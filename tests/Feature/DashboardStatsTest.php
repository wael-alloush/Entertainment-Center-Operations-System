<?php

use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\SessionDurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SessionDurationSeeder::class);
    $this->user = User::factory()->create();
});

it('redirects legacy dashboard url to active invoices', function (): void {
    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertRedirect(route('invoices.active'));
});

it('paginates completed invoices', function (): void {
    Invoice::factory()->completed()->count(30)->create([
        'entered_at' => now(),
        'exited_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('invoices.completed'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('invoices/completed')
            ->has('invoices.data', 25)
            ->where('invoices.total', 30)
            ->where('invoices.last_page', 2));
});

it('loads the first page of active invoices immediately', function (): void {
    Invoice::factory()->active()->count(3)->create();

    $this->actingAs($this->user)
        ->get(route('invoices.active'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('invoices/active')
            ->has('invoices.data', 3)
            ->where('invoices.total', 3));
});
