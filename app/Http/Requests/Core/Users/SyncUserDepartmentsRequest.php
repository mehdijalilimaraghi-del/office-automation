<?php

namespace App\Http\Requests\Core\Users;

use Illuminate\Foundation\Http\FormRequest;

class SyncUserDepartmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_ids' => ['present', 'array'],
            'department_ids.*' => ['integer', 'distinct', 'exists:departments,id'],
            'primary_department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'department_ids' => 'واحدهای سازمانی',
            'department_ids.*' => 'واحد سازمانی',
            'primary_department_id' => 'واحد سازمانی اصلی',
        ];
    }
}
