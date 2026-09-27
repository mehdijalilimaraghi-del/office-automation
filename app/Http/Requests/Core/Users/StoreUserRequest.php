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

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],

            'department_id' => [
                'required',
                'integer',
                'exists:departments,id',
            ],

            'status' => [
                'sometimes',
                Rule::in([
                    'active',
                    'inactive',
                ]),
            ],

            'role_ids' => [
                'present',
                'array',
            ],

            'role_ids.*' => [
                'integer',
                'distinct',
                'exists:roles,id',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'نام کاربر',
            'email' => 'ایمیل',
            'password' => 'رمز عبور',
            'password_confirmation' => 'تکرار رمز عبور',
            'department_id' => 'واحد سازمانی',
            'status' => 'وضعیت',
            'role_ids' => 'نقش‌ها',
            'role_ids.*' => 'نقش',
        ];
    }
}