<?php

declare(strict_types=1);

namespace App\Http\Requests\Modules\Projects;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_id' => [
                'nullable',
                'integer',
                'exists:departments,id',
            ],
            'name' => ['required', 'string', 'max:150'],
                        'description' => ['nullable', 'string'],
            'status' => [
                'sometimes',
                Rule::in([
                    'planning',
                    'active',
                    'completed',
                    'archived',
                ]),
            ],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'manager_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
            'sections' => ['nullable', 'array', 'max:50'],
            'sections.*.department_id' => ['required_with:sections', 'integer', 'exists:departments,id'],
            'sections.*.name' => ['required_with:sections', 'string', 'max:150'],
            'sections.*.description' => ['nullable', 'string', 'max:10000'],
            'sections.*.manager_can_create_tasks' => ['sometimes', 'boolean'],
            'sections.*.documents' => ['nullable', 'array', 'max:20'],
            'sections.*.documents.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,txt,csv,zip'],
            'project_documents' => ['nullable', 'array', 'max:20'],
            'project_documents.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,txt,csv,zip'],
            'custom_fields' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'department_id' => 'واحد',
            'name' => 'نام پروژه',
            'code' => 'کد پروژه',
            'description' => 'توضیحات',
            'status' => 'وضعیت',
            'start_date' => 'تاریخ شروع',
            'end_date' => 'تاریخ پایان',
            'manager_id' => 'مدیر پروژه',
            'sections' => 'بخش‌های پروژه',
            'sections.*.department_id' => 'واحد مسئول بخش',
            'sections.*.name' => 'نام بخش',
            'is_active' => 'وضعیت فعال بودن',
        ];
    }
}