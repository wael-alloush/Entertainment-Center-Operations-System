<?php

use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\SessionDurationSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SessionDurationSeeder::class);
    $this->user = User::factory()->create();
});

it('renders dashboard with stats', function (): void {
    Invoice::factory()->active()->count(2)->create();
    Invoice::factory()->completed()->create();

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('stats')
            ->where('stats.active_invoices', 2));
});

it('paginates completed invoices', function (): void {
    Invoice::factory()->completed()->count(30)->create();

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
