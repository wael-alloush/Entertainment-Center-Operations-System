<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustedDevice extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'token_hash',
        'user_agent',
        'last_used_at',
        'is_active',
        'allows_attendance',
        'registration_code_id',
        'created_by',
        'authenticator_type',
        'credential_id',
        'public_key',
        'webauthn_user_handle',
        'aaguid',
        'transports',
        'credential_counter',
        'device_identifier',
        'last_verified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'allows_attendance' => 'boolean',
            'credential_counter' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function registrationCode(): BelongsTo
    {
        return $this->belongsTo(DeviceRegistrationCode::class, 'registration_code_id');
    }
}
