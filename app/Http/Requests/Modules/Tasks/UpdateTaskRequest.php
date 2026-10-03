<?php

declare(strict_types=1);

namespace App\Http\Requests\Modules\Tasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'external_organization_id' => ['sometimes', 'nullable', 'integer', 'exists:external_organizations,id'],
            'external_department_id' => ['sometimes', 'nullable', 'integer', 'exists:external_departments,id'],
            'external_contact_id' => ['sometimes', 'nullable', 'integer', 'exists:external_contacts,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'priority' => [
                'sometimes',
                Rule::in([
                    'low',
                    'normal',
                    'high',
                    'urgent',
                ]),
            ],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => [
                'sometimes',
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'external_organization_id' => 'سازمان خارجی',
            'external_department_id' => 'واحد خارجی',
            'external_contact_id' => 'شخص خارجی',
            'title' => 'عنوان تسک',
            'description' => 'توضیحات',
            'priority' => 'اولویت',
            'start_date' => 'تاریخ شروع',
            'due_date' => 'تاریخ سررسید',
            'is_active' => 'وضعیت فعال بودن',
        ];
    }
}
