<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    /** @use HasFactory<\Database\Factories\InvoiceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'invoice_number',
        'customer_id',
        'phone_number',
        'children_count',
        'session_duration_id',
        'status',
        'needs_parent_call',
        'parent_call_marked_at',
        'parent_call_marked_by',
        'entered_at',
        'expected_exit_at',
        'exited_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'needs_parent_call' => 'boolean',
            'parent_call_marked_at' => 'datetime',
            'children_count' => 'integer',
            'entered_at' => 'datetime',
            'expected_exit_at' => 'datetime',
            'exited_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sessionDuration(): BelongsTo
    {
        return $this->belongsTo(SessionDuration::class);
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(InvoiceExtension::class)->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parentCallMarker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_call_marked_by');
    }

    public function bonuses(): HasMany
    {
        return $this->hasMany(CustomerBonus::class);
    }

    /**
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', InvoiceStatus::Active);
    }

    /**
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', InvoiceStatus::Completed);
    }

    /**
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || $term === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('invoice_number', 'like', "%{$term}%")
                ->orWhere('phone_number', 'like', "%{$term}%");
        });
    }

    /**
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    public function scopeForSessionDuration(Builder $query, ?int $sessionDurationId): Builder
    {
        if ($sessionDurationId === null) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($sessionDurationId): void {
            $query->where('session_duration_id', $sessionDurationId)
                ->orWhereHas('extensions', function (Builder $query) use ($sessionDurationId): void {
                    $query->where('session_duration_id', $sessionDurationId);
                });
        });
    }

    /**
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    public function scopeEnteredBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from !== null) {
            $query->whereDate('entered_at', '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate('entered_at', '<=', $to);
        }

        return $query;
    }
}
