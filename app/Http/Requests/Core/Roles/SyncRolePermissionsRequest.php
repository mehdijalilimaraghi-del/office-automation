<?php

namespace App\Http\Requests\Core\Roles;

use Illuminate\Foundation\Http\FormRequest;

class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permission_ids' => [
                'present',
                'array',
            ],

            'permission_ids.*' => [
                'integer',
                'distinct',
                'exists:permissions,id',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'permission_ids' => 'مجوزها',
            'permission_ids.*' => 'مجوز',
        ];
    }
}