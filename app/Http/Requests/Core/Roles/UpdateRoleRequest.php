<?php

namespace App\Http\Requests\Core\Roles;

use App\Core\Roles\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('roles', 'name')
                    ->ignore($role->id),
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
            'code' => 'کد نقش',
            'description' => 'توضیحات',
            'is_active' => 'وضعیت فعال بودن',
        ];
    }
}