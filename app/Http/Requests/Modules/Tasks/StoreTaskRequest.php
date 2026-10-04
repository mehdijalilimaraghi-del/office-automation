<?php

declare(strict_types=1);

namespace App\Http\Requests\Modules\Tasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'external_organization_id' => ['nullable', 'integer', 'exists:external_organizations,id'],
            'external_department_id' => ['nullable', 'integer', 'exists:external_departments,id'],
            'external_contact_id' => ['nullable', 'integer', 'exists:external_contacts,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['todo', 'in_progress', 'completed', 'cancelled'])],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'review_required' => ['sometimes', 'boolean'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'assignment_note' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'project_id' => 'پروژه',
            'department_id' => 'واحد',
            'external_organization_id' => 'سازمان خارجی',
            'external_department_id' => 'واحد خارجی',
            'external_contact_id' => 'شخص خارجی',
            'external_organization_id' => 'سازمان خارجی',
            'external_department_id' => 'واحد خارجی',
            'external_contact_id' => 'شخص خارجی',
            'title' => 'عنوان تسک',
            'description' => 'توضیحات',
            'status' => 'وضعیت',
            'priority' => 'اولویت',
            'assignee_id' => 'مسئول تسک',
            'assignment_note' => 'توضیح ارجاع',
            'start_date' => 'تاریخ شروع',
            'due_date' => 'تاریخ سررسید',
            'is_active' => 'وضعیت فعال بودن',
        ];
    }
}
