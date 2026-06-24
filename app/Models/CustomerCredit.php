<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCredit extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'invoice_id',
        'children_count',
        'session_duration_id',
        'display_number',
        'counts_as_visit',
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
            'counts_as_visit' => 'boolean',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sessionDuration(): BelongsTo
    {
        return $this->belongsTo(SessionDuration::class);
    }

    /**
     * @param  Builder<CustomerCredit>  $query
     * @return Builder<CustomerCredit>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query
            ->whereNull('display_number')
            ->whereNull('invoice_id');
    }
}
