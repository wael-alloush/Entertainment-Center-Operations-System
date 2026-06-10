<?php

namespace Database\Factories;

use App\Models\SessionDuration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionDuration>
 */
class SessionDurationFactory extends Factory
{
    protected $model = SessionDuration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['نصف ساعة', 'ساعة', 'ساعتين', 'مفتوح']),
            'duration_minutes' => fake()->randomElement([33, 65, 125, null]),
            'color' => fake()->hexColor(),
            'sort_order' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'مفتوح',
            'duration_minutes' => null,
        ]);
    }
}
