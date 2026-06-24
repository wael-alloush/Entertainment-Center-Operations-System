<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SessionDuration;
use App\Models\User;
use App\Services\BonusProgramService;
use Database\Seeders\SessionDurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SessionDurationSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->reception = User::factory()->reception()->create();
});

function createInvoiceWithPendingBonus(User $admin): array
{
    $customer = Customer::factory()->create(['phone_number' => '0599333444']);
    $hour = SessionDuration::query()->where('name', 'ساعة')->first();

    $sourceInvoice = Invoice::factory()->active()->create([
        'customer_id' => $customer->id,
        'phone_number' => '0599333444',
        'children_count' => 2,
        'session_duration_id' => $hour->id,
        'entered_at' => now(),
    ]);

    $pendingBonus = app(BonusProgramService::class)->addBonus($sourceInvoice, [
        'children_count' => 2,
        'session_duration_id' => $hour->id,
    ], $admin);

    return [$sourceInvoice->fresh(), $pendingBonus->fresh()];
}

it('renders pending bonuses page with added bonuses', function (): void {
    [$sourceInvoice, $pendingBonus] = createInvoiceWithPendingBonus($this->admin);

    $this->actingAs($this->reception)
        ->get(route('invoices.bonuses'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoices/bonuses')
            ->has('bonuses.data', 1)
            ->where('bonuses.data.0.id', $pendingBonus->id)
            ->where('bonuses.data.0.phone_number', '0599333444')
            ->where('bonuses.data.0.children_count', 2)
            ->where('bonusProgram.validity_days', 30)
            ->where('pendingBonusCount', 1));

    expect($sourceInvoice->is_bonus)->toBeFalse();
});

it('activates a pending bonus as a new invoice on the same phone number', function (): void {
    [$sourceInvoice, $pendingBonus] = createInvoiceWithPendingBonus($this->admin);

    $this->actingAs($this->reception)
        ->post(route('customer-bonuses.activate', $pendingBonus))
        ->assertRedirect(route('invoices.active'))
        ->assertSessionHas('success');

    $sourceInvoice->refresh();
    $pendingBonus->refresh();
    $bonusInvoice = Invoice::query()->findOrFail($pendingBonus->invoice_id);

    expect($sourceInvoice->is_bonus)->toBeFalse()
        ->and($bonusInvoice->is_bonus)->toBeTrue()
        ->and($bonusInvoice->bonus_display_number)->toBe(1)
        ->and($bonusInvoice->phone_number)->toBe('0599333444')
        ->and($bonusInvoice->id)->not->toBe($sourceInvoice->id)
        ->and($pendingBonus->display_number)->toBe(1);
});

it('filters pending bonuses by phone number', function (): void {
    [, $pendingBonus] = createInvoiceWithPendingBonus($this->admin);

    $otherCustomer = Customer::factory()->create(['phone_number' => '0599000999']);
    $hour = SessionDuration::query()->where('name', 'ساعة')->first();
    $otherSourceInvoice = Invoice::factory()->active()->create([
        'customer_id' => $otherCustomer->id,
        'phone_number' => '0599000999',
        'session_duration_id' => $hour->id,
    ]);

    app(BonusProgramService::class)->addBonus($otherSourceInvoice, [
        'children_count' => 1,
        'session_duration_id' => $hour->id,
    ], $this->admin);

    $this->actingAs($this->reception)
        ->get(route('invoices.bonuses', ['search' => '0599333444']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('bonuses.data', 1)
            ->where('bonuses.data.0.id', $pendingBonus->id)
            ->where('bonuses.data.0.phone_number', '0599333444'));
});
