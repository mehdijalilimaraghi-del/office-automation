<?php

namespace App\Http\Requests\Core\CustomFields;

use App\Core\CustomFields\Services\CustomFieldService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomFieldRequest extends StoreCustomFieldRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'key' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
            'type' => ['required', Rule::in(CustomFieldService::supportedTypes())],
        ]);
    }
}
