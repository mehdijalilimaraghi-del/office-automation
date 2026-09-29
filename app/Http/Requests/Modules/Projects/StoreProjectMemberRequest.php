<?php

declare(strict_types=1);

namespace App\Http\Requests\Modules\Projects;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
            'project_role_id' => [
                'nullable',
                'integer',
                'exists:project_roles,id',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'کاربر',
            'project_role_id' => 'نقش پروژه',
        ];
    }
}
