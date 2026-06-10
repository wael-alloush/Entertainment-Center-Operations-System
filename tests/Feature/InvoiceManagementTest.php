<?php

use App\Enums\InvoiceStatus;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\SessionDuration;
use App\Models\User;
use App\Support\TimeFormatter;
use Carbon\Carbon;
use Database\Seeders\SessionDurationSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SessionDurationSeeder::class);
    $this->user = User::factory()->create();
});

it('lists active invoices for authenticated users', function (): void {
    Invoice::factory()->active()->create();

    $this->actingAs($this->user)
        ->get(route('invoices.active'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('invoices/active')
            ->has('sessionDurations', 4)
            ->has('invoices.data', 1));
});

it('creates a new invoice', function (): void {
    $duration = SessionDuration::query()->first();

    $this->actingAs($this->user)
        ->post(route('invoices.store'), [
            'invoice_number' => '999888',
            'phone_number' => '0598765432',
            'children_count' => 2,
            'session_duration_id' => $duration->id,
        ])
        ->assertRedirect(route('invoices.active'));

    expect(Invoice::query()->where('invoice_number', '999888')->exists())->toBeTrue();
});

it('records invoice entry time in saudi local timezone', function (): void {
    $duration = SessionDuration::query()->first();

    $this->travelTo(Carbon::parse('2026-06-09 16:45:00', 'Asia/Riyadh'));

    $this->actingAs($this->user)
        ->post(route('invoices.store'), [
            'invoice_number' => '777001',
            'phone_number' => '0598111222',
            'children_count' => 1,
            'session_duration_id' => $duration->id,
        ])
        ->assertRedirect(route('invoices.active'));

    $invoice = Invoice::query()->where('invoice_number', '777001')->firstOrFail();

    expect($invoice->entered_at->format('H:i'))->toBe('16:45')
        ->and(TimeFormatter::formatTime($invoice->entered_at))->toBe('04:45 م');
});

it('counts customer visits by unique days not invoices', function (): void {
    $duration = SessionDuration::query()->first();

    $this->actingAs($this->user)
        ->post(route('invoices.store'), [
            'invoice_number' => '111111',
            'phone_number' => '0598000001',
            'children_count' => 2,
            'session_duration_id' => $duration->id,
        ])
        ->assertRedirect();

    $this->actingAs($this->user)
        ->post(route('invoices.store'), [
            'invoice_number' => '222222',
            'phone_number' => '0598000001',
            'children_count' => 1,
            'session_duration_id' => $duration->id,
        ])
        ->assertRedirect();

    $customer = \App\Models\Customer::query()->where('phone_number', '0598000001')->first();

    expect($customer)->not->toBeNull()
        ->and($customer->visit_count)->toBe(1);

    $this->actingAs($this->user)
        ->get(route('invoices.active'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('invoices.data.0.phone_number', '0598000001')
            ->where('invoices.data.0.visit_count', 1));
});

it('shows visit badge as unique visit days for the phone number', function (): void {
    $phone = '0598000099';

    Invoice::factory()->completed()->create([
        'phone_number' => $phone,
        'entered_at' => now()->subDays(2),
        'exited_at' => now()->subDays(2)->addHour(),
    ]);

    Invoice::factory()->completed()->create([
        'phone_number' => $phone,
        'entered_at' => now()->subDay(),
        'exited_at' => now()->subDay()->addHour(),
    ]);

    Invoice::factory()->active()->create([
        'phone_number' => $phone,
        'entered_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('invoices.active'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('invoices.data.0.phone_number', $phone)
            ->where('invoices.data.0.visit_count', 3));
});

it('completes an active invoice', function (): void {
    $invoice = Invoice::factory()->active()->create();

    $this->actingAs($this->user)
        ->patch(route('invoices.checkout', $invoice))
        ->assertRedirect();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Completed)
        ->and($invoice->fresh()->exited_at)->not->toBeNull();
});

it('extends an active invoice session and adds duration to exit time', function (): void {
    $sessionDuration = SessionDuration::query()->where('name', 'ساعة')->firstOrFail();
    $extension = SessionDuration::query()->where('name', 'نصف ساعة')->firstOrFail();
    $enteredAt = now()->subMinutes(10);
    $expectedExitAt = $enteredAt->copy()->addMinutes($sessionDuration->duration_minutes);

    $invoice = Invoice::factory()->active()->create([
        'session_duration_id' => $sessionDuration->id,
        'entered_at' => $enteredAt,
        'expected_exit_at' => $expectedExitAt,
    ]);

    $this->actingAs($this->user)
        ->post(route('invoices.extend', $invoice), [
            'session_duration_id' => $extension->id,
        ])
        ->assertRedirect();

    $invoice->refresh();

    expect($invoice->extensions)->toHaveCount(1)
        ->and($invoice->expected_exit_at?->toDateTimeString())
        ->toBe($expectedExitAt->copy()->addMinutes($extension->duration_minutes)->toDateTimeString());
});

it('forbids extending invoices with open exit time', function (): void {
    $openDuration = SessionDuration::query()->where('name', 'مفتوح')->firstOrFail();
    $extension = SessionDuration::query()->where('name', 'نصف ساعة')->firstOrFail();

    $invoice = Invoice::factory()->active()->create([
        'session_duration_id' => $openDuration->id,
        'expected_exit_at' => null,
    ]);

    $this->actingAs($this->user)
        ->from(route('invoices.active'))
        ->post(route('invoices.extend', $invoice), [
            'session_duration_id' => $extension->id,
        ])
        ->assertRedirect(route('invoices.active'))
        ->assertSessionHas('error');

    expect($invoice->fresh()->extensions)->toHaveCount(0);
});

it('clears exit time when extending with an open session duration', function (): void {
    $sessionDuration = SessionDuration::query()->where('name', 'ساعة')->firstOrFail();
    $openDuration = SessionDuration::query()->where('name', 'مفتوح')->firstOrFail();
    $enteredAt = now()->subMinutes(10);

    $invoice = Invoice::factory()->active()->create([
        'session_duration_id' => $sessionDuration->id,
        'entered_at' => $enteredAt,
        'expected_exit_at' => $enteredAt->copy()->addMinutes($sessionDuration->duration_minutes),
    ]);

    $this->actingAs($this->user)
        ->post(route('invoices.extend', $invoice), [
            'session_duration_id' => $openDuration->id,
        ])
        ->assertRedirect();

    expect($invoice->fresh()->expected_exit_at)->toBeNull();
});

it('updates invoice number via json', function (): void {
    $invoice = Invoice::factory()->active()->create(['invoice_number' => '111222']);

    $this->actingAs($this->user)
        ->patchJson(route('invoices.update', $invoice), [
            'invoice_number' => '333444',
        ])
        ->assertOk()
        ->assertJsonPath('invoice.invoice_number', '333444');

    expect($invoice->fresh()->invoice_number)->toBe('333444');
});

it('updates phone number and links customer', function (): void {
    $invoice = Invoice::factory()->active()->create(['phone_number' => '0599111111']);

    $this->actingAs($this->user)
        ->patchJson(route('invoices.update', $invoice), [
            'phone_number' => '0599222222',
        ])
        ->assertOk()
        ->assertJsonPath('invoice.phone_number', '0599222222');

    expect($invoice->fresh()->phone_number)->toBe('0599222222');
});

it('updates children count via json', function (): void {
    $invoice = Invoice::factory()->active()->create(['children_count' => 1]);

    $this->actingAs($this->user)
        ->patchJson(route('invoices.update', $invoice), [
            'children_count' => 4,
        ])
        ->assertOk()
        ->assertJsonPath('invoice.children_count', 4);
});

it('allows duplicate invoice numbers on create and update', function (): void {
    $duration = SessionDuration::query()->first();
    Invoice::factory()->active()->create(['invoice_number' => '555666']);

    $this->actingAs($this->user)
        ->post(route('invoices.store'), [
            'invoice_number' => '555666',
            'phone_number' => '0598765432',
            'children_count' => 2,
            'session_duration_id' => $duration->id,
        ])
        ->assertRedirect(route('invoices.active'));

    $invoice = Invoice::factory()->active()->create(['invoice_number' => '777888']);

    $this->actingAs($this->user)
        ->patchJson(route('invoices.update', $invoice), [
            'invoice_number' => '555666',
        ])
        ->assertOk()
        ->assertJsonPath('invoice.invoice_number', '555666');

    expect(Invoice::query()->where('invoice_number', '555666')->count())->toBe(3);
});

it('allows reception user to update invoice fields', function (): void {
    $reception = User::factory()->reception()->create();
    $invoice = Invoice::factory()->active()->create(['children_count' => 2]);

    $this->actingAs($reception)
        ->patchJson(route('invoices.update', $invoice), [
            'children_count' => 3,
        ])
        ->assertOk()
        ->assertJsonPath('invoice.children_count', 3);
});

it('reactivates a completed invoice without changing its times', function (): void {
    $enteredAt = now()->subDays(2)->setTime(10, 30);
    $expectedExit = now()->subDays(2)->setTime(12, 0);
    $exitedAt = now()->subDays(2)->setTime(11, 45);

    $invoice = Invoice::factory()->completed()->create([
        'entered_at' => $enteredAt,
        'expected_exit_at' => $expectedExit,
        'exited_at' => $exitedAt,
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('invoices.reactivate', $invoice))
        ->assertRedirect(route('invoices.active'));

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Active)
        ->and($invoice->entered_at->toDateTimeString())->toBe($enteredAt->toDateTimeString())
        ->and($invoice->expected_exit_at->toDateTimeString())->toBe($expectedExit->toDateTimeString())
        ->and($invoice->exited_at->toDateTimeString())->toBe($exitedAt->toDateTimeString());
});

it('forbids reactivating active invoices', function (): void {
    $invoice = Invoice::factory()->active()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('invoices.reactivate', $invoice))
        ->assertForbidden();
});

it('forbids reception users from reactivating completed invoices', function (): void {
    $invoice = Invoice::factory()->completed()->create();

    $this->actingAs($this->user)
        ->patch(route('invoices.reactivate', $invoice))
        ->assertForbidden();
});

it('searches invoices by phone number', function (): void {
    Invoice::factory()->active()->create(['phone_number' => '0599111111']);
    Invoice::factory()->active()->create(['phone_number' => '0599222222']);

    $this->actingAs($this->user)
        ->get(route('invoices.active', ['search' => '0599111111']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('invoices.data', 1));
});

it('toggles parent call flag on active invoices for reception users', function (): void {
    $invoice = Invoice::factory()->active()->create();

    $this->actingAs($this->user)
        ->from(route('invoices.active'))
        ->patch(route('invoices.parent-call', $invoice))
        ->assertRedirect(route('invoices.active'))
        ->assertSessionHas('success');

    expect($invoice->fresh())
        ->needs_parent_call->toBeTrue()
        ->parent_call_marked_by->toBe($this->user->id)
        ->and($invoice->fresh()->parent_call_marked_at)->not->toBeNull();

    $this->actingAs($this->user)
        ->from(route('invoices.active'))
        ->patch(route('invoices.parent-call', $invoice))
        ->assertRedirect(route('invoices.active'));

    expect($invoice->fresh())
        ->needs_parent_call->toBeFalse()
        ->parent_call_marked_at->toBeNull()
        ->parent_call_marked_by->toBeNull();
});

it('shows parent call flag in active invoice list', function (): void {
    $invoice = Invoice::factory()->active()->needsParentCall()->create([
        'parent_call_marked_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('invoices.active'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('invoices/active')
            ->where('invoices.data.0.id', $invoice->id)
            ->where('invoices.data.0.needs_parent_call', true)
            ->where('invoices.data.0.parent_call_marked_by', $this->user->name));
});

it('keeps active invoice order by entry time regardless of parent call flag', function (): void {
    $older = Invoice::factory()->active()->needsParentCall()->create([
        'entered_at' => now()->subHour(),
        'parent_call_marked_by' => $this->user->id,
    ]);
    $newer = Invoice::factory()->active()->create(['entered_at' => now()]);

    $this->actingAs($this->user)
        ->get(route('invoices.active'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('invoices.data.0.id', $newer->id)
            ->where('invoices.data.1.id', $older->id));
});

it('clears parent call flag when invoice is checked out', function (): void {
    $invoice = Invoice::factory()->active()->needsParentCall()->create([
        'parent_call_marked_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->patch(route('invoices.checkout', $invoice))
        ->assertRedirect();

    expect($invoice->fresh())
        ->needs_parent_call->toBeFalse()
        ->parent_call_marked_at->toBeNull()
        ->parent_call_marked_by->toBeNull();
});

it('forbids toggling parent call on completed invoices', function (): void {
    $invoice = Invoice::factory()->completed()->create();

    $this->actingAs($this->user)
        ->patch(route('invoices.parent-call', $invoice))
        ->assertForbidden();
});

it('returns only changed active invoices for lightweight sync', function (): void {
    $invoice = Invoice::factory()->active()->create();
    $since = now()->subMinute()->toIso8601String();

    $this->travel(2)->seconds();

    $invoice->update(['needs_parent_call' => true]);

    $this->actingAs($this->user)
        ->getJson(route('invoices.active.changes', ['since' => $since]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'updated')
        ->assertJsonPath('updated.0.id', $invoice->id)
        ->assertJsonPath('updated.0.needs_parent_call', true)
        ->assertJsonPath('removed_ids', []);
});

it('returns removed invoice ids when invoices are checked out', function (): void {
    $invoice = Invoice::factory()->active()->create();
    $since = now()->subMinute()->toIso8601String();

    $this->travel(2)->seconds();

    $invoice->update([
        'status' => InvoiceStatus::Completed,
        'exited_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->getJson(route('invoices.active.changes', ['since' => $since]))
        ->assertSuccessful()
        ->assertJsonPath('updated', [])
        ->assertJsonPath('removed_ids', [$invoice->id]);
});

it('returns empty changes payload when nothing changed', function (): void {
    Invoice::factory()->active()->create();

    $this->actingAs($this->user)
        ->getJson(route('invoices.active.changes', ['since' => now()->toIso8601String()]))
        ->assertSuccessful()
        ->assertJsonPath('updated', [])
        ->assertJsonPath('removed_ids', [])
        ->assertJsonPath('summary', null);
});
