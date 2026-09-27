<?php

namespace App\Http\Requests\Core\Permissions;

use App\Core\Permissions\Models\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Permission $permission */
        $permission = $this->route('permission');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('permissions', 'name')
                    ->ignore($permission->id),
            ],

            'code' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Za-z][A-Za-z0-9._:-]*$/',
                Rule::unique('permissions', 'code')
                    ->ignore($permission->id),
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
            'name' => 'نام مجوز',
            'code' => 'کد مجوز',
            'description' => 'توضیحات',
            'is_active' => 'وضعیت فعال بودن',
        ];
    }
}