<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManageEmployeeDayOffRequest extends FormRequest
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
        return [];
    }
}
