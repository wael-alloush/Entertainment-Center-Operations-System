<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateDeviceCodeRequest;
use App\Http\Requests\UpdateAttendanceDeviceRestrictionRequest;
use App\Http\Requests\UpdateDeviceTrustRequest;
use App\Http\Requests\UpdateTrustedDeviceRequest;
use App\Models\TrustedDevice;
use App\Services\DeviceTrustService;
use Illuminate\Http\RedirectResponse;

class TrustedDeviceController extends Controller
{
    public function __construct(private DeviceTrustService $deviceTrustService) {}

    public function storeCode(GenerateDeviceCodeRequest $request): RedirectResponse
    {
        $result = $this->deviceTrustService->generateRegistrationCode(
            $request->user(),
            $request->validated('label'),
        );

        return back()->with([
            'success' => 'تم إنشاء رمز التوثيق.',
            'generated_device_code' => $result['plain_code'],
            'generated_device_code_expires_at' => $result['expires_at'],
        ]);
    }

    public function updateSettings(UpdateDeviceTrustRequest $request): RedirectResponse
    {
        $this->deviceTrustService->setEnabled($request->boolean('enabled'));

        $message = $request->boolean('enabled')
            ? 'تم تفعيل تقييد الأجهزة الموثّقة.'
            : 'تم إيقاف تقييد الأجهزة الموثّقة.';

        return back()->with('success', $message);
    }

    public function updateAttendanceRestriction(UpdateAttendanceDeviceRestrictionRequest $request): RedirectResponse
    {
        $this->deviceTrustService->setAttendanceRestrictionEnabled($request->boolean('enabled'));

        $message = $request->boolean('enabled')
            ? 'تم تفعيل تقييد تسجيل الحضور بالأجهزة المعتمدة.'
            : 'تم إيقاف تقييد تسجيل الحضور بالأجهزة.';

        return back()->with('success', $message);
    }

    public function update(UpdateTrustedDeviceRequest $request, TrustedDevice $trustedDevice): RedirectResponse
    {
        $trustedDevice->update($request->validated());

        $message = $trustedDevice->is_active
            ? 'تم تحديث بيانات الجهاز.'
            : 'تم تعطيل الجهاز. لن يتمكن من استخدام النظام حتى إعادة تفعيله.';

        return back()->with('success', $message);
    }

    public function destroy(TrustedDevice $trustedDevice): RedirectResponse
    {
        $trustedDevice->delete();

        return back()->with('success', 'تم إلغاء توثيق الجهاز.');
    }
}
