<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyInvoiceBonusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'children_count' => ['required', 'integer', 'min:1', 'max:20'],
            'session_duration_id' => ['required', 'integer', Rule::exists('session_durations', 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'children_count.required' => 'عدد الأطفال مطلوب.',
            'session_duration_id.required' => 'مدة جلسة المكافأة مطلوبة.',
        ];
    }
}
