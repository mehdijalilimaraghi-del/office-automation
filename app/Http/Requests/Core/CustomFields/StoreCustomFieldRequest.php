<?php

namespace App\Http\Requests\Core\CustomFields;

use App\Core\CustomFields\Services\CustomFieldService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomFieldRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
            'label' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(CustomFieldService::supportedTypes())],
            'is_required' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:150'],
        ];
    }
}
