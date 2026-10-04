<?php

namespace App\Http\Requests\Core\Departments;

use Illuminate\Foundation\Http\FormRequest;

class AssignPositionRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['user_id'=>['required','integer','exists:users,id'],'is_primary'=>['boolean']];
    }
}