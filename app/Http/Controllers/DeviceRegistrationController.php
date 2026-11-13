<?php

namespace App\Http\Controllers;

use App\Http\Requests\RedeemDeviceCodeRequest;
use App\Services\DeviceTrustService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DeviceRegistrationController extends Controller
{
    public function __construct(private DeviceTrustService $deviceTrustService) {}

    public function create(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->redirectIfDeviceTrustUnavailable()) {
            return $redirect;
        }

        if ($request->user()?->isAdmin() || $this->deviceTrustService->isDeviceTrusted($request)) {
            return redirect()->intended(route('invoices.active'));
        }

        return Inertia::render('auth/register-device', [
            'isAuthenticated' => $request->user() !== null,
            'canAttemptPasskeyRestore' => $request->user() !== null
                && $this->deviceTrustService->hasRegisteredWebAuthnDevices(),
        ]);
    }

    public function store(RedeemDeviceCodeRequest $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfDeviceTrustUnavailable()) {
            return $redirect;
        }

        $token = $this->deviceTrustService->resolveToken($request);

        $this->deviceTrustService->redeemRegistrationCode(
            $request,
            $request->validated('code'),
            $request->validated('name'),
        );

        $redirect = $request->user()
            ? redirect()->intended(route('invoices.active'))
            : redirect()->route('login');

        return $redirect
            ->with('success', $request->user()
                ? 'تم توثيق الجهاز بنجاح.'
                : 'تم توثيق الجهاز بنجاح. يمكنك تسجيل الدخول الآن.')
            ->withCookie($this->deviceTrustService->attachDeviceCookie($token, $request));
    }

    public function options(Request $request): JsonResponse
    {
        $this->ensureDeviceTrustEnabled();
        $this->ensureAuthenticated($request);

        return response()->json(
            $this->deviceTrustService->generateWebAuthnRegistrationOptions(
                $request->user(),
                $request->input('name', $request->user()->name),
                $request,
            )
        );
    }

    public function assertionOptions(Request $request): JsonResponse
    {
        $this->ensureDeviceTrustEnabled();
        $this->ensureAuthenticated($request);

        return response()->json(
            $this->deviceTrustService->generateWebAuthnAssertionOptions($request)
        );
    }

    public function restoreWithWebAuthn(Request $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfDeviceTrustUnavailable()) {
            return $redirect;
        }

        $token = $request->cookie(DeviceTrustService::COOKIE_NAME);

        if (! is_string($token) || ! Str::isUuid($token)) {
            $token = $this->deviceTrustService->resolveToken($request);
        }

        $validated = $request->validate([
            'credential' => ['required', 'array'],
        ]);

        $this->deviceTrustService->restoreDeviceTrustWithWebAuthn(
            $request,
            $validated['credential'],
        );

        return redirect()->intended(route('invoices.active'))
            ->withCookie($this->deviceTrustService->attachDeviceCookie($token, $request));
    }

    public function registerWithWebAuthn(Request $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfDeviceTrustUnavailable()) {
            return $redirect;
        }

        $token = $this->deviceTrustService->resolveToken($request);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:120'],
            'credential' => ['required', 'array'],
        ]);

        $this->deviceTrustService->completeWebAuthnRegistration(
            $request,
            $validated['code'],
            $validated['name'],
            $validated['credential'],
        );

        return redirect()->intended(route('invoices.active'))
            ->with('success', 'تم توثيق الجهاز بنجاح.')
            ->withCookie($this->deviceTrustService->attachDeviceCookie($token, $request));
    }

    private function redirectIfDeviceTrustUnavailable(): ?RedirectResponse
    {
        if ($this->deviceTrustService->isEnabled()) {
            return null;
        }

        return redirect()->route('login')->with('error', 'توثيق الأجهزة غير مفعّل حالياً.');
    }

    private function ensureDeviceTrustEnabled(): void
    {
        if (! $this->deviceTrustService->isEnabled()) {
            throw ValidationException::withMessages([
                'device' => 'توثيق الأجهزة غير مفعّل حالياً.',
            ]);
        }
    }

    private function ensureAuthenticated(Request $request): void
    {
        if ($request->user() === null) {
            abort(401, 'يجب تسجيل الدخول أولاً.');
        }
    }
}
