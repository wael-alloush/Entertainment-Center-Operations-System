<?php

namespace App\Console\Commands;

use App\Services\LegacyImportService;
use Illuminate\Console\Command;

class ImportLegacyEntertainmentCommand extends Command
{
    protected $signature = 'legacy:import
                            {--sql= : Path to the legacy MySQL dump to load into the legacy database}
                            {--fresh : Clear imported customers, invoices, bonuses, and credits before importing}';

    protected $description = 'Import data from the legacy entertainment system into buds';

    public function handle(LegacyImportService $importService): int
    {
        $importService->setOutput($this->output);

        $sqlPath = $this->option('sql');

        if (is_string($sqlPath) && $sqlPath !== '') {
            $this->info("Loading SQL dump: {$sqlPath}");
            $importService->loadSqlDump($sqlPath);
        }

        $this->info('Starting legacy import...');

        try {
            $stats = $importService->import(
                fresh: (bool) $this->option('fresh'),
            );
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Entity', 'Count'],
            collect($stats)->map(fn (int $count, string $entity): array => [$entity, $count])->values()->all(),
        );

        $this->info('Legacy import completed.');

        return self::SUCCESS;
    }
}
