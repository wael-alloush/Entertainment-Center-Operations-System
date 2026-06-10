<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'مدير النظام',
            'email' => 'admin@example.com',
        ]);

        User::factory()->reception()->create([
            'name' => 'موظف الاستقبال',
            'email' => 'reception@example.com',
        ]);

        User::factory()->admin()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            SessionDurationSeeder::class,
            EmployeeSeeder::class,
            DemoDataSeeder::class,
        ]);

        Setting::query()->updateOrCreate(
            ['key' => 'work_schedule'],
            ['value' => ['start_time' => '08:22']],
        );
    }
}
