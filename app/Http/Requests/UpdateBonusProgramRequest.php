<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBonusProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'visit_threshold' => ['required', 'integer', 'min:1', 'max:50'],
            'validity_days' => ['required', 'integer', 'min:1', 'max:365'],
            'expiry_check_time' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'visit_threshold.required' => 'عدد الزيارات للمكافأة مطلوب.',
            'validity_days.required' => 'مدة صلاحية المكافأة مطلوبة.',
            'expiry_check_time.required' => 'ساعة التحقق من انتهاء المكافآت مطلوبة.',
            'expiry_check_time.regex' => 'صيغة ساعة التحقق غير صحيحة.',
        ];
    }
}
