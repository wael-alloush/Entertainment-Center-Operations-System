<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Adjust timestamps stored while APP_TIMEZONE was UTC to Asia/Riyadh local time.
     */
    public function up(): void
    {
        $this->shiftDateTimeColumns('invoices', ['entered_at', 'expected_exit_at', 'exited_at']);
        $this->shiftDateTimeColumns('invoice_extensions', ['applied_at']);
        $this->shiftDateTimeColumns('attendance_records', ['checked_in_at', 'checked_out_at']);
        $this->shiftDateTimeColumns('customers', ['first_visit_at', 'last_visit_at']);
    }

    public function down(): void
    {
        $this->shiftDateTimeColumns('invoices', ['entered_at', 'expected_exit_at', 'exited_at'], -3);
        $this->shiftDateTimeColumns('invoice_extensions', ['applied_at'], -3);
        $this->shiftDateTimeColumns('attendance_records', ['checked_in_at', 'checked_out_at'], -3);
        $this->shiftDateTimeColumns('customers', ['first_visit_at', 'last_visit_at'], -3);
    }

    /**
     * @param  list<string>  $columns
     */
    private function shiftDateTimeColumns(string $table, array $columns, int $hours = 3): void
    {
        $driver = DB::connection()->getDriverName();

        foreach ($columns as $column) {
            if ($driver === 'sqlite') {
                $operator = $hours >= 0 ? '+' : '-';
                $amount = abs($hours);

                DB::statement("
                    UPDATE {$table}
                    SET {$column} = datetime({$column}, '{$operator}{$amount} hours')
                    WHERE {$column} IS NOT NULL
                ");

                continue;
            }

            $interval = $hours >= 0 ? "INTERVAL {$hours} HOUR" : 'INTERVAL '.abs($hours).' HOUR';
            $function = $hours >= 0 ? 'DATE_ADD' : 'DATE_SUB';

            DB::statement("
                UPDATE {$table}
                SET {$column} = {$function}({$column}, {$interval})
                WHERE {$column} IS NOT NULL
            ");
        }
    }
};
