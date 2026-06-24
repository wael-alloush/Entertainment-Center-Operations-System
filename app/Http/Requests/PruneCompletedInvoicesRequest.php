<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PruneCompletedInvoicesRequest extends FormRequest
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
            'confirmation' => ['required', 'string', 'in:حذف الفواتير'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmation.in' => 'اكتب «حذف الفواتير» للتأكيد.',
        ];
    }
}
