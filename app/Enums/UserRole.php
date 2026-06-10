<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Reception = 'reception';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'مدير النظام',
            self::Reception => 'موظف استقبال',
        };
    }
}
