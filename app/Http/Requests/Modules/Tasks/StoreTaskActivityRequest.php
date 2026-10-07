<?php

declare(strict_types=1);

namespace App\Http\Requests\Modules\Tasks;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'activity_type' => ['sometimes', 'in:planned,logged'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'عنوان فعالیت',
            'description' => 'توضیحات فعالیت',
            'assigned_to' => 'انجام‌دهنده',
            'activity_type' => 'نوع فعالیت',
        ];
    }
}
