<?php

namespace App\Http\Requests;

use App\Models\SessionDuration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSessionDurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $durationMinutes = $this->input('duration_minutes');

        $this->merge([
            'duration_minutes' => $durationMinutes === '' || $durationMinutes === 'null' ? null : $durationMinutes,
            'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var SessionDuration $sessionDuration */
        $sessionDuration = $this->route('sessionDuration');

        return [
            'name' => ['required', 'string', 'max:64', Rule::unique('session_durations', 'name')->ignore($sessionDuration->id)],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'color' => ['required', 'string', 'max:64'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'اسم الجلسة مستخدم مسبقاً.',
            'duration_minutes.min' => 'مدة الجلسة يجب أن تكون دقيقة واحدة على الأقل.',
        ];
    }
}
