<?php

namespace App\Http\Requests\Core\Roles;

use App\Core\Permissions\Enums\PermissionScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permissions' => [
                'present',
                'array',
            ],

            'permissions.*.permission_id' => [
                'required',
                'integer',
                'distinct',
                'exists:permissions,id',
            ],

            'permissions.*.scope' => [
                'required',
                Rule::enum(PermissionScope::class),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'permissions' => 'مجوزها',
            'permissions.*.permission_id' => 'مجوز',
            'permissions.*.scope' => 'محدوده دسترسی',
        ];
    }
}
