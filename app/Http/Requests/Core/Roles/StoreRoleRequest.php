<?php

namespace App\Http\Requests\Core\Roles;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
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
                'max:100',
                'unique:roles,name',
            ],

            
            'description' => [
                'nullable',
                'string',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'نام نقش',
                        'description' => 'توضیحات',
            'is_active' => 'وضعیت فعال بودن',
        ];
    }
}