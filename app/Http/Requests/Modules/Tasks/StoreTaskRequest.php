<?php

declare(strict_types=1);

namespace App\Http\Requests\Modules\Tasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use App\Modules\Tasks\Models\Task;
use App\Modules\Projects\Models\ProjectSection;
use App\Modules\Projects\Models\Project;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $projectId = $this->input('project_id');

        if (Gate::allows('create', [
            Task::class,
            $projectId !== null ? (int) $projectId : null,
        ])) {
            return true;
        }

        $sectionId = $this->input('section_id');
        $user = $this->user();
        if ($sectionId === null || $user === null || ! $user->hasPermission('tasks.create')) {
            return false;
        }

        $section = ProjectSection::query()->with('department')->find($sectionId);

        return $section !== null
            && (int) $section->project_id === (int) $projectId
            && $section->manager_can_create_tasks
            && (int) $section->department?->manager_id === (int) $user->id;
    }

    protected function failedAuthorization(): void
    {
        abort(403);
    }

    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'section_id' => [Rule::requiredIf(fn (): bool => $this->filled('project_id') && Project::query()->whereKey($this->input('project_id'))->whereHas('sections')->exists()), 'nullable', 'integer', 'exists:project_sections,id'],
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
            'delegate_to_employee' => ['sometimes', 'boolean'],
            'delegate_assignee_id' => [Rule::requiredIf(fn (): bool => $this->boolean('delegate_to_employee')), 'nullable', 'integer', 'exists:users,id'],
            'assignment_note' => ['nullable', 'string', 'max:5000'],
            'next_activity_title' => ['nullable', 'string', 'max:255'],
            'next_activity_description' => ['nullable', 'string'],
            'next_activity_assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'custom_fields' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'project_id' => 'پروژه',
            'section_id' => 'بخش پروژه',
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
            'delegate_assignee_id' => 'کارمند مقصد ارجاع',
            'assignment_note' => 'توضیح ارجاع',
            'next_activity_title' => 'فعالیت بعدی',
            'next_activity_description' => 'توضیحات فعالیت بعدی',
            'next_activity_assigned_to' => 'انجام‌دهنده فعالیت بعدی',
            'start_date' => 'تاریخ شروع',
            'due_date' => 'تاریخ سررسید',
            'is_active' => 'وضعیت فعال بودن',
        ];
    }
}
