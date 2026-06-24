<?php

namespace App\Http\Requests;

use App\Models\CustomerCredit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! ($this->user()?->isAdmin() ?? false)) {
            return false;
        }

        $credit = $this->route('customerCredit');

        return $credit instanceof CustomerCredit && $credit->display_number === null && $credit->invoice_id === null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone_number' => ['required', 'string', 'max:20', 'regex:/^05\d{8}$/'],
            'children_count' => ['required', 'integer', 'min:1', 'max:20'],
            'session_duration_id' => ['required', 'integer', Rule::exists('session_durations', 'id')],
            'counts_as_visit' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone_number.regex' => 'رقم الجوال يجب أن يبدأ بـ 05 ويتكون من 10 أرقام.',
            'counts_as_visit.required' => 'يجب تحديد خيار احتساب الزيارة.',
        ];
    }
}
