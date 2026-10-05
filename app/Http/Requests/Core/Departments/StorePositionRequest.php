<?php

namespace App\Http\Requests\Core\Departments;

use Illuminate\Foundation\Http\FormRequest;

class StorePositionRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'name'=>['required','string','max:150'],
                        'description'=>['nullable','string','max:5000'],
            'custom_fields' => ['nullable', 'array'],
            'is_active'=>['boolean'],
        ];
    }
}