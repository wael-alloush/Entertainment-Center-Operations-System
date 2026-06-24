<?php

use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceService;
use Database\Seeders\SessionDurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SessionDurationSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->reception = User::factory()->reception()->create();
});

it('shows only todays completed invoices by default', function (): void {
    Carbon::setTestNow('2026-06-10 14:00:00');

    Invoice::factory()->completed()->create([
        'entered_at' => now(),
        'exited_at' => now(),
        'invoice_number' => '100001',
    ]);

    Invoice::factory()->completed()->create([
        'entered_at' => now()->subDay(),
        'exited_at' => now()->subDay(),
        'invoice_number' => '100002',
    ]);

    $this->actingAs($this->reception)
        ->get(route('invoices.completed'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('invoices/completed')
            ->where('canFilterCompletedByDate', false)
            ->where('filters.from_date', '2026-06-10')
            ->where('filters.to_date', '2026-06-10')
            ->has('invoices.data', 1)
            ->where('invoices.data.0.invoice_number', '100001'));
});

it('prevents reception from viewing completed invoices outside today', function (): void {
    Carbon::setTestNow('2026-06-10 14:00:00');

    Invoice::factory()->completed()->create([
        'entered_at' => now()->subDays(3),
        'exited_at' => now()->subDays(3),
        'invoice_number' => '200001',
    ]);

    $this->actingAs($this->reception)
        ->get(route('invoices.completed', [
            'from_date' => now()->subWeek()->toDateString(),
            'to_date' => now()->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('invoices.data', 0)
            ->where('filters.from_date', '2026-06-10')
            ->where('filters.to_date', '2026-06-10'));
});

it('allows admin to browse all completed invoices when date filters are cleared', function (): void {
    Carbon::setTestNow('2026-06-10 14:00:00');

    Invoice::factory()->completed()->create([
        'entered_at' => now()->subDays(5),
        'exited_at' => now()->subDays(5),
        'invoice_number' => '300001',
    ]);

    Invoice::factory()->completed()->create([
        'entered_at' => now(),
        'exited_at' => now(),
        'invoice_number' => '300002',
    ]);

    $this->actingAs($this->admin)
        ->get(route('invoices.completed', ['from_date' => '', 'to_date' => '']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canFilterCompletedByDate', true)
            ->where('filters.from_date', '')
            ->where('filters.to_date', '')
            ->has('invoices.data', 2));
});

it('keeps cleared date filters when admin paginates completed invoices', function (): void {
    Carbon::setTestNow('2026-06-10 14:00:00');

    Invoice::factory()->completed()->count(30)->create([
        'entered_at' => now()->subDays(5),
        'exited_at' => now()->subDays(5),
    ]);

    $this->actingAs($this->admin)
        ->get(route('invoices.completed', ['from_date' => '', 'to_date' => '', 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.from_date', '')
            ->where('filters.to_date', '')
            ->has('invoices.data', 5)
            ->where('invoices.current_page', 2)
            ->where('invoices.total', 30));

    $request = Request::create(route('invoices.completed', ['from_date' => '', 'to_date' => '']), 'GET');
    $request->setUserResolver(fn (): User => $this->admin);

    $paginator = app(InvoiceService::class)->completedRowsList($request);

    expect($paginator->url(2))->toContain('from_date=')
        ->and($paginator->url(2))->toContain('to_date=');
});

it('allows admin to filter completed invoices by a custom date range', function (): void {
    Carbon::setTestNow('2026-06-10 14:00:00');

    Invoice::factory()->completed()->create([
        'entered_at' => Carbon::parse('2026-06-08 12:00:00'),
        'exited_at' => Carbon::parse('2026-06-08 13:00:00'),
        'invoice_number' => '400001',
    ]);

    Invoice::factory()->completed()->create([
        'entered_at' => now(),
        'exited_at' => now(),
        'invoice_number' => '400002',
    ]);

    $this->actingAs($this->admin)
        ->get(route('invoices.completed', [
            'from_date' => '2026-06-08',
            'to_date' => '2026-06-08',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('invoices.data', 1)
            ->where('invoices.data.0.invoice_number', '400001'));
});
