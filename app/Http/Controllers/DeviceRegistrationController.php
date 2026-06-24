<?php

namespace App\Http\Controllers;

use App\Http\Requests\RedeemDeviceCodeRequest;
use App\Services\DeviceTrustService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeviceRegistrationController extends Controller
{
    public function __construct(private DeviceTrustService $deviceTrustService) {}

    public function create(Request $request): Response|RedirectResponse
    {
        if (! $this->deviceTrustService->isEnabled()) {
            return redirect()->route('login');
        }

        if ($request->user()?->isAdmin() || $this->deviceTrustService->isDeviceTrusted($request)) {
            return redirect()->intended(route('invoices.active'));
        }

        return Inertia::render('auth/register-device', [
            'isAuthenticated' => $request->user() !== null,
        ]);
    }

    public function store(RedeemDeviceCodeRequest $request): RedirectResponse
    {
        if (! $this->deviceTrustService->isEnabled()) {
            return redirect()->route('login')->with('error', 'توثيق الأجهزة غير مفعّل حالياً.');
        }

        $this->deviceTrustService->redeemRegistrationCode(
            $request,
            $request->validated('code'),
            $request->validated('name'),
        );

        $message = 'تم توثيق الجهاز بنجاح.';

        if ($request->user()) {
            return redirect()->intended(route('invoices.active'))->with('success', $message);
        }

        return redirect()->route('login')->with('success', $message.' يمكنك تسجيل الدخول الآن.');
    }
}
