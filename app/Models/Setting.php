<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        return $setting?->value ?? $default;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public static function setValue(string $key, array $value): Setting
    {
        $setting = static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );

        if ($key === 'work_schedule') {
            static::flushWorkScheduleCache();
        }

        return $setting;
    }

    public static function flushWorkScheduleCache(): void
    {
        \Illuminate\Support\Facades\Cache::forget('settings.work_schedule');
    }
}
