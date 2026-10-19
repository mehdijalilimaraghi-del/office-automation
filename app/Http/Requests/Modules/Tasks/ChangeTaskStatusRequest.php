<?php

declare(strict_types=1);

namespace App\Http\Requests\Modules\Tasks;

use Illuminate\Foundation\Http\FormRequest;

class ChangeTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'in:cancelled,cannot_continue',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'status' => 'وضعیت تسک',
        ];
    }
}
