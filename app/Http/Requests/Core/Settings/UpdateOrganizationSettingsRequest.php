<?php

namespace App\Http\Requests\Core\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'registration_number' => ['nullable', 'string', 'max:50'],
            'economic_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'management_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'project_management_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'secretariat_department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'نام سازمان',
            'short_name' => 'نام اختصاری',
            'national_id' => 'شناسه ملی',
            'registration_number' => 'شماره ثبت',
            'economic_code' => 'کد اقتصادی',
            'phone' => 'تلفن',
            'email' => 'ایمیل سازمان',
            'website' => 'وب‌سایت',
            'address' => 'نشانی',
            'management_department_id' => 'واحد مدیریتی',
            'project_management_department_id' => 'واحد مدیریت پروژه‌ها',
            'secretariat_department_id' => 'دبیرخانه',
        ];
    }
}
