<?php

namespace App\Console\Commands;

use App\Services\BonusProgramService;
use Illuminate\Console\Command;

class ExpireCustomerBonusesCommand extends Command
{
    protected $signature = 'bonuses:expire';

    protected $description = 'Expire pending customer bonuses that passed their validity period';

    public function handle(BonusProgramService $bonusProgramService): int
    {
        $expiredCount = $bonusProgramService->expireOverdueBonusesIfNeeded();

        $this->info("Expired {$expiredCount} bonus(es).");

        return self::SUCCESS;
    }
}
