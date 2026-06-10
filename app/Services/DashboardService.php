<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SessionDuration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $today = now()->startOfDay();

        $activeStats = Invoice::query()
            ->active()
            ->selectRaw('COUNT(*) as invoice_count, COALESCE(SUM(children_count), 0) as children_count')
            ->first();

        $completedToday = Invoice::query()
            ->completed()
            ->whereDate('exited_at', '>=', $today)
            ->count();

        $completedMonth = Invoice::query()
            ->completed()
            ->whereMonth('exited_at', now()->month)
            ->whereYear('exited_at', now()->year)
            ->count();

        return [
            'active_invoices' => (int) ($activeStats->invoice_count ?? 0),
            'active_children' => (int) ($activeStats->children_count ?? 0),
            'completed_today' => $completedToday,
            'completed_month' => $completedMonth,
            'total_customers' => Customer::query()->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function monthlyChartData(): array
    {
        $durations = SessionDuration::activeList(['id', 'name', 'color']);
        $monthExpression = $this->monthExpression('exited_at');

        $counts = Invoice::query()
            ->completed()
            ->whereYear('exited_at', now()->year)
            ->selectRaw("{$monthExpression} as chart_month, session_duration_id, COUNT(*) as aggregate")
            ->groupByRaw("{$monthExpression}, session_duration_id")
            ->get()
            ->groupBy('chart_month');

        return collect(range(1, 12))->map(function (int $month) use ($durations, $counts): array {
            $row = [
                'month' => Carbon::create(null, $month)->translatedFormat('M'),
            ];

            $monthCounts = $counts->get($month, collect())->keyBy('session_duration_id');

            foreach ($durations as $duration) {
                $row['duration_'.$duration->id] = (int) ($monthCounts->get($duration->id)?->aggregate ?? 0);
            }

            return $row;
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sessionDistribution(): array
    {
        $durations = SessionDuration::activeList(['id', 'name', 'color']);
        $counts = $this->sessionUsageCounts($durations);

        return $durations
            ->map(fn (SessionDuration $duration): array => [
                'id' => $duration->id,
                'name' => $duration->name,
                'color' => $duration->color,
                'value' => $counts->get($duration->id, 0),
                'key' => 'duration_'.$duration->id,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sessionDurationsForChart(): array
    {
        return SessionDuration::activeList(['id', 'name', 'color'])
            ->map(fn (SessionDuration $duration): array => [
                'id' => $duration->id,
                'name' => $duration->name,
                'color' => $duration->color,
                'key' => 'duration_'.$duration->id,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, SessionDuration>  $durations
     * @return Collection<int, int>
     */
    private function sessionUsageCounts(Collection $durations): Collection
    {
        $durationIds = $durations->pluck('id');

        $invoices = Invoice::query()
            ->where('status', '!=', InvoiceStatus::Cancelled)
            ->where(function ($query) use ($durationIds): void {
                $query->whereIn('session_duration_id', $durationIds)
                    ->orWhereHas('extensions', fn ($extensionQuery) => $extensionQuery->whereIn('session_duration_id', $durationIds));
            })
            ->with(['extensions' => fn ($query) => $query->select('id', 'invoice_id', 'session_duration_id')->whereIn('session_duration_id', $durationIds)])
            ->get(['id', 'session_duration_id']);

        return $durations->mapWithKeys(function (SessionDuration $duration) use ($invoices): array {
            $count = $invoices
                ->filter(function (Invoice $invoice) use ($duration): bool {
                    if ($invoice->session_duration_id === $duration->id) {
                        return true;
                    }

                    return $invoice->extensions->contains('session_duration_id', $duration->id);
                })
                ->count();

            return [$duration->id => $count];
        });
    }

    private function monthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "cast(strftime('%m', {$column}) as integer)",
            'pgsql' => "EXTRACT(MONTH FROM {$column})::integer",
            default => "MONTH({$column})",
        };
    }
}
