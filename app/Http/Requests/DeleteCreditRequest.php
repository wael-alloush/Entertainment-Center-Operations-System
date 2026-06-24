<?php

namespace App\Http\Requests;

use App\Models\CustomerCredit;
use Illuminate\Foundation\Http\FormRequest;

class DeleteCreditRequest extends FormRequest
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
        return [];
    }
}
