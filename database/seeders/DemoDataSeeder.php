<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SessionDuration;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $halfHour = SessionDuration::query()->where('name', 'نصف ساعة')->first();
        $hour = SessionDuration::query()->where('name', 'ساعة')->first();
        $twoHours = SessionDuration::query()->where('name', 'ساعتين')->first();

        if (! $halfHour || ! $hour || ! $twoHours) {
            return;
        }

        $customer = Customer::query()->updateOrCreate(
            ['phone_number' => '0599123456'],
            [
                'visit_count' => 5,
                'first_visit_at' => now()->subMonths(2),
                'last_visit_at' => now(),
            ],
        );

        $enteredAt = now()->subMinutes(20);

        Invoice::query()->updateOrCreate(
            ['invoice_number' => '100001'],
            [
                'customer_id' => $customer->id,
                'phone_number' => '0599123456',
                'children_count' => 3,
                'session_duration_id' => $hour->id,
                'status' => InvoiceStatus::Active,
                'entered_at' => $enteredAt,
                'expected_exit_at' => $enteredAt->copy()->addMinutes($hour->duration_minutes),
            ],
        );

        $customerTwo = Customer::query()->updateOrCreate(
            ['phone_number' => '0599111222'],
            [
                'visit_count' => 2,
                'first_visit_at' => now()->subMonth(),
                'last_visit_at' => now()->subHours(2),
            ],
        );

        $customerThree = Customer::query()->updateOrCreate(
            ['phone_number' => '0599333444'],
            [
                'visit_count' => 1,
                'first_visit_at' => now()->subDays(2),
                'last_visit_at' => now()->subDay(),
            ],
        );

        Invoice::query()->updateOrCreate(
            ['invoice_number' => '100002'],
            [
                'customer_id' => $customerTwo->id,
                'phone_number' => '0599111222',
                'children_count' => 2,
                'session_duration_id' => $halfHour->id,
                'status' => InvoiceStatus::Completed,
                'entered_at' => now()->subHours(3),
                'expected_exit_at' => now()->subHours(2)->subMinutes(27),
                'exited_at' => now()->subHours(2),
            ],
        );

        Invoice::query()->updateOrCreate(
            ['invoice_number' => '100003'],
            [
                'customer_id' => $customerThree->id,
                'phone_number' => '0599333444',
                'children_count' => 1,
                'session_duration_id' => $twoHours->id,
                'status' => InvoiceStatus::Completed,
                'entered_at' => now()->subDays(1),
                'expected_exit_at' => now()->subDays(1)->addMinutes($twoHours->duration_minutes),
                'exited_at' => now()->subDays(1)->addMinutes($twoHours->duration_minutes),
            ],
        );
    }
}
