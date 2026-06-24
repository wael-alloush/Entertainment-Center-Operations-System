<?php

namespace App\Services;

use App\Models\DeviceRegistrationCode;
use App\Models\Setting;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Cookie;

class DeviceTrustService
{
    public const COOKIE_NAME = 'buds_device';

    private const CODE_TTL_MINUTES = 15;

    private const ACTIVE_WITHIN_MINUTES = 5;

    /**
     * @return array{enabled: bool}
     */
    public function settings(): array
    {
        $settings = Setting::getValue('device_trust', ['enabled' => false]);

        return [
            'enabled' => (bool) ($settings['enabled'] ?? false),
        ];
    }

    /**
     * @return array{enabled: bool}
     */
    public function attendanceSettings(): array
    {
        $settings = Setting::getValue('attendance_device_restriction', ['enabled' => false]);

        return [
            'enabled' => (bool) ($settings['enabled'] ?? false),
        ];
    }

    public function isEnabled(): bool
    {
        return $this->settings()['enabled'];
    }

    public function isAttendanceRestrictionEnabled(): bool
    {
        return $this->attendanceSettings()['enabled'];
    }

    public function setEnabled(bool $enabled): void
    {
        Setting::setValue('device_trust', ['enabled' => $enabled]);
    }

    public function setAttendanceRestrictionEnabled(bool $enabled): void
    {
        Setting::setValue('attendance_device_restriction', ['enabled' => $enabled]);
    }

    public function resolveToken(Request $request): string
    {
        $token = $request->cookie(self::COOKIE_NAME);

        if (is_string($token) && Str::isUuid($token)) {
            return $token;
        }

        return (string) Str::uuid();
    }

    public function attachDeviceCookie(string $token): Cookie
    {
        return cookie(
            name: self::COOKIE_NAME,
            value: $token,
            minutes: 60 * 24 * 365 * 2,
            path: '/',
            secure: app()->isProduction(),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }

    public function isDeviceTrusted(Request $request): bool
    {
        $token = $request->cookie(self::COOKIE_NAME);

        if (! is_string($token) || ! Str::isUuid($token)) {
            return false;
        }

        return TrustedDevice::query()
            ->where('token_hash', $this->hashToken($token))
            ->where('is_active', true)
            ->exists();
    }

    public function findTrustedDevice(Request $request): ?TrustedDevice
    {
        $token = $request->cookie(self::COOKIE_NAME);

        if (! is_string($token) || ! Str::isUuid($token)) {
            return null;
        }

        return TrustedDevice::query()
            ->where('token_hash', $this->hashToken($token))
            ->where('is_active', true)
            ->first();
    }

    public function canRecordAttendance(Request $request, bool $isAdmin = false): bool
    {
        if (! $this->isAttendanceRestrictionEnabled()) {
            return true;
        }

        if ($isAdmin) {
            return true;
        }

        $device = $this->findTrustedDevice($request);

        return $device !== null && $device->allows_attendance;
    }

    public function touchDevice(Request $request): void
    {
        $token = $request->cookie(self::COOKIE_NAME);

        if (! is_string($token) || ! Str::isUuid($token)) {
            return;
        }

        TrustedDevice::query()
            ->where('token_hash', $this->hashToken($token))
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('last_used_at')
                    ->orWhere('last_used_at', '<', now()->subMinute());
            })
            ->update(['last_used_at' => now()]);
    }

    public function isCurrentlyActive(?Carbon $lastUsedAt): bool
    {
        if ($lastUsedAt === null) {
            return false;
        }

        return $lastUsedAt->greaterThanOrEqualTo(now()->subMinutes(self::ACTIVE_WITHIN_MINUTES));
    }

    /**
     * @return array{plain_code: string, expires_at: string}
     */
    public function generateRegistrationCode(User $admin, ?string $label = null): array
    {
        $plainCode = $this->makePlainCode();
        $expiresAt = now()->addMinutes(self::CODE_TTL_MINUTES);

        DeviceRegistrationCode::query()->create([
            'code_hash' => $this->hashCode($plainCode),
            'label' => $label,
            'expires_at' => $expiresAt,
            'created_by' => $admin->id,
        ]);

        return [
            'plain_code' => $plainCode,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function redeemRegistrationCode(Request $request, string $plainCode, string $deviceName): TrustedDevice
    {
        $normalizedCode = $this->normalizeCode($plainCode);

        if ($normalizedCode === '') {
            throw ValidationException::withMessages([
                'code' => 'رمز التوثيق غير صالح.',
            ]);
        }

        $registrationCode = DeviceRegistrationCode::query()
            ->where('code_hash', $this->hashCode($normalizedCode))
            ->first();

        if ($registrationCode === null) {
            throw ValidationException::withMessages([
                'code' => 'رمز التوثيق غير صحيح.',
            ]);
        }

        if ($registrationCode->isUsed()) {
            throw ValidationException::withMessages([
                'code' => 'تم استخدام هذا الرمز مسبقاً.',
            ]);
        }

        if ($registrationCode->isExpired()) {
            throw ValidationException::withMessages([
                'code' => 'انتهت صلاحية رمز التوثيق. اطلب رمزاً جديداً من المدير.',
            ]);
        }

        $token = $this->resolveToken($request);
        $tokenHash = $this->hashToken($token);

        if (TrustedDevice::query()->where('token_hash', $tokenHash)->exists()) {
            throw ValidationException::withMessages([
                'code' => 'هذا الجهاز موثّق مسبقاً.',
            ]);
        }

        $device = TrustedDevice::query()->create([
            'name' => $deviceName,
            'token_hash' => $tokenHash,
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'last_used_at' => now(),
            'is_active' => true,
            'registration_code_id' => $registrationCode->id,
            'created_by' => $registrationCode->created_by,
        ]);

        $registrationCode->update(['used_at' => now()]);

        return $device;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function formatTrustedDevices(): array
    {
        return TrustedDevice::query()
            ->with('creator:id,name')
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (TrustedDevice $device): array => [
                'id' => $device->id,
                'name' => $device->name,
                'user_agent' => $device->user_agent,
                'is_active' => $device->is_active,
                'allows_attendance' => $device->allows_attendance,
                'is_currently_active' => $this->isCurrentlyActive($device->last_used_at),
                'last_used_at' => $device->last_used_at?->toIso8601String(),
                'created_at' => $device->created_at?->toIso8601String(),
                'created_by_name' => $device->creator?->name,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function formatPendingCodes(): array
    {
        return DeviceRegistrationCode::query()
            ->with('creator:id,name')
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DeviceRegistrationCode $code): array => [
                'id' => $code->id,
                'label' => $code->label,
                'expires_at' => $code->expires_at->toIso8601String(),
                'created_at' => $code->created_at?->toIso8601String(),
                'created_by_name' => $code->creator?->name,
            ])
            ->all();
    }

    private function makePlainCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        $segment = static function () use ($alphabet): string {
            $result = '';

            for ($index = 0; $index < 4; $index++) {
                $result .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            return $result;
        };

        return $segment().'-'.$segment();
    }

    private function normalizeCode(string $code): string
    {
        return strtoupper(str_replace([' ', '-'], '', trim($code)));
    }

    private function hashCode(string $code): string
    {
        return hash('sha256', $this->normalizeCode($code));
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
