<?php

namespace App\Http\Requests\Core\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'username' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z][A-Za-z0-9_.-]{2,49}$/',
                Rule::unique('users', 'username'),
            ],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],

            'mobile' => ['nullable', 'string', 'max:50'],

            'phone' => ['nullable', 'string', 'max:50'],

            'extension' => ['nullable', 'string', 'max:20'],

            'password' => [
                'nullable',
                'required_if:status,active',
                'string',
                'min:8',
                'confirmed',
            ],

            'department_ids' => [
                'sometimes',
                'array',
            ],

            'department_ids.*' => [
                'integer',
                'distinct',
                'exists:departments,id',
            ],

            'primary_department_id' => [
                'nullable',
                'integer',
                'exists:departments,id',
            ],

            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'employee_code' => ['nullable', 'string', 'max:50', Rule::unique('user_profiles', 'employee_code')],
            'national_id' => ['nullable', 'string', 'max:20', Rule::unique('user_profiles', 'national_id')],
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
            'department_ids' => 'واحدهای سازمانی',
            'department_ids.*' => 'واحد سازمانی',
            'primary_department_id' => 'واحد سازمانی اصلی',
            'status' => 'وضعیت',
            'role_ids' => 'نقش کاربری',
            'role_ids.*' => 'نقش کاربری',
        ];
    }
}