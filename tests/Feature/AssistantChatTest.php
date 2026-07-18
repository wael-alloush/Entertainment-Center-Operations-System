<?php

use App\Models\Customer;
use App\Models\CustomerBonus;
use App\Models\CustomerCredit;
use App\Models\Invoice;
use App\Models\SessionDuration;
use App\Models\User;
use App\Services\AssistantToolExecutor;
use Database\Seeders\SessionDurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SessionDurationSeeder::class);
    $this->user = User::factory()->reception()->create();
    config([
        'services.openai.key' => 'test-openai-key',
        'services.openai.model' => 'gpt-4.1-mini',
        'services.openai.base_url' => 'https://api.openai.com/v1',
    ]);
});

it('redirects guests away from the assistant chat endpoint', function (): void {
    $this->postJson(route('assistant.chat'), [
        'message' => 'ملخص اليوم',
    ])->assertUnauthorized();
});

it('rejects chat requests when the openai key is missing', function (): void {
    config(['services.openai.key' => '']);

    $this->actingAs($this->user)
        ->postJson(route('assistant.chat'), [
            'message' => 'ملخص اليوم',
        ])
        ->assertStatus(503)
        ->assertJsonPath('message', 'مفتاح OpenAI غير مُعد. أضف OPENAI_API_KEY إلى ملف البيئة.');
});

it('looks up customer visit and pending records through assistant tools', function (): void {
    $sessionDurationId = SessionDuration::query()->value('id');
    $customer = Customer::factory()->create([
        'phone_number' => '0599000100',
        'visit_count' => 3,
    ]);

    Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => $customer->phone_number,
        'session_duration_id' => $sessionDurationId,
        'entered_at' => now()->subDays(2),
        'exited_at' => now()->subDays(2)->addHour(),
        'counts_as_visit' => true,
    ]);

    Invoice::factory()->active()->create([
        'customer_id' => $customer->id,
        'phone_number' => $customer->phone_number,
        'session_duration_id' => $sessionDurationId,
    ]);

    CustomerBonus::query()->create([
        'customer_id' => $customer->id,
        'source_invoice_id' => null,
        'children_count' => 2,
        'session_duration_id' => $sessionDurationId,
        'title' => 'مكافأة اختبار',
        'expires_at' => now()->addDays(10),
        'created_by' => $this->user->id,
    ]);

    CustomerCredit::query()->create([
        'customer_id' => $customer->id,
        'children_count' => 1,
        'session_duration_id' => $sessionDurationId,
        'counts_as_visit' => true,
        'created_by' => $this->user->id,
    ]);

    $result = app(AssistantToolExecutor::class)->lookupCustomer('0599000100');

    expect($result['found'])->toBeTrue()
        ->and($result['phone_number'])->toBe('0599000100')
        ->and($result['visit_count'])->toBeGreaterThanOrEqual(1)
        ->and($result['has_pending_bonus'])->toBeTrue()
        ->and($result['pending_credits_count'])->toBe(1)
        ->and($result['active_invoices_count'])->toBe(1);
});

it('returns active invoice counts and today summary from assistant tools', function (): void {
    Invoice::factory()->active()->count(2)->create();
    Invoice::factory()->completed()->create([
        'exited_at' => now(),
        'entered_at' => now()->subHour(),
    ]);

    $executor = app(AssistantToolExecutor::class);

    expect($executor->countActiveInvoices()['active_invoices_count'])->toBe(2)
        ->and($executor->todaySummary()['active_invoices_count'])->toBe(2)
        ->and($executor->todaySummary()['completed_today_count'])->toBe(1)
        ->and($executor->bonusSettings())->toHaveKeys(['visit_threshold', 'validity_days', 'expiry_check_time']);
});

it('runs tool calling against openai and returns the final assistant reply', function (): void {
    $customer = Customer::factory()->create([
        'phone_number' => '0599000222',
        'visit_count' => 4,
    ]);

    Invoice::factory()->active()->create([
        'customer_id' => $customer->id,
        'phone_number' => $customer->phone_number,
    ]);

    Http::fake([
        'api.openai.com/v1/chat/completions' => Http::sequence()
            ->push([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_lookup_1',
                            'type' => 'function',
                            'function' => [
                                'name' => 'lookup_customer',
                                'arguments' => json_encode([
                                    'phone_number' => '0599000222',
                                ], JSON_THROW_ON_ERROR),
                            ],
                        ]],
                    ],
                ]],
            ])
            ->push([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'العميل 0599000222 لديه جلسة نشطة واحدة.',
                    ],
                ]],
            ]),
    ]);

    $this->actingAs($this->user)
        ->postJson(route('assistant.chat'), [
            'message' => 'كم جلسة نشطة لرقم 0599000222؟',
        ])
        ->assertOk()
        ->assertJsonPath('reply', 'العميل 0599000222 لديه جلسة نشطة واحدة.')
        ->assertJsonPath('tools_used.0', 'lookup_customer');

    Http::assertSentCount(2);
});

it('validates the assistant chat message', function (): void {
    $this->actingAs($this->user)
        ->postJson(route('assistant.chat'), [
            'message' => '',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);
});
