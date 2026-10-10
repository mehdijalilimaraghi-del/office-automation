<?php

declare(strict_types=1);

namespace App\Http\Requests\Modules\Projects;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

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
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn(
                    'id',
                    DB::table('department_memberships')
                        ->select('user_id')
                        ->whereIn('department_id', DB::table('departments')
                            ->select('id')
                            ->where('name', 'مدیریت پروژه'))
                )),
            ],
            'sections' => ['required', 'array', 'min:1', 'max:50'],
            'sections.*.department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNotNull('manager_id'))],
            'sections.*.name' => ['required', 'string', 'max:150'],
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
            'manager_id' => 'مدیر پروژه (عضو واحد مدیریت پروژه)',
            'sections' => 'بخش‌های پروژه',
            'sections.*.department_id' => 'واحد مسئول بخش (فعال و دارای مدیر فعال)',
            'sections.*.name' => 'نام بخش',
            'project_documents' => 'مدارک عمومی پروژه',
            'project_documents.*' => 'مدرک عمومی پروژه',
            'sections.*.documents' => 'مدارک اختصاصی بخش',
            'sections.*.documents.*' => 'مدرک اختصاصی بخش',
            'is_active' => 'وضعیت فعال بودن',
        ];
    }
}