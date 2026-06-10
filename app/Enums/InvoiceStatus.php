<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'نشطة',
            self::Completed => 'مكتملة',
            self::Cancelled => 'ملغاة',
        };
    }
}
