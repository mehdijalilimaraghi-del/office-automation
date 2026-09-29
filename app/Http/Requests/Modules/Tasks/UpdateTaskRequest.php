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
            'title' => 'عنوان تسک',
            'description' => 'توضیحات',
            'priority' => 'اولویت',
            'start_date' => 'تاریخ شروع',
            'due_date' => 'تاریخ سررسید',
            'is_active' => 'وضعیت فعال بودن',
        ];
    }
}
