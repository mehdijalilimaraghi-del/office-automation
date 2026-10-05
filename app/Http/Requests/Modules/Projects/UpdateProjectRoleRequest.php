<?php

declare(strict_types=1);

namespace App\Http\Requests\Modules\Projects;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
                        'description' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'نام نقش',
            'code' => 'کد نقش',
            'description' => 'توضیحات',
        ];
    }
}
