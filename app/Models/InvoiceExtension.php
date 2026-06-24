<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceExtension extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'invoice_id',
        'session_duration_id',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function sessionDuration(): BelongsTo
    {
        return $this->belongsTo(SessionDuration::class);
    }
}
