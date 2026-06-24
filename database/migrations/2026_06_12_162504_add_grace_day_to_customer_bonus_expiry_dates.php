<?php

use App\Models\CustomerBonus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        CustomerBonus::query()
            ->whereNotNull('expires_at')
            ->orderBy('id')
            ->chunkById(100, function ($bonuses): void {
                foreach ($bonuses as $bonus) {
                    DB::table('customer_bonuses')
                        ->where('id', $bonus->id)
                        ->update([
                            'expires_at' => $bonus->expires_at->addDay(),
                            'expired_at' => $bonus->expired_at?->addDay(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        CustomerBonus::query()
            ->whereNotNull('expires_at')
            ->orderBy('id')
            ->chunkById(100, function ($bonuses): void {
                foreach ($bonuses as $bonus) {
                    DB::table('customer_bonuses')
                        ->where('id', $bonus->id)
                        ->update([
                            'expires_at' => $bonus->expires_at->subDay(),
                            'expired_at' => $bonus->expired_at?->subDay(),
                        ]);
                }
            });
    }
};
