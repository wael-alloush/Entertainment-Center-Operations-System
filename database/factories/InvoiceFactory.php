<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SessionDuration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $enteredAt = fake()->dateTimeBetween('-1 day', 'now');
        $sessionDuration = SessionDuration::query()->inRandomOrder()->first()
            ?? SessionDuration::factory()->create();
        $durationMinutes = $sessionDuration->duration_minutes ?? fake()->randomElement([33, 65, 125]);

        return [
            'invoice_number' => fake()->unique()->numerify('######'),
            'customer_id' => Customer::query()->inRandomOrder()->value('id') ?? Customer::factory(),
            'phone_number' => '05'.fake()->numerify('########'),
            'children_count' => fake()->numberBetween(1, 5),
            'session_duration_id' => $sessionDuration->id,
            'status' => InvoiceStatus::Active,
            'entered_at' => $enteredAt,
            'expected_exit_at' => $durationMinutes !== null
                ? (clone $enteredAt)->modify("+{$durationMinutes} minutes")
                : null,
            'exited_at' => null,
            'created_by' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvoiceStatus::Active,
            'exited_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes): array {
            $exitedAt = isset($attributes['expected_exit_at'])
                ? (clone $attributes['expected_exit_at'])
                : now();

            return [
                'status' => InvoiceStatus::Completed,
                'exited_at' => $exitedAt,
            ];
        });
    }

    public function needsParentCall(): static
    {
        return $this->state(fn (array $attributes): array => [
            'needs_parent_call' => true,
            'parent_call_marked_at' => now(),
        ]);
    }
}
