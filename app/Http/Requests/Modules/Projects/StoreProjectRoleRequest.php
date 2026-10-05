<?php

declare(strict_types=1);

namespace App\Http\Requests\Modules\Projects;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRoleRequest extends FormRequest
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
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'نام نقش',
                        'description' => 'توضیحات',
            'is_active' => 'وضعیت فعال بودن',
        ];
    }
}
