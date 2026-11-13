<?php

namespace App\Support;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;

class InvoiceAuthorization
{
    /**
     * @var list<string>
     */
    public const EDITABLE_FIELDS = [
        'invoice_number',
        'phone_number',
        'children_count',
    ];

    public static function canUpdateInvoice(?Invoice $invoice): bool
    {
        if ($invoice === null) {
            return false;
        }

        return $invoice->status !== InvoiceStatus::Cancelled;
    }

    public static function canUpdateField(?Invoice $invoice, string $field): bool
    {
        if (! self::canUpdateInvoice($invoice)) {
            return false;
        }

        if ($field === 'invoice_number') {
            return ! $invoice->is_credit && ! $invoice->is_bonus;
        }

        return in_array($field, self::EDITABLE_FIELDS, true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function filterAllowedFields(?Invoice $invoice, array $data): array
    {
        if ($invoice === null) {
            return [];
        }

        return array_filter(
            $data,
            fn (mixed $value, string $field): bool => self::canUpdateField($invoice, $field),
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
