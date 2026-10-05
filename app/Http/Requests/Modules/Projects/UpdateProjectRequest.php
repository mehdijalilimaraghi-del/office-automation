<?php

declare(strict_types=1);

namespace App\Http\Requests\Modules\Projects;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
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
                'required',
                Rule::in([
                    'planning',
                    'active',
                    'completed',
                    'archived',
                ]),
            ],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
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
            'is_active' => 'وضعیت فعال بودن',
        ];
    }
}