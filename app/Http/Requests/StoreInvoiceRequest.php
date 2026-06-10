<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invoice_number' => ['required', 'string', 'max:32'],
            'phone_number' => ['required', 'string', 'max:20', 'regex:/^05\d{8}$/'],
            'children_count' => ['required', 'integer', 'min:1', 'max:20'],
            'session_duration_id' => ['required', 'integer', Rule::exists('session_durations', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone_number.regex' => 'رقم الجوال يجب أن يبدأ بـ 05 ويتكون من 10 أرقام.',
        ];
    }
}
