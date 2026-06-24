<?php

use App\Models\CustomerCredit;
use App\Models\Invoice;
use App\Models\SessionDuration;
use App\Models\User;
use App\Services\CreditProgramService;
use Database\Seeders\SessionDurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SessionDurationSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->reception = User::factory()->reception()->create();
});

function createPendingCredit(User $admin, bool $countsAsVisit = true): CustomerCredit
{
    $hour = SessionDuration::query()->where('name', 'ساعة')->first();

    return app(CreditProgramService::class)->addCredit([
        'phone_number' => '0599444555',
        'children_count' => 2,
        'session_duration_id' => $hour->id,
        'counts_as_visit' => $countsAsVisit,
    ], $admin);
}

it('renders pending credits page', function (): void {
    $pendingCredit = createPendingCredit($this->admin);

    $this->actingAs($this->reception)
        ->get(route('invoices.credits'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoices/credits')
            ->has('credits.data', 1)
            ->where('credits.data.0.id', $pendingCredit->id)
            ->where('credits.data.0.phone_number', '0599444555')
            ->where('credits.data.0.counts_as_visit', true)
            ->where('pendingCreditCount', 1));
});

it('stores a pending credit from the dialog', function (): void {
    $hour = SessionDuration::query()->where('name', 'ساعة')->first();

    $this->actingAs($this->admin)
        ->post(route('credits.store'), [
            'phone_number' => '0599666777',
            'children_count' => 3,
            'session_duration_id' => $hour->id,
            'counts_as_visit' => false,
        ])
        ->assertRedirect(route('invoices.credits'))
        ->assertSessionHas('success');

    expect(CustomerCredit::query()->pending()->count())->toBe(1)
        ->and(CustomerCredit::query()->first()?->counts_as_visit)->toBeFalse();
});

it('forbids reception users from adding credits', function (): void {
    $hour = SessionDuration::query()->where('name', 'ساعة')->first();

    $this->actingAs($this->reception)
        ->post(route('credits.store'), [
            'phone_number' => '0599666777',
            'children_count' => 3,
            'session_duration_id' => $hour->id,
            'counts_as_visit' => false,
        ])
        ->assertForbidden();

    expect(CustomerCredit::query()->pending()->count())->toBe(0);
});

it('activates a pending credit as a new invoice', function (): void {
    $pendingCredit = createPendingCredit($this->admin);

    $this->actingAs($this->reception)
        ->post(route('customer-credits.activate', $pendingCredit))
        ->assertRedirect(route('invoices.active'))
        ->assertSessionHas('success');

    $pendingCredit->refresh();
    $creditInvoice = Invoice::query()->findOrFail($pendingCredit->invoice_id);

    expect($creditInvoice->is_credit)->toBeTrue()
        ->and($creditInvoice->credit_display_number)->toBe(1)
        ->and($creditInvoice->phone_number)->toBe('0599444555')
        ->and($creditInvoice->counts_as_visit)->toBeTrue()
        ->and($pendingCredit->display_number)->toBe(1);
});

it('allows admin to update a pending credit', function (): void {
    $pendingCredit = createPendingCredit($this->admin);
    $twoHours = SessionDuration::query()->where('name', 'ساعتين')->first();

    $this->actingAs($this->admin)
        ->patch(route('customer-credits.update', $pendingCredit), [
            'phone_number' => '0599777666',
            'children_count' => 4,
            'session_duration_id' => $twoHours->id,
            'counts_as_visit' => false,
        ])
        ->assertRedirect(route('invoices.credits'))
        ->assertSessionHas('success');

    $pendingCredit->refresh();

    expect($pendingCredit->customer->phone_number)->toBe('0599777666')
        ->and($pendingCredit->children_count)->toBe(4)
        ->and($pendingCredit->session_duration_id)->toBe($twoHours->id)
        ->and($pendingCredit->counts_as_visit)->toBeFalse();
});

it('allows admin to delete a pending credit', function (): void {
    $pendingCredit = createPendingCredit($this->admin);

    $this->actingAs($this->admin)
        ->delete(route('customer-credits.destroy', $pendingCredit))
        ->assertRedirect(route('invoices.credits'))
        ->assertSessionHas('success');

    expect(CustomerCredit::query()->whereKey($pendingCredit->id)->exists())->toBeFalse();
});

it('forbids reception users from updating or deleting credits', function (): void {
    $pendingCredit = createPendingCredit($this->admin);
    $hour = SessionDuration::query()->where('name', 'ساعة')->first();

    $this->actingAs($this->reception)
        ->patch(route('customer-credits.update', $pendingCredit), [
            'phone_number' => '0599000111',
            'children_count' => 2,
            'session_duration_id' => $hour->id,
            'counts_as_visit' => true,
        ])
        ->assertForbidden();

    $this->actingAs($this->reception)
        ->delete(route('customer-credits.destroy', $pendingCredit))
        ->assertForbidden();

    expect(CustomerCredit::query()->whereKey($pendingCredit->id)->exists())->toBeTrue();
});

it('filters pending credits by phone number', function (): void {
    $pendingCredit = createPendingCredit($this->admin);

    app(CreditProgramService::class)->addCredit([
        'phone_number' => '0599000999',
        'children_count' => 1,
        'session_duration_id' => SessionDuration::query()->value('id'),
        'counts_as_visit' => true,
    ], $this->admin);

    $this->actingAs($this->reception)
        ->get(route('invoices.credits', ['search' => '0599444555']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('credits.data', 1)
            ->where('credits.data.0.id', $pendingCredit->id));
});
