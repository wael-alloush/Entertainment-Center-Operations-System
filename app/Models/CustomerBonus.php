<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerBonus extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'invoice_id',
        'source_invoice_id',
        'children_count',
        'session_duration_id',
        'display_number',
        'expires_at',
        'expired_at',
        'title',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'children_count' => 'integer',
            'display_number' => 'integer',
            'expires_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function sourceInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'source_invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sessionDuration(): BelongsTo
    {
        return $this->belongsTo(SessionDuration::class);
    }

    /**
     * @param  Builder<CustomerBonus>  $query
     * @return Builder<CustomerBonus>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query
            ->whereNull('display_number')
            ->whereNull('invoice_id')
            ->whereNull('expired_at');
    }
}
