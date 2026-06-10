<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    protected $model = AttendanceRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'work_date' => fake()->dateTimeBetween('-1 month')->format('Y-m-d'),
            'scheduled_start_time' => '08:22:00',
            'checked_in_at' => null,
            'checked_out_at' => null,
            'late_minutes' => 0,
        ];
    }

    public function checkedIn(): static
    {
        return $this->state(function (array $attributes): array {
            $workDate = $attributes['work_date'] ?? now()->toDateString();

            return [
                'checked_in_at' => "{$workDate} 08:30:00",
                'late_minutes' => 8,
            ];
        });
    }
}
