<?php

namespace Database\Seeders;

use App\Models\SessionDuration;
use Illuminate\Database\Seeder;

class SessionDurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $durations = [
            [
                'name' => 'نصف ساعة',
                'duration_minutes' => 33,
                'color' => 'oklch(66.7% 0.295 322.15)',
                'sort_order' => 1,
            ],
            [
                'name' => 'ساعة',
                'duration_minutes' => 65,
                'color' => 'var(--primary)',
                'sort_order' => 2,
            ],
            [
                'name' => 'ساعتين',
                'duration_minutes' => 125,
                'color' => 'oklch(64.5% 0.246 16.439)',
                'sort_order' => 3,
            ],
            [
                'name' => 'مفتوح',
                'duration_minutes' => null,
                'color' => 'oklch(42.4% 0.199 265.638)',
                'sort_order' => 4,
            ],
        ];

        foreach ($durations as $duration) {
            SessionDuration::query()->updateOrCreate(
                ['name' => $duration['name']],
                $duration,
            );
        }
    }
}
