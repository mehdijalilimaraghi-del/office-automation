<?php

namespace App\Http\Requests\Core\Departments;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_id' => [
                'nullable',
                'integer',
                'exists:departments,id',
            ],

            'manager_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'code' => [
                'nullable',
                'string',
                'max:50',
            ],

            'icon' => [
                'required',
                'string',
                'max:50',
                'in:building-2,landmark,briefcase,users,factory,calculator,scale,monitor,wrench,truck,shield-check,clipboard-list,megaphone,warehouse,settings,home',
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
            'parent_id' => 'دپارتمان والد',
            'manager_id' => 'مدیر دپارتمان',
            'name' => 'نام دپارتمان',
            'code' => 'کد دپارتمان',
            'icon' => 'آیکون واحد',
            'description' => 'توضیحات',
            'is_active' => 'وضعیت فعال بودن',
        ];
    }
}
