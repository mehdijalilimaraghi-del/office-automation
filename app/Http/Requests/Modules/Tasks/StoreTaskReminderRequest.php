<?php

declare(strict_types=1);

namespace App\Http\Requests\Modules\Tasks;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'remind_at' => ['required', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'کاربر',
            'title' => 'عنوان یادآور',
            'description' => 'شرح یادآور',
            'remind_at' => 'زمان یادآوری',
        ];
    }
}
