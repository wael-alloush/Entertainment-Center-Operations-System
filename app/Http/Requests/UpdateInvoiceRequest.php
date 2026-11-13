<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use App\Support\InvoiceAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice instanceof Invoice
            && $this->user() !== null
            && InvoiceAuthorization::canUpdateInvoice($invoice);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invoice_number' => ['sometimes', 'required', 'string', 'max:32'],
            'phone_number' => ['sometimes', 'required', 'string', 'max:20', 'regex:/^05\d{8}$/'],
            'children_count' => ['sometimes', 'required', 'integer', 'min:1', 'max:20'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $invoice = $this->route('invoice');

            if (! $invoice instanceof Invoice) {
                return;
            }

            foreach (InvoiceAuthorization::EDITABLE_FIELDS as $field) {
                if (! $this->has($field)) {
                    continue;
                }

                if (! InvoiceAuthorization::canUpdateField($invoice, $field)) {
                    $validator->errors()->add($field, 'لا يمكن تعديل هذا الحقل لهذا النوع من الفواتير.');
                }
            }
        });
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
