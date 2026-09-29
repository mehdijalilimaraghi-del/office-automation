<?php

namespace App\Http\Requests\Core\Departments;

use App\Core\Departments\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Department $department */
        $department = $this->route('department');

        return [
            'parent_id' => [
                'nullable',
                'integer',
                'exists:departments,id',
                Rule::notIn([$department->id]),
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
            'description' => 'توضیحات',
            'is_active' => 'وضعیت فعال بودن',
        ];
    }
}
