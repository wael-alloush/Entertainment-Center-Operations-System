<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\CustomerBonus;
use App\Models\CustomerCredit;
use App\Models\Invoice;
use App\Models\InvoiceExtension;
use App\Models\SessionDuration;
use Carbon\Carbon;
use Database\Seeders\SessionDurationSeeder;
use Illuminate\Console\OutputStyle;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon as IlluminateCarbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class LegacyImportService
{
    public const BONUS_VALIDITY_DAYS = 30;

    /** @var array<int, int> */
    private array $legacyBillToInvoiceId = [];

    /** @var array<int, int> */
    private array $playTimeToSessionDurationId = [];

    private ?OutputStyle $output = null;

    public function setOutput(?OutputStyle $output): self
    {
        $this->output = $output;

        return $this;
    }

    public function loadSqlDump(string $path): void
    {
        if (! is_file($path)) {
            throw new \InvalidArgumentException("SQL dump not found: {$path}");
        }

        $connection = $this->legacyConnection();

        if ($connection->getDriverName() !== 'mysql') {
            throw new \RuntimeException('Loading SQL dumps requires the legacy connection to use MySQL.');
        }

        $database = $connection->getDatabaseName();
        $host = config('database.connections.legacy.host');
        $port = config('database.connections.legacy.port');
        $username = config('database.connections.legacy.username');
        $password = config('database.connections.legacy.password');

        $command = [
            $this->resolveMysqlBinary(),
            '-h', (string) $host,
            '-P', (string) $port,
            '-u', (string) $username,
            $database,
        ];

        $process = new Process($command, null, [
            'MYSQL_PWD' => (string) $password,
        ]);
        $process->setInput(file_get_contents($path) ?: '');
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Failed to load SQL dump: '.$process->getErrorOutput());
        }

        $this->line('Loaded SQL dump into legacy database.');
    }

    /**
     * @return array<string, int>
     */
    public function import(bool $fresh = false): array
    {
        $this->assertLegacyTablesExist();

        if ($fresh) {
            $this->clearImportedData();
        }

        $stats = [
            'customers' => 0,
            'invoices' => 0,
            'extensions' => 0,
            'pending_bonuses' => 0,
            'expired_bonuses' => 0,
            'credits' => 0,
            'skipped_used_rewards' => 0,
        ];

        $this->ensureSessionDurations();
        $this->buildPlayTimeMap();

        $stats['customers'] = $this->importCustomers();
        $stats['invoices'] = $this->importInvoices();
        $stats['extensions'] = $this->importExtensions();

        [$pendingBonuses, $expiredBonuses, $skippedUsedRewards] = $this->importBonuses();
        $stats['pending_bonuses'] = $pendingBonuses;
        $stats['expired_bonuses'] = $expiredBonuses;
        $stats['skipped_used_rewards'] = $skippedUsedRewards;

        $stats['credits'] = $this->importCredits();
        $this->refreshAllVisitCounts();
        $this->verifyImportCounts($stats);

        return $stats;
    }

    public function resolveEnteredAt(object $bill): Carbon
    {
        $entry = Carbon::parse((string) $bill->entry_time);
        $created = Carbon::parse((string) $bill->created_at);

        if (abs($entry->diffInHours($created)) >= 2) {
            return $created;
        }

        return $entry;
    }

    public function resolveExitedAt(object $bill): ?Carbon
    {
        if ((string) $bill->status !== 'completed' || $bill->exit_time === null) {
            return null;
        }

        $exit = Carbon::parse((string) $bill->exit_time);
        $updated = Carbon::parse((string) $bill->updated_at);

        if (abs($exit->diffInHours($updated)) >= 2) {
            return $updated;
        }

        return $exit;
    }

    public function extractDisplayNumber(string $billNumber): ?int
    {
        if (preg_match('/(\d+)/u', $billNumber, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    public function isBonusBill(object $bill): bool
    {
        return (bool) $bill->is_reward || str_contains((string) $bill->bill_number, 'مكافأة');
    }

    public function isCreditBill(object $bill): bool
    {
        return str_contains((string) $bill->bill_number, 'رصيد');
    }

    public function resolveLegacyBonusDates(Carbon|string $createdAt, ?Carbon $asOf = null): array
    {
        $created = $createdAt instanceof Carbon ? $createdAt : Carbon::parse((string) $createdAt);
        $bonusProgram = app(BonusProgramService::class);
        $expiresAt = $bonusProgram->calculateExpiresAt(IlluminateCarbon::parse($created));
        $reference = IlluminateCarbon::parse($asOf ?? now());

        $bonus = new CustomerBonus([
            'expires_at' => $expiresAt,
            'expired_at' => null,
        ]);

        $isExpired = $bonusProgram->shouldFinalizeBonus($bonus, $reference)
            && $bonusProgram->hasPassedDailyExpiryCheckTime($reference);

        return [
            'created_at' => $created,
            'expires_at' => $expiresAt,
            'expired_at' => $isExpired ? $bonusProgram->scheduledFinalizationAt($bonus) : null,
            'title' => $isExpired ? 'مكافأة منتهية' : 'مكافأة غير مفعلة',
        ];
    }

    public function sessionDurationIdForPlayTime(int $playTimeId, ?int $durationMinutes = null): int
    {
        if (isset($this->playTimeToSessionDurationId[$playTimeId])) {
            return $this->playTimeToSessionDurationId[$playTimeId];
        }

        return $this->sessionDurationIdForMinutes($durationMinutes ?? 0);
    }

    public function sessionDurationIdForMinutes(int $durationMinutes): int
    {
        if ($durationMinutes === 0) {
            $open = SessionDuration::query()->whereNull('duration_minutes')->first();

            if ($open !== null) {
                return $open->id;
            }
        }

        $match = SessionDuration::query()
            ->where('duration_minutes', $durationMinutes > 0 ? $durationMinutes : null)
            ->first();

        if ($match !== null) {
            return $match->id;
        }

        $created = SessionDuration::query()->create([
            'name' => "مدة {$durationMinutes} دقيقة",
            'duration_minutes' => $durationMinutes > 0 ? $durationMinutes : null,
            'color' => 'var(--muted-foreground)',
            'sort_order' => 99,
            'is_active' => false,
        ]);

        return $created->id;
    }

    private function legacyConnection(): Connection
    {
        return DB::connection('legacy');
    }

    private function resolveMysqlBinary(): string
    {
        $candidates = [
            'mysql',
            'C:\\xampp\\mysql\\bin\\mysql.exe',
            'C:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysql.exe',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === 'mysql') {
                return $candidate;
            }

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return 'mysql';
    }

    private function assertLegacyTablesExist(): void
    {
        if (! $this->legacyConnection()->getSchemaBuilder()->hasTable('bills')) {
            throw new \RuntimeException(
                'Legacy table "bills" not found. Import the SQL dump into the legacy database first (legacy:import --sql=path).',
            );
        }
    }

    private function clearImportedData(): void
    {
        $this->line('Clearing existing imported data...');

        DB::transaction(function (): void {
            InvoiceExtension::query()->delete();
            CustomerBonus::query()->delete();
            CustomerCredit::query()->delete();
            Invoice::query()->delete();
            Customer::query()->delete();
        });
    }

    private function ensureSessionDurations(): void
    {
        if (SessionDuration::query()->exists()) {
            return;
        }

        (new SessionDurationSeeder)->run();
    }

    private function buildPlayTimeMap(): void
    {
        $this->playTimeToSessionDurationId = [];

        $playTimes = $this->legacyConnection()->table('play_times')->orderBy('id')->get();

        foreach ($playTimes as $playTime) {
            $duration = (int) $playTime->duration;
            $this->playTimeToSessionDurationId[(int) $playTime->id] = $this->sessionDurationIdForMinutes($duration);
        }
    }

    private function importCustomers(): int
    {
        $phones = collect()
            ->merge($this->legacyConnection()->table('bills')->distinct()->pluck('phone_number'))
            ->merge($this->legacyConnection()->table('phone_rewards')->distinct()->pluck('phone_number'))
            ->merge($this->legacyConnection()->table('phone_credits')->distinct()->pluck('phone_number'))
            ->unique()
            ->filter()
            ->values();

        foreach ($phones as $phone) {
            Customer::query()->firstOrCreate(
                ['phone_number' => (string) $phone],
                ['visit_count' => 0],
            );
        }

        $count = $phones->count();
        $this->line("Ensured {$count} customer(s).");

        return $count;
    }

    private function importInvoices(): int
    {
        $this->legacyBillToInvoiceId = [];
        $imported = 0;

        $this->legacyConnection()
            ->table('bills')
            ->orderBy('id')
            ->chunk(200, function (Collection $bills) use (&$imported): void {
                DB::transaction(function () use ($bills, &$imported): void {
                    foreach ($bills as $bill) {
                        $customer = Customer::query()->firstOrCreate(
                            ['phone_number' => (string) $bill->phone_number],
                            ['visit_count' => 0],
                        );

                        $sessionDurationId = $this->resolveBillSessionDurationId($bill);
                        $enteredAt = $this->resolveEnteredAt($bill);
                        $exitedAt = $this->resolveExitedAt($bill);
                        $isBonus = $this->isBonusBill($bill);
                        $isCredit = $this->isCreditBill($bill);

                        $invoice = Invoice::query()->create([
                            'invoice_number' => (string) $bill->bill_number,
                            'customer_id' => $customer->id,
                            'phone_number' => (string) $bill->phone_number,
                            'children_count' => (int) $bill->children_count,
                            'session_duration_id' => $sessionDurationId,
                            'status' => (string) $bill->status === 'active'
                                ? InvoiceStatus::Active
                                : InvoiceStatus::Completed,
                            'is_bonus' => $isBonus,
                            'bonus_display_number' => $isBonus
                                ? $this->extractDisplayNumber((string) $bill->bill_number)
                                : null,
                            'is_credit' => $isCredit,
                            'credit_display_number' => $isCredit
                                ? $this->extractDisplayNumber((string) $bill->bill_number)
                                : null,
                            'counts_as_visit' => (bool) $bill->counts_visit,
                            'entered_at' => $enteredAt,
                            'expected_exit_at' => $this->calculateExpectedExitAt($bill, $sessionDurationId, $enteredAt),
                            'exited_at' => $exitedAt,
                        ]);

                        $this->legacyBillToInvoiceId[(int) $bill->id] = $invoice->id;
                        $imported++;
                    }
                });
            });

        $this->line("Imported {$imported} invoice(s).");

        return $imported;
    }

    private function resolveBillSessionDurationId(object $bill): int
    {
        $firstExtension = $this->legacyConnection()
            ->table('extensions')
            ->where('bill_id', $bill->id)
            ->orderBy('id')
            ->first();

        if ($firstExtension !== null) {
            return $this->sessionDurationIdForPlayTime(
                (int) $firstExtension->play_time_id,
                (int) $firstExtension->duration,
            );
        }

        return $this->sessionDurationIdForMinutes((int) ($bill->original_duration ?? 0));
    }

    private function calculateExpectedExitAt(object $bill, int $sessionDurationId, Carbon $enteredAt): ?Carbon
    {
        $sessionDuration = SessionDuration::query()->find($sessionDurationId);

        if ($sessionDuration === null) {
            return null;
        }

        $extensions = $this->legacyConnection()
            ->table('extensions')
            ->where('bill_id', $bill->id)
            ->orderBy('id')
            ->get();

        if ($extensions->isEmpty()) {
            return app(InvoiceService::class)->calculateExpectedExitAt($enteredAt, $sessionDuration);
        }

        $totalMinutes = $extensions->sum(fn (object $extension): int => (int) $extension->duration);

        if ($totalMinutes === 0) {
            return null;
        }

        return $enteredAt->copy()->addMinutes($totalMinutes);
    }

    private function importExtensions(): int
    {
        $imported = 0;
        $extensionsByBill = $this->legacyConnection()
            ->table('extensions')
            ->orderBy('id')
            ->get()
            ->groupBy('bill_id');

        DB::transaction(function () use ($extensionsByBill, &$imported): void {
            foreach ($this->legacyBillToInvoiceId as $legacyBillId => $invoiceId) {
                $billExtensions = $extensionsByBill->get($legacyBillId);

                if ($billExtensions === null) {
                    continue;
                }

                $additional = $billExtensions->sortBy('id')->values()->slice(1);

                foreach ($additional->values() as $index => $extension) {
                    InvoiceExtension::query()->create([
                        'invoice_id' => $invoiceId,
                        'session_duration_id' => $this->sessionDurationIdForPlayTime(
                            (int) $extension->play_time_id,
                            (int) $extension->duration,
                        ),
                        'sort_order' => $index + 1,
                    ]);
                    $imported++;
                }
            }
        });

        $this->line("Imported {$imported} extension(s).");

        return $imported;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function importBonuses(): array
    {
        if (! $this->legacyConnection()->getSchemaBuilder()->hasTable('phone_rewards')) {
            return [0, 0, 0];
        }

        $pending = 0;
        $expired = 0;
        $skippedUsed = 0;
        $rewards = $this->legacyConnection()->table('phone_rewards')->orderBy('id')->get();

        foreach ($rewards as $reward) {
            $customer = Customer::query()->firstOrCreate(
                ['phone_number' => (string) $reward->phone_number],
                ['visit_count' => 0],
            );

            if ((bool) $reward->is_used) {
                $skippedUsed++;

                continue;
            }

            $dates = $this->resolveLegacyBonusDates(Carbon::parse((string) $reward->created_at));

            $bonus = new CustomerBonus;
            $bonus->forceFill([
                'customer_id' => $customer->id,
                'invoice_id' => null,
                'source_invoice_id' => null,
                'children_count' => (int) $reward->children_count,
                'session_duration_id' => $this->sessionDurationIdForPlayTime((int) $reward->play_time_id),
                'display_number' => null,
                'expires_at' => $dates['expires_at'],
                'expired_at' => $dates['expired_at'],
                'title' => $dates['title'],
                'created_at' => $dates['created_at'],
                'updated_at' => $dates['expired_at'] ?? $dates['created_at'],
            ]);
            $bonus->save();

            if ($dates['expired_at'] !== null) {
                $expired++;
            } else {
                $pending++;
            }
        }

        $this->line("Imported {$pending} pending bonus(es) and {$expired} expired bonus(es), skipped {$skippedUsed} activated reward(s).");

        return [$pending, $expired, $skippedUsed];
    }

    private function importCredits(): int
    {
        if (! $this->legacyConnection()->getSchemaBuilder()->hasTable('phone_credits')) {
            return 0;
        }

        $imported = 0;
        $credits = $this->legacyConnection()->table('phone_credits')->orderBy('id')->get();

        foreach ($credits as $credit) {
            $customer = Customer::query()->firstOrCreate(
                ['phone_number' => (string) $credit->phone_number],
                ['visit_count' => 0],
            );

            $invoiceId = null;
            $displayNumber = null;
            $createdAt = Carbon::parse((string) $credit->created_at);

            if ((bool) $credit->is_used && $credit->used_at !== null) {
                $usedAt = Carbon::parse((string) $credit->used_at);
                $invoice = $this->findCreditInvoiceForActivation((string) $credit->phone_number, $usedAt);

                if ($invoice !== null) {
                    $invoiceId = $invoice->id;
                    $displayNumber = $invoice->credit_display_number;
                }
            }

            $creditModel = new CustomerCredit;
            $creditModel->forceFill([
                'customer_id' => $customer->id,
                'invoice_id' => $invoiceId,
                'children_count' => (int) $credit->children_count,
                'session_duration_id' => $this->sessionDurationIdForPlayTime((int) $credit->play_time_id),
                'display_number' => $displayNumber,
                'counts_as_visit' => (bool) $credit->counts_visit,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
            $creditModel->save();

            $imported++;
        }

        $this->line("Imported {$imported} credit record(s).");

        return $imported;
    }

    private function findCreditInvoiceForActivation(string $phoneNumber, Carbon $usedAt): ?Invoice
    {
        return Invoice::query()
            ->where('phone_number', $phoneNumber)
            ->where('is_credit', true)
            ->whereBetween('entered_at', [
                $usedAt->copy()->subMinutes(10),
                $usedAt->copy()->addMinutes(10),
            ])
            ->get()
            ->sortBy(fn (Invoice $invoice): int => (int) abs($invoice->entered_at->diffInSeconds($usedAt)))
            ->first();
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function verifyImportCounts(array $stats): void
    {
        $legacyPhones = collect()
            ->merge($this->legacyConnection()->table('bills')->distinct()->pluck('phone_number'))
            ->merge($this->legacyConnection()->table('phone_rewards')->distinct()->pluck('phone_number'))
            ->merge($this->legacyConnection()->table('phone_credits')->distinct()->pluck('phone_number'))
            ->unique()
            ->filter()
            ->count();

        $importedCustomers = Customer::query()->count();

        if ($legacyPhones !== $importedCustomers) {
            $this->line("Warning: legacy phones ({$legacyPhones}) != imported customers ({$importedCustomers}).");
        }

        $legacyBills = (int) $this->legacyConnection()->table('bills')->count();
        $importedInvoices = Invoice::query()->count();

        if ($legacyBills !== $importedInvoices) {
            $this->line("Warning: legacy bills ({$legacyBills}) != imported invoices ({$importedInvoices}).");
        }
    }

    private function refreshAllVisitCounts(): void
    {
        $invoiceService = app(InvoiceService::class);

        Customer::query()
            ->orderBy('id')
            ->pluck('phone_number')
            ->each(fn (string $phoneNumber) => $invoiceService->refreshStoredVisitCount($phoneNumber));

        $this->line('Refreshed visit counts for all customers.');
    }

    private function line(string $message): void
    {
        $this->output?->writeln($message);
    }
}
