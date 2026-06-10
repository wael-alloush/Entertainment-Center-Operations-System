<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone_number' => '05'.fake()->unique()->numerify('########'),
            'visit_count' => fake()->numberBetween(1, 20),
            'first_visit_at' => fake()->dateTimeBetween('-1 year'),
            'last_visit_at' => fake()->dateTimeBetween('-1 month'),
        ];
    }
}
