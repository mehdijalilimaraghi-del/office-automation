<?php

namespace App\Http\Requests\Core\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],

            'username' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z][A-Za-z0-9_.-]{2,49}$/',
                Rule::unique('users', 'username')
                    ->ignore($user->id),
            ],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->ignore($user->id),
            ],

            'mobile' => ['nullable', 'string', 'max:50'],

            'phone' => ['nullable', 'string', 'max:50'],

            'extension' => ['nullable', 'string', 'max:20'],

            'password' => [
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ],

            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'employee_code' => ['nullable', 'string', 'max:50', Rule::unique('user_profiles', 'employee_code')->ignore($user->profile?->id)],
            'national_id' => ['nullable', 'string', 'max:20', Rule::unique('user_profiles', 'national_id')->ignore($user->profile?->id)],
            'birth_date' => ['nullable', 'date'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            'status' => [
                'sometimes',
                Rule::in([
                    'active',
                    'inactive',
                ]),
            ],

            'role_ids' => [
                'sometimes',
                'array',
                'max:1',
            ],

            'custom_fields' => ['sometimes', 'array'],

            'custom_fields.*' => ['nullable'],

            'role_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'نام کاربر',
            'username' => 'نام کاربری',
            'email' => 'ایمیل',
            'mobile' => 'موبایل',
            'phone' => 'تلفن ثابت',
            'extension' => 'داخلی',
            'password' => 'رمز عبور',
            'password_confirmation' => 'تکرار رمز عبور',
            'status' => 'وضعیت',
            'role_ids' => 'نقش کاربری',
            'role_ids.*' => 'نقش کاربری',
        ];
    }
}