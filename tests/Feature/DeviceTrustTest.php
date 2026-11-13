<?php

use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\DeviceTrustService;
use Database\Seeders\EmployeeSeeder;
use Database\Seeders\SessionDurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([SessionDurationSeeder::class, EmployeeSeeder::class]);
    $this->admin = User::factory()->admin()->create();
    $this->reception = User::factory()->reception()->create();
    $this->deviceTrustService = app(DeviceTrustService::class);

    Setting::setValue('device_trust', ['enabled' => true]);
    Setting::setValue('work_schedule', ['start_time' => '23:00']);
});

function enableDeviceTrust(): void
{
    Setting::setValue('device_trust', ['enabled' => true]);
}

function disableDeviceTrust(): void
{
    Setting::setValue('device_trust', ['enabled' => false]);
}

function deviceCookie(?string $token = null): array
{
    return ['buds_device' => $token ?? (string) Str::uuid()];
}

function generateDeviceCode(User $admin, ?string $label = null): string
{
    test()->actingAs($admin)
        ->post(route('management.device-codes.store'), [
            'label' => $label,
        ])
        ->assertRedirect()
        ->assertSessionHas('generated_device_code');

    return (string) session('generated_device_code');
}

function trustDeviceWithCode(string $plainCode, string $deviceName, string $token): void
{
    test()->withCookie('buds_device', $token)
        ->post(route('device.register.store'), [
            'code' => $plainCode,
            'name' => $deviceName,
        ])
        ->assertRedirect();
}

it('generates and redeems a one-time device registration code', function (): void {
    $token = (string) Str::uuid();
    $plainCode = generateDeviceCode($this->admin, 'استقبال 1');

    trustDeviceWithCode($plainCode, 'استقبال 1', $token);

    expect(TrustedDevice::query()->count())->toBe(1);

    $this->withCookie('buds_device', $token)
        ->actingAs($this->reception)
        ->get(route('invoices.active'))
        ->assertOk();
});

it('blocks reception users on untrusted devices when device trust is enabled', function (): void {
    $this->actingAs($this->reception)
        ->get(route('invoices.active'))
        ->assertRedirect(route('device.register'))
        ->assertSessionMissing('error');
});

it('blocks reception invoice updates on untrusted devices when device trust is enabled', function (): void {
    $invoice = Invoice::factory()->active()->create(['children_count' => 2]);

    $this->actingAs($this->reception)
        ->patchJson(route('invoices.update', $invoice), [
            'children_count' => 3,
        ])
        ->assertForbidden();
});

it('does not offer passkey restore when no passkeys are registered', function (): void {
    $this->actingAs($this->reception)
        ->get(route('device.register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/register-device')
            ->where('isAuthenticated', true)
            ->where('canAttemptPasskeyRestore', false));
});

it('allows admin users on untrusted devices when device trust is enabled', function (): void {
    $this->actingAs($this->admin)
        ->get(route('management.index'))
        ->assertOk();
});

it('allows reception users when device trust is disabled', function (): void {
    disableDeviceTrust();

    $this->actingAs($this->reception)
        ->get(route('invoices.active'))
        ->assertOk();
});

it('rejects expired and reused registration codes', function (): void {
    $token = (string) Str::uuid();
    $plainCode = generateDeviceCode($this->admin);

    trustDeviceWithCode($plainCode, 'جهاز 1', $token);

    $this->withCookie('buds_device', (string) Str::uuid())
        ->post(route('device.register.store'), [
            'code' => $plainCode,
            'name' => 'جهاز 2',
        ])
        ->assertSessionHasErrors('code');

    $expiredCode = generateDeviceCode($this->admin);
    $this->travel(20)->minutes();

    $this->withCookie('buds_device', (string) Str::uuid())
        ->post(route('device.register.store'), [
            'code' => $expiredCode,
            'name' => 'جهاز 3',
        ])
        ->assertSessionHasErrors('code');
});

it('shows trusted devices on the management page', function (): void {
    $token = (string) Str::uuid();
    $plainCode = generateDeviceCode($this->admin);
    trustDeviceWithCode($plainCode, 'تابلت', $token);

    $this->actingAs($this->admin)
        ->get(route('management.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('management/index')
            ->where('deviceTrust.enabled', true)
            ->has('trustedDevices', 1)
            ->where('trustedDevices.0.name', 'تابلت'));
});

function enableAttendanceDeviceRestriction(): void
{
    Setting::setValue('attendance_device_restriction', ['enabled' => true]);
}

function trustAttendanceDevice(User $admin, string $deviceName = 'جهاز حضور'): string
{
    $token = (string) Str::uuid();
    $plainCode = generateDeviceCode($admin, $deviceName);
    trustDeviceWithCode($plainCode, $deviceName, $token);

    $device = TrustedDevice::query()->firstOrFail();

    test()->actingAs($admin)
        ->patch(route('management.trusted-devices.update', $device), [
            'name' => $deviceName,
            'is_active' => true,
            'allows_attendance' => true,
        ])
        ->assertRedirect();

    return $token;
}

it('restricts attendance recording to approved devices', function (): void {
    enableAttendanceDeviceRestriction();

    $employee = Employee::query()->firstOrFail();
    $attendanceToken = trustAttendanceDevice($this->admin);

    $otherToken = (string) Str::uuid();
    $plainCode = generateDeviceCode($this->admin, 'جهاز بدون حضور');
    trustDeviceWithCode($plainCode, 'جهاز بدون حضور', $otherToken);

    $this->withCookie('buds_device', $otherToken)
        ->actingAs($this->reception)
        ->get(route('employees.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canRecordAttendance', false)
            ->where('attendanceDeviceRestrictionEnabled', true));

    $this->withCookie('buds_device', $otherToken)
        ->actingAs($this->reception)
        ->post(route('employees.check-in', $employee))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->withCookie('buds_device', $attendanceToken)
        ->actingAs($this->reception)
        ->post(route('employees.check-in', $employee))
        ->assertRedirect()
        ->assertSessionHas('success');
});

it('allows admin to record attendance from any device when restriction is enabled', function (): void {
    enableAttendanceDeviceRestriction();

    $employee = Employee::query()->firstOrFail();

    $this->withCookie('buds_device', (string) Str::uuid())
        ->actingAs($this->admin)
        ->post(route('employees.check-in', $employee))
        ->assertRedirect()
        ->assertSessionHas('success');
});

it('marks trusted devices as currently active based on recent usage', function (): void {
    $token = (string) Str::uuid();
    $plainCode = generateDeviceCode($this->admin);
    trustDeviceWithCode($plainCode, 'جهاز نشط', $token);

    $device = TrustedDevice::query()->firstOrFail();
    $device->update(['last_used_at' => now()->subMinutes(2)]);

    $this->actingAs($this->admin)
        ->get(route('management.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('trustedDevices.0.is_currently_active', true));

    $device->update(['last_used_at' => now()->subMinutes(10)]);

    $this->actingAs($this->admin)
        ->get(route('management.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('trustedDevices.0.is_currently_active', false));
});

it('can enable and disable device trust from management', function (): void {
    $this->actingAs($this->admin)
        ->patch(route('management.device-trust.update'), [
            'enabled' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Setting::getValue('device_trust'))->toBe(['enabled' => false]);
});

it('normalizes webauthn origins without default https port', function (): void {
    $service = app(DeviceTrustService::class);

    $request = Request::create(
        'https://example.com/device/webauthn/register',
        'POST',
        [],
        [],
        [],
        [
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'example.com',
            'HTTP_X_FORWARDED_PORT' => '443',
        ],
    );

    expect($service->getWebAuthnOrigin($request))->toBe('https://example.com')
        ->and($service->webAuthnAllowedOrigins($request))->toContain('https://example.com');
});

it('requires credential payload for webauthn registration', function (): void {
    $token = (string) Str::uuid();
    $plainCode = generateDeviceCode($this->admin, 'استقبال WebAuthn');

    $this->withCookie('buds_device', $token)
        ->actingAs($this->reception)
        ->post(route('device.webauthn.register'), [
            'code' => $plainCode,
            'name' => 'استقبال WebAuthn',
        ])
        ->assertSessionHasErrors('credential');

    expect(TrustedDevice::query()->count())->toBe(0);
});

it('shows device registration page for untrusted devices', function (): void {
    TrustedDevice::query()->create([
        'name' => 'استقبال Passkey',
        'token_hash' => hash('sha256', (string) Str::uuid()),
        'user_agent' => 'Test Agent',
        'is_active' => true,
        'allows_attendance' => false,
        'authenticator_type' => 'webauthn',
        'credential_id' => 'test-credential-id',
        'public_key' => 'test-public-key',
        'credential_counter' => 0,
        'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->reception)
        ->get(route('device.register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/register-device')
            ->where('isAuthenticated', true)
            ->where('canAttemptPasskeyRestore', true));

    $this->actingAs($this->reception)
        ->get(route('device.webauthn.assertion-options'))
        ->assertOk()
        ->assertJsonStructure(['challenge', 'rpId', 'allowCredentials']);
});

it('requires credential payload for webauthn restore', function (): void {
    TrustedDevice::query()->create([
        'name' => 'استقبال Passkey',
        'token_hash' => hash('sha256', (string) Str::uuid()),
        'user_agent' => 'Test Agent',
        'is_active' => true,
        'allows_attendance' => false,
        'authenticator_type' => 'webauthn',
        'credential_id' => 'test-credential-id',
        'public_key' => 'test-public-key',
        'credential_counter' => 0,
        'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->reception)
        ->post(route('device.webauthn.restore'))
        ->assertSessionHasErrors('credential');
});

it('reassigns device token after webauthn restore helper updates token hash', function (): void {
    $oldToken = (string) Str::uuid();
    $newToken = (string) Str::uuid();

    $device = TrustedDevice::query()->create([
        'name' => 'استقبال Passkey',
        'token_hash' => hash('sha256', $oldToken),
        'user_agent' => 'Test Agent',
        'is_active' => true,
        'allows_attendance' => false,
        'authenticator_type' => 'webauthn',
        'credential_id' => 'test-credential-id',
        'public_key' => 'test-public-key',
        'credential_counter' => 0,
        'created_by' => $this->admin->id,
    ]);

    $device->update([
        'token_hash' => hash('sha256', $newToken),
        'last_verified_at' => now(),
        'last_used_at' => now(),
    ]);

    $request = Request::create('/', 'GET', [], ['buds_device' => $newToken]);

    expect($this->deviceTrustService->isDeviceTrusted($request))->toBeTrue();
});
