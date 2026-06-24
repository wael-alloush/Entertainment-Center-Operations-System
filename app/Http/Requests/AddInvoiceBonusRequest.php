<?php

namespace App\Http\Requests;

class AddInvoiceBonusRequest extends ApplyInvoiceBonusRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }
}
