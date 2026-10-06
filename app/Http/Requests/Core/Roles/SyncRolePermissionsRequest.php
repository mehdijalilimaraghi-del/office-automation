<?php

namespace App\Http\Requests\Core\Roles;

use App\Core\Permissions\Enums\PermissionScope;
use App\Core\Permissions\Models\Permission;
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
        $rules = [
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

        foreach ($this->input('permissions', []) as $index => $permission) {
            $permissionModel = isset($permission['permission_id'])
                ? Permission::query()->find((int) $permission['permission_id'])
                : null;

            if ($permissionModel === null) {
                continue;
            }

            $rules["permissions.{$index}.scope"] = [
                'required',
                Rule::in(array_map(
                    static fn (PermissionScope $scope): string => $scope->value,
                    PermissionScope::allowedForPermission($permissionModel->code),
                )),
            ];
        }

        return $rules;
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
