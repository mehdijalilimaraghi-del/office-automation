<?php

namespace App\Http\Requests\Core\Settings;

use App\Core\Departments\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'management_department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
            'project_management_department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
            'secretariat_department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $managementId = $this->input('management_department_id');
            $projectManagementId = $this->input('project_management_department_id');

            if (blank($managementId) !== blank($projectManagementId)) {
                $validator->errors()->add(
                    'project_management_department_id',
                    'برای تعیین واحد مدیریت پروژه‌ها، واحد مدیریتی را نیز انتخاب کنید.'
                );

                return;
            }

            if (filled($managementId) && filled($projectManagementId)) {
                $isChildOfManagement = Department::query()
                    ->whereKey($projectManagementId)
                    ->where('parent_id', $managementId)
                    ->where('is_active', true)
                    ->exists();

                if (! $isChildOfManagement) {
                    $validator->errors()->add(
                        'project_management_department_id',
                        'واحد مدیریت پروژه‌ها باید زیرمجموعه واحد مدیریتی انتخاب‌شده باشد.'
                    );
                }
            }
        });
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
