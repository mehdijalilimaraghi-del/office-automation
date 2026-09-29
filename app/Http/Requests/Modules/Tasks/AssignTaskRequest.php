<?php

declare(strict_types=1);

namespace App\Http\Requests\Modules\Tasks;

use Illuminate\Foundation\Http\FormRequest;

class AssignTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assignee_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'assignee_id' => 'مسئول تسک',
        ];
    }
}
