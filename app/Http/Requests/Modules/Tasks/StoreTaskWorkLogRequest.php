<?php

declare(strict_types=1);

namespace App\Http\Requests\Modules\Tasks;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskWorkLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date', 'after_or_equal:started_at'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'عنوان فعالیت',
            'description' => 'شرح فعالیت',
            'started_at' => 'زمان شروع',
            'completed_at' => 'زمان پایان',
        ];
    }
}
