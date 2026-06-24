<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $password = $this->input('password');

        $this->merge([
            'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'password' => filled($password) ? $password : null,
            'password_confirmation' => filled($password) ? $this->input('password_confirmation') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $managedUser */
        $managedUser = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($managedUser->id)],
            'password' => ['nullable', 'string', Password::defaults(), 'confirmed'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var User $managedUser */
            $managedUser = $this->route('user');
            $currentUser = $this->user();

            if ($currentUser === null) {
                return;
            }

            if ($managedUser->is($currentUser) && ! $this->boolean('is_active')) {
                $validator->errors()->add('is_active', 'لا يمكنك تعطيل حسابك الحالي.');
            }

            if ($managedUser->is($currentUser) && $this->input('role') !== UserRole::Admin->value) {
                $validator->errors()->add('role', 'لا يمكنك تغيير صلاحيتك من مدير النظام.');
            }

            $willRemainActiveAdmin = User::query()
                ->where('role', UserRole::Admin)
                ->where('is_active', true)
                ->whereKeyNot($managedUser->id)
                ->exists();

            $managedUserWillBeActiveAdmin = $this->boolean('is_active')
                && $this->input('role') === UserRole::Admin->value;

            if (! $willRemainActiveAdmin && ! $managedUserWillBeActiveAdmin) {
                $validator->errors()->add('role', 'يجب الإبقاء على مدير نظام واحد نشط على الأقل.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.required' => 'يجب تحديد صلاحية المستخدم.',
            'role.enum' => 'صلاحية المستخدم غير صالحة.',
        ];
    }
}
