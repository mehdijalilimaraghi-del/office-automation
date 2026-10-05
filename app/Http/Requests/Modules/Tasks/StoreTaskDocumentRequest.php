<?php

declare(strict_types=1);

namespace App\Http\Requests\Modules\Tasks;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'document_type' => ['nullable', 'string', 'max:100'],
            'file' => [
                'required',
                'file',
                'max:20480',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,txt,csv,zip',
            ],
        ];
    }
}
