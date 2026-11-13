<?php

namespace App\Services;

use App\Models\DeviceRegistrationCode;
use App\Models\Setting;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\Exception\AuthenticatorResponseVerificationException;
use Webauthn\Exception\CounterException;
use Webauthn\Exception\InvalidDataException;
use Webauthn\Exception\InvalidUserHandleException;
use Webauthn\Exception\WebauthnException;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\EmptyTrustPath;

class DeviceTrustService
{
    private const WEBAUTHN_TIMEOUT_MS = 60000;

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

        $token = (string) Str::uuid();
        $request->cookies->set(self::COOKIE_NAME, $token);

        return $token;
    }

    public function attachDeviceCookie(string $token, ?Request $request = null): Cookie
    {
        $request ??= request();

        return cookie(
            name: self::COOKIE_NAME,
            value: $token,
            minutes: 60 * 24 * 365 * 2,
            path: '/',
            secure: $request->isSecure(),
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

    private function normalizeWebAuthnIdentifier(string $value): string
    {
        return strtolower(trim(preg_replace('/[^a-fA-F0-9]/', '', $value) ?? ''));
    }

    private function hashDeviceIdentifier(string $value): string
    {
        return hash('sha256', $this->normalizeWebAuthnIdentifier($value));
    }

    public function generateWebAuthnRegistrationOptions(User $user, ?string $deviceName = null, ?Request $request = null): array
    {
        $request ??= request();
        $challenge = $this->base64UrlEncode(random_bytes(32));

        session()->put('webauthn_registration_challenge', $challenge);

        return [
            'challenge' => $challenge,
            'timeout' => self::WEBAUTHN_TIMEOUT_MS,
            'attestation' => 'none',
            'rp' => [
                'name' => config('app.name', 'Buds'),
                'id' => $this->getWebAuthnRpId($request),
            ],
            'user' => [
                'id' => $this->base64UrlEncode((string) $user->getKey()),
                'name' => $user->email,
                'displayName' => $deviceName ?? $user->name,
            ],
            'pubKeyCredParams' => $this->webAuthnPublicKeyCredentialParameters(),
            'authenticatorSelection' => [
                'userVerification' => 'preferred',
                'residentKey' => 'discouraged',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $credentialPayload
     */
    public function verifyWebAuthnRegistration(Request $request, string $deviceName, ?array $credentialPayload = null): TrustedDevice
    {
        $challengeBase64Url = session('webauthn_registration_challenge');

        if (! is_string($challengeBase64Url) || $challengeBase64Url === '') {
            throw ValidationException::withMessages([
                'credential' => 'انتهت صلاحية جلسة التوثيق. أعد المحاولة.',
            ]);
        }

        $challenge = $this->base64UrlDecode($challengeBase64Url);

        if ($challenge === '') {
            throw ValidationException::withMessages([
                'credential' => 'انتهت صلاحية جلسة التوثيق. أعد المحاولة.',
            ]);
        }

        $user = $request->user();

        if ($user === null) {
            throw ValidationException::withMessages([
                'credential' => 'يجب أن تكون مسجلاً داخل التطبيق قبل توثيق الجهاز.',
            ]);
        }

        $rp = PublicKeyCredentialRpEntity::create(config('app.name', 'Buds'), $this->getWebAuthnRpId($request));
        $userEntity = PublicKeyCredentialUserEntity::create(
            $user->email,
            (string) $user->getKey(),
            $deviceName,
        );

        $creationOptions = PublicKeyCredentialCreationOptions::create(
            $rp,
            $userEntity,
            $challenge,
            array_map(
                fn (array $parameter): PublicKeyCredentialParameters => PublicKeyCredentialParameters::create(
                    $parameter['type'],
                    $parameter['alg'],
                ),
                $this->webAuthnPublicKeyCredentialParameters(),
            ),
            new AuthenticatorSelectionCriteria(
                null,
                AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
                AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_DISCOURAGED,
            ),
            'none',
            [],
            self::WEBAUTHN_TIMEOUT_MS,
        );

        $credentialPayload ??= $request->input('credential');

        if (! is_array($credentialPayload)) {
            throw ValidationException::withMessages([
                'credential' => 'بيانات Passkey غير صالحة. أعد المحاولة.',
            ]);
        }

        try {
            $publicKeyCredential = $this->deserializePublicKeyCredential($credentialPayload);
            $response = $publicKeyCredential->response;

            if (! $response instanceof AuthenticatorAttestationResponse) {
                throw ValidationException::withMessages([
                    'credential' => 'تعذر إكمال إعداد Passkey على هذا الجهاز.',
                ]);
            }

            $validator = AuthenticatorAttestationResponseValidator::create(
                $this->configureCeremonyStepFactory($request)->creationCeremony(),
            );
            $credentialRecord = $validator->check(
                $response,
                $creationOptions,
                $this->getWebAuthnRpId($request),
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (AuthenticatorResponseVerificationException $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'credential' => $this->webAuthnVerificationErrorMessage($exception),
            ]);
        } catch (WebauthnException $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'credential' => $this->webAuthnProcessingErrorMessage($exception),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'credential' => $this->webAuthnProcessingErrorMessage($exception),
            ]);
        }

        $token = $this->resolveToken($request);
        $device = TrustedDevice::query()
            ->where('token_hash', $this->hashToken($token))
            ->first();

        if ($device === null) {
            $userAgent = Str::limit((string) $request->userAgent(), 500, '');

            $device = TrustedDevice::query()->create([
                'name' => $deviceName,
                'token_hash' => $this->hashToken($token),
                'user_agent' => $userAgent,
                'created_by' => $user->id,
                'is_active' => true,
                'allows_attendance' => false,
            ]);
        }

        $this->markDeviceAsWebAuthnTrusted(
            $device,
            $this->base64UrlEncode($credentialRecord->publicKeyCredentialId),
            $this->base64UrlEncode($credentialRecord->credentialPublicKey),
            $credentialRecord->aaguid->toRfc4122(),
            implode(',', $credentialRecord->transports),
            $credentialRecord->counter,
            $credentialRecord->userHandle,
        );

        $device->refresh();
        session()->forget('webauthn_registration_challenge');

        return $device;
    }

    private function markDeviceAsWebAuthnTrusted(TrustedDevice $device, string $credentialId, string $publicKey, ?string $aaguid = null, ?string $transports = null, int $counter = 0, ?string $userHandle = null): void
    {
        $attributes = [
            'authenticator_type' => 'webauthn',
            'credential_id' => $credentialId,
            'public_key' => $publicKey,
            'aaguid' => $aaguid,
            'transports' => $transports,
            'credential_counter' => $counter,
            'device_identifier' => $this->hashDeviceIdentifier($credentialId),
            'last_verified_at' => now(),
            'last_used_at' => now(),
            'is_active' => true,
        ];

        if ($userHandle !== null && $userHandle !== '' && Schema::hasColumn($device->getTable(), 'webauthn_user_handle')) {
            $attributes['webauthn_user_handle'] = $this->base64UrlEncode($userHandle);
        }

        $device->update($attributes);
    }

    public function hasRegisteredWebAuthnDevices(): bool
    {
        return $this->activeWebAuthnDevices()->isNotEmpty();
    }

    /**
     * @return array{challenge: string, timeout: int, rpId: string, allowCredentials: list<array{type: string, id: string, transports: list<string>}>, userVerification: string}
     */
    public function generateWebAuthnAssertionOptions(?Request $request = null): array
    {
        $request ??= request();

        if (! $this->hasRegisteredWebAuthnDevices()) {
            throw ValidationException::withMessages([
                'credential' => 'لا يوجد Passkey مسجّل.',
            ]);
        }

        $challenge = $this->base64UrlEncode(random_bytes(32));
        session()->put('webauthn_assertion_challenge', $challenge);

        return [
            'challenge' => $challenge,
            'timeout' => self::WEBAUTHN_TIMEOUT_MS,
            'rpId' => $this->getWebAuthnRpId($request),
            'allowCredentials' => $this->webAuthnAllowCredentialsForAssertion(),
            'userVerification' => 'preferred',
        ];
    }

    /**
     * @param  array<string, mixed>  $credentialPayload
     */
    public function restoreDeviceTrustWithWebAuthn(Request $request, array $credentialPayload): TrustedDevice
    {
        $challengeBase64Url = session('webauthn_assertion_challenge');

        if (! is_string($challengeBase64Url) || $challengeBase64Url === '') {
            throw ValidationException::withMessages([
                'credential' => 'انتهت صلاحية الجلسة. أعد تحميل الصفحة ثم حاول مجدداً.',
            ]);
        }

        $challenge = $this->base64UrlDecode($challengeBase64Url);

        if ($challenge === '') {
            throw ValidationException::withMessages([
                'credential' => 'انتهت صلاحية الجلسة. أعد تحميل الصفحة ثم حاول مجدداً.',
            ]);
        }

        $credentialPayloadId = $credentialPayload['id'] ?? $credentialPayload['rawId'] ?? null;

        if (! is_string($credentialPayloadId) || $credentialPayloadId === '') {
            throw ValidationException::withMessages([
                'credential' => 'بيانات Passkey غير صالحة. أعد المحاولة.',
            ]);
        }

        $device = $this->findTrustedDeviceByCredentialId($credentialPayloadId);

        if ($device === null) {
            throw ValidationException::withMessages([
                'credential' => 'Passkey غير مرتبط بجهاز موثّق في النظام.',
            ]);
        }

        $credentialPayload = $this->normalizeWebAuthnAssertionPayload($credentialPayload);

        $requestOptions = PublicKeyCredentialRequestOptions::create(
            $challenge,
            rpId: $this->getWebAuthnRpId($request),
            allowCredentials: $this->webAuthnCredentialDescriptors(),
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            timeout: self::WEBAUTHN_TIMEOUT_MS,
        );

        try {
            $publicKeyCredential = $this->deserializePublicKeyCredential($credentialPayload);
            $response = $publicKeyCredential->response;

            if (! $response instanceof AuthenticatorAssertionResponse) {
                throw ValidationException::withMessages([
                    'credential' => 'تعذر التحقق من Passkey على هذا الجهاز.',
                ]);
            }

            $validator = AuthenticatorAssertionResponseValidator::create(
                $this->configureCeremonyStepFactory($request)->requestCeremony(),
            );
            $credentialRecord = $this->buildCredentialRecordFromDevice($device, $credentialPayload);
            $updatedCredential = $validator->check(
                $credentialRecord,
                $response,
                $requestOptions,
                $this->getWebAuthnRpId($request),
                null,
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (AuthenticatorResponseVerificationException $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'credential' => $this->webAuthnVerificationErrorMessage($exception, forRestore: true),
            ]);
        } catch (WebauthnException $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'credential' => $this->webAuthnProcessingErrorMessage($exception),
            ]);
        } catch (QueryException $exception) {
            report($exception);

            if (str_contains($exception->getMessage(), 'webauthn_user_handle')) {
                throw ValidationException::withMessages([
                    'credential' => 'تحديث قاعدة البيانات مطلوب. شغّل php artisan migrate على الخادم ثم أعد المحاولة.',
                ]);
            }

            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'credential' => $this->webAuthnProcessingErrorMessage($exception),
            ]);
        }

        $token = $request->cookie(self::COOKIE_NAME);

        if (! is_string($token) || ! Str::isUuid($token)) {
            $token = $this->resolveToken($request);
        }

        $device->update([
            'token_hash' => $this->hashToken($token),
            'credential_counter' => $updatedCredential->counter,
            'last_verified_at' => now(),
            'last_used_at' => now(),
            'is_active' => true,
        ]);

        $this->persistWebAuthnUserHandle($device, $updatedCredential->userHandle);

        session()->forget('webauthn_assertion_challenge');

        return $device->refresh();
    }

    private function findTrustedDeviceByCredentialId(string $credentialId): ?TrustedDevice
    {
        return TrustedDevice::query()
            ->where('is_active', true)
            ->where('authenticator_type', 'webauthn')
            ->where('credential_id', $credentialId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $credentialPayload
     */
    private function buildCredentialRecordFromDevice(TrustedDevice $device, array $credentialPayload = []): CredentialRecord
    {
        if (! is_string($device->credential_id) || ! is_string($device->public_key)) {
            throw ValidationException::withMessages([
                'credential' => 'بيانات Passkey المحفوظة لهذا الجهاز غير مكتملة.',
            ]);
        }

        $aaguid = $this->parseWebAuthnAaguid($device->aaguid);

        $credentialId = $this->decodeWebAuthnBinaryField($device->credential_id, 'معرّف Passkey');
        $publicKey = $this->decodeWebAuthnBinaryField($device->public_key, 'مفتاح Passkey');

        return CredentialRecord::create(
            publicKeyCredentialId: $credentialId,
            type: 'public-key',
            transports: $this->parseTransports($device->transports),
            attestationType: 'none',
            trustPath: EmptyTrustPath::create(),
            aaguid: $aaguid,
            credentialPublicKey: $publicKey,
            userHandle: $this->resolveWebAuthnUserHandle($device, $credentialPayload),
            counter: $device->credential_counter ?? 0,
        );
    }

    /**
     * @param  array<string, mixed>  $credentialPayload
     * @return array<string, mixed>
     */
    private function normalizeWebAuthnAssertionPayload(array $credentialPayload): array
    {
        $encodedId = $credentialPayload['rawId'] ?? $credentialPayload['id'] ?? null;

        if (! is_string($encodedId) || $encodedId === '') {
            throw ValidationException::withMessages([
                'credential' => 'بيانات Passkey غير صالحة. أعد المحاولة.',
            ]);
        }

        $credentialPayload['id'] = $encodedId;
        $credentialPayload['rawId'] = $encodedId;

        if (! isset($credentialPayload['type']) || ! is_string($credentialPayload['type'])) {
            $credentialPayload['type'] = 'public-key';
        }

        if (! is_array($credentialPayload['response'] ?? null)) {
            throw ValidationException::withMessages([
                'credential' => 'بيانات Passkey غير صالحة. أعد المحاولة.',
            ]);
        }

        /** @var array<string, mixed> $response */
        $response = $credentialPayload['response'];

        foreach (['clientDataJSON', 'authenticatorData', 'signature'] as $field) {
            if (! is_string($response[$field] ?? null) || $response[$field] === '') {
                throw ValidationException::withMessages([
                    'credential' => 'بيانات Passkey المرسلة من المتصفح ناقصة. أعد المحاولة.',
                ]);
            }
        }

        if (($response['userHandle'] ?? null) === '') {
            unset($response['userHandle']);
        }

        $credentialPayload['response'] = $response;

        return $credentialPayload;
    }

    private function decodeWebAuthnBinaryField(string $value, string $label): string
    {
        $decoded = $this->base64UrlDecode($value);

        if ($decoded === '') {
            throw ValidationException::withMessages([
                'credential' => "بيانات {$label} المحفوظة تالفة. اطلب رمز توثيق جديد من المدير لإعادة ربط الجهاز.",
            ]);
        }

        return $decoded;
    }

    private function parseWebAuthnAaguid(?string $aaguid): Uuid
    {
        if (is_string($aaguid) && $aaguid !== '') {
            try {
                return Uuid::fromString($aaguid);
            } catch (\Throwable) {
                // Fall back to the none attestation UUID below.
            }
        }

        return Uuid::fromString('00000000-0000-0000-0000-000000000000');
    }

    private function persistWebAuthnUserHandle(TrustedDevice $device, string $userHandle): void
    {
        if ($userHandle === '' || ! Schema::hasColumn($device->getTable(), 'webauthn_user_handle')) {
            return;
        }

        $device->update([
            'webauthn_user_handle' => $this->base64UrlEncode($userHandle),
        ]);
    }

    /**
     * @param  array<string, mixed>  $credentialPayload
     */
    private function resolveWebAuthnUserHandle(TrustedDevice $device, array $credentialPayload = []): string
    {
        if (is_string($device->webauthn_user_handle) && $device->webauthn_user_handle !== '') {
            return $this->base64UrlDecode($device->webauthn_user_handle);
        }

        $responseUserHandle = $credentialPayload['response']['userHandle'] ?? null;

        if (is_string($responseUserHandle) && $responseUserHandle !== '') {
            $decodedUserHandle = $this->base64UrlDecode($responseUserHandle);

            if ($decodedUserHandle !== '') {
                return $decodedUserHandle;
            }
        }

        throw ValidationException::withMessages([
            'credential' => 'بيانات Passkey القديمة ناقصة. اطلب رمز توثيق جديد من المدير لإعادة ربط الجهاز.',
        ]);
    }

    private function webAuthnProcessingErrorMessage(\Throwable $exception): string
    {
        if ($exception instanceof InvalidDataException) {
            $message = strtolower($exception->getMessage());

            if (str_contains($message, 'invalid id')) {
                return 'تعذر قراءة معرّف Passkey. أعد المحاولة أو أعد ربط الجهاز برمز توثيق جديد.';
            }

            return 'تعذر قراءة بيانات Passkey المرسلة من المتصفح. أعد تحميل الصفحة ثم حاول مجدداً.';
        }

        if ($exception instanceof CounterException) {
            return 'عداد Passkey غير متسق. اطلب رمز توثيق جديد من المدير لإعادة ربط الجهاز.';
        }

        if ($exception instanceof WebauthnException) {
            return 'تعذر معالجة بيانات Passkey. أعد المحاولة أو أعد ربط الجهاز برمز توثيق جديد.';
        }

        return 'تعذر معالجة Passkey. أعد المحاولة أو اطلب رمز توثيق جديد من المدير.';
    }

    private function webAuthnVerificationErrorMessage(AuthenticatorResponseVerificationException $exception, bool $forRestore = false): string
    {
        if ($exception instanceof InvalidUserHandleException) {
            return $forRestore
                ? 'تعذر التحقق من Passkey. أعد المحاولة.'
                : 'تعذر مطابقة Passkey مع سجل الجهاز. اطلب رمز توثيق جديد من المدير لإعادة ربط الجهاز.';
        }

        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'origin')) {
            return 'فشل التحقق من Passkey. تأكد أن APP_URL في .env يطابق عنوان الموقع الذي تفتحه (HTTPS ونفس النطاق).';
        }

        if (str_contains($message, 'challenge')) {
            return 'انتهت صلاحية الجلسة. أعد تحميل الصفحة ثم حاول مجدداً.';
        }

        if (str_contains($message, 'signature')) {
            return $forRestore
                ? 'تعذر التحقق من Passkey. أعد المحاولة.'
                : 'تعذر التحقق من توقيع Passkey. جرّب إعادة ربط الجهاز برمز توثيق جديد من المدير.';
        }

        return 'فشل التحقق من Passkey. تأكد أن APP_URL في .env يطابق عنوان الموقع الذي تفتحه (HTTPS ونفس النطاق).';
    }

    public function isCurrentlyActive(?Carbon $lastUsedAt): bool
    {
        if ($lastUsedAt === null) {
            return false;
        }

        return $lastUsedAt->greaterThanOrEqualTo(now()->subMinutes(self::ACTIVE_WITHIN_MINUTES));
    }

    public function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;

        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($value, '-_', '+/'), true) ?: '';
    }

    public function getWebAuthnOrigin(?Request $request = null): string
    {
        $request ??= request();

        $forwardedProto = $request->header('X-Forwarded-Proto');
        $forwardedHost = $request->header('X-Forwarded-Host');
        $forwardedPort = $request->header('X-Forwarded-Port');

        if (is_string($forwardedHost) && $forwardedHost !== '') {
            $host = trim(explode(',', $forwardedHost)[0]);
            $scheme = is_string($forwardedProto) && $forwardedProto !== ''
                ? trim(explode(',', $forwardedProto)[0])
                : ($request->isSecure() ? 'https' : 'http');
            $port = is_string($forwardedPort) && $forwardedPort !== ''
                ? (int) trim(explode(',', $forwardedPort)[0])
                : null;

            if ($host !== '') {
                return $this->normalizeWebAuthnOrigin($scheme, $host, $port);
            }
        }

        if ($request->getHost() !== '') {
            return $this->normalizeWebAuthnOrigin(
                $request->getScheme(),
                $request->getHost(),
                $request->getPort() ?: null,
            );
        }

        $appUrl = trim((string) config('app.url', 'http://localhost'));
        $parsed = parse_url($appUrl);

        if (! is_array($parsed) || ! isset($parsed['scheme'], $parsed['host'])) {
            return 'http://localhost';
        }

        return $this->normalizeWebAuthnOrigin(
            $parsed['scheme'],
            $parsed['host'],
            isset($parsed['port']) ? (int) $parsed['port'] : null,
        );
    }

    /**
     * @return list<string>
     */
    public function webAuthnAllowedOrigins(?Request $request = null): array
    {
        $request ??= request();

        $origins = array_values(array_unique(array_filter([
            $this->getWebAuthnOrigin($request),
            $this->originFromAppUrl(),
        ])));

        return $origins !== [] ? $origins : ['http://localhost'];
    }

    private function originFromAppUrl(): ?string
    {
        $appUrl = trim((string) config('app.url', ''));

        if ($appUrl === '') {
            return null;
        }

        $parsed = parse_url($appUrl);

        if (! is_array($parsed) || ! isset($parsed['scheme'], $parsed['host'])) {
            return null;
        }

        return $this->normalizeWebAuthnOrigin(
            $parsed['scheme'],
            $parsed['host'],
            isset($parsed['port']) ? (int) $parsed['port'] : null,
        );
    }

    private function normalizeWebAuthnOrigin(string $scheme, string $host, ?int $port = null): string
    {
        if ($port === 443 && $scheme === 'https') {
            $port = null;
        }

        if ($port === 80 && $scheme === 'http') {
            $port = null;
        }

        if ($port !== null) {
            return sprintf('%s://%s:%d', $scheme, $host, $port);
        }

        return sprintf('%s://%s', $scheme, $host);
    }

    public function getWebAuthnRpId(?Request $request = null): string
    {
        $origin = $this->getWebAuthnOrigin($request);
        $host = parse_url($origin, PHP_URL_HOST);

        return $host !== null && $host !== '' ? $host : 'localhost';
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

    /**
     * @param  array<string, mixed>  $credentialPayload
     */
    public function completeWebAuthnRegistration(Request $request, string $plainCode, string $deviceName, array $credentialPayload): TrustedDevice
    {
        $registrationCode = $this->findValidRegistrationCode($plainCode);
        $token = $this->resolveToken($request);

        if (TrustedDevice::query()->where('token_hash', $this->hashToken($token))->exists()) {
            throw ValidationException::withMessages([
                'code' => 'هذا المتصفح موثّق مسبقاً على هذا الجهاز.',
            ]);
        }

        $device = $this->verifyWebAuthnRegistration($request, $deviceName, $credentialPayload);

        $device->update([
            'registration_code_id' => $registrationCode->id,
            'created_by' => $registrationCode->created_by,
        ]);

        $registrationCode->update(['used_at' => now()]);

        return $device->refresh();
    }

    public function redeemRegistrationCode(Request $request, string $plainCode, string $deviceName): TrustedDevice
    {
        $registrationCode = $this->findValidRegistrationCode($plainCode);
        $token = $this->resolveToken($request);
        $tokenHash = $this->hashToken($token);

        if (TrustedDevice::query()->where('token_hash', $tokenHash)->exists()) {
            throw ValidationException::withMessages([
                'code' => 'هذا المتصفح موثّق مسبقاً على هذا الجهاز.',
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

    private function findValidRegistrationCode(string $plainCode): DeviceRegistrationCode
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

        return $registrationCode;
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

    /**
     * @return list<array{type: string, id: string, transports: list<string>}>
     */
    private function webAuthnAllowCredentialsForAssertion(): array
    {
        return $this->activeWebAuthnDevices()
            ->map(fn (TrustedDevice $device): array => [
                'type' => 'public-key',
                'id' => $device->credential_id,
                'transports' => $this->parseTransports($device->transports),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<PublicKeyCredentialDescriptor>
     */
    private function webAuthnCredentialDescriptors(): array
    {
        return $this->activeWebAuthnDevices()
            ->map(fn (TrustedDevice $device): PublicKeyCredentialDescriptor => PublicKeyCredentialDescriptor::create(
                'public-key',
                $this->base64UrlDecode($device->credential_id),
                $this->parseTransports($device->transports),
            ))
            ->all();
    }

    /**
     * @return list<string>
     */
    private function parseTransports(?string $transports): array
    {
        if (! is_string($transports) || $transports === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $transports))));
    }

    private function webAuthnPublicKeyCredentialParameters(): array
    {
        return [
            [
                'type' => 'public-key',
                'alg' => -7,
            ],
            [
                'type' => 'public-key',
                'alg' => -257,
            ],
        ];
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

    /**
     * @return Collection<int, TrustedDevice>
     */
    private function activeWebAuthnDevices(): Collection
    {
        return TrustedDevice::query()
            ->where('is_active', true)
            ->where('authenticator_type', 'webauthn')
            ->whereNotNull('credential_id')
            ->whereNotNull('public_key')
            ->get();
    }

    private function createWebAuthnSerializer(): SerializerInterface
    {
        return (new WebauthnSerializerFactory(
            new AttestationStatementSupportManager([
                new NoneAttestationStatementSupport,
            ])
        ))->create();
    }

    private function configureCeremonyStepFactory(?Request $request = null): CeremonyStepManagerFactory
    {
        $request ??= request();
        $factory = new CeremonyStepManagerFactory;
        $factory->setAllowedOrigins($this->webAuthnAllowedOrigins($request));

        if (app()->environment('local')) {
            $factory->setSecuredRelyingPartyId(['localhost', '127.0.0.1']);
        }

        return $factory;
    }

    /**
     * @param  array<string, mixed>  $credentialPayload
     */
    private function deserializePublicKeyCredential(array $credentialPayload): PublicKeyCredential
    {
        $publicKeyCredential = $this->createWebAuthnSerializer()->deserialize(
            json_encode($credentialPayload, JSON_THROW_ON_ERROR),
            PublicKeyCredential::class,
            'json',
        );

        if (! $publicKeyCredential instanceof PublicKeyCredential) {
            throw ValidationException::withMessages([
                'credential' => 'بيانات Passkey غير صالحة. أعد المحاولة.',
            ]);
        }

        return $publicKeyCredential;
    }
}
