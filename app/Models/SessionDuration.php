<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class SessionDuration extends Model
{
    /** @use HasFactory<\Database\Factories\SessionDurationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'duration_minutes',
        'color',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(InvoiceExtension::class);
    }

    public function isOpen(): bool
    {
        return $this->duration_minutes === null;
    }

    /**
     * @param  list<string>  $columns
     * @return Collection<int, SessionDuration>
     */
    public static function activeList(array $columns = ['id', 'name', 'color', 'duration_minutes']): Collection
    {
        $cacheKey = 'session_durations.active.'.implode('.', $columns);

        /** @var Collection<int, SessionDuration> */
        return Cache::remember($cacheKey, now()->addHour(), fn (): Collection => static::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get($columns));
    }

    public static function flushCache(): void
    {
        Cache::forget('session_durations.active.id.name.color.duration_minutes');
        Cache::forget('session_durations.active.id.name.color');
    }
}
