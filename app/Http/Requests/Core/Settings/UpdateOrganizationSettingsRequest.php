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
            'postal_code' => ['nullable', 'string', 'max:20'],
            'stamp' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=2000,max_height=2000'],
            'letterhead' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=150,max_width=2500,min_height=600,max_height=5000'],
            'remove_letterhead' => ['sometimes', 'boolean'],
            'stamp_active' => ['sometimes', 'boolean'],
            'remove_stamp' => ['sometimes', 'boolean'],
            'numbering_template' => ['required', 'string', 'max:200'],
            'registration_numbering_template' => ['required', 'string', 'max:200'],
            'composition_method' => ['required', 'string', Rule::in(['editor', 'word', 'both'])],
            'correspondence_font_family' => ['required', 'string', Rule::in(['Vazirmatn', 'Tahoma', 'Arial', 'B Nazanin', 'B Mitra', 'IRANSans', 'Times New Roman'])],
            'correspondence_font_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'correspondence_font_size' => ['required', 'integer', 'min:8', 'max:36'],
            'correspondence_table_border_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'correspondence_shape_border_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'correspondence_page_size' => ['required', 'string', Rule::in(['A4', 'A5'])],
            'approval_incoming' => ['required', 'string', Rule::in(['direct', 'approval'])],
            'approval_outgoing' => ['required', 'string', Rule::in(['direct', 'approval'])],
            'approval_internal' => ['required', 'string', Rule::in(['direct', 'approval'])],
            'management_department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'project_management_department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'secretariat_department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'default_response_department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNotNull('manager_id'))],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (['numbering_template' => 'الگوی شماره نامه صادره', 'registration_numbering_template' => 'الگوی شماره ثبت دبیرخانه'] as $field => $label) {
                $template = (string) $this->input($field, '');
                preg_match_all('/\\{\\{([^{}]+)\\}\\}/u', $template, $matches);
                $tokens = $matches[1] ?? [];
                $numbers = array_values(array_filter($tokens, static fn (string $token): bool => preg_match('/^Num-(?:[1-9]|1[0-2])-[rc]$/', $token) === 1));
                $allowed = ['pdd', 'PDD', 'pmm', 'PMM', 'pyy', 'PYY', 'gdd', 'GDD', 'gmm', 'GMM', 'gyy', 'GYY', 'Dept'];
                $unknown = array_filter($tokens, static fn (string $token): bool => ! in_array($token, $allowed, true) && preg_match('/^Num-(?:[1-9]|1[0-2])-[rc]$/', $token) !== 1);
                $withoutTokens = preg_replace('/\\{\\{[^{}]+\\}\\}/u', '', $template);
                if (count($numbers) !== 1 || $unknown !== [] || count($tokens) !== count(array_unique($tokens)) || str_contains((string) $withoutTokens, '{{') || str_contains((string) $withoutTokens, '}}')) {
                    $validator->errors()->add($field, $label.' باید دقیقاً یک شمارنده معتبر و متغیرهای شناخته‌شده داشته باشد.');
                }
            }

            $managementId = $this->input('management_department_id');
            $projectManagementId = $this->input('project_management_department_id');

            if (filled($projectManagementId) && blank($managementId)) {
                $validator->errors()->add(
                    'management_department_id',
                    'برای تعیین واحد مدیریت پروژه‌ها، ابتدا واحد مدیریتی را انتخاب کنید.'
                );

                return;
            }

            if (filled($managementId) && filled($projectManagementId)) {
                $isChildOfManagement = Department::query()
                    ->whereKey($projectManagementId)
                    ->where('parent_id', $managementId)
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
            'postal_code' => 'کد پستی',
            'numbering_template' => 'الگوی شماره نامه صادره',
            'registration_numbering_template' => 'الگوی شماره ثبت دبیرخانه',
            'correspondence_font_color' => 'رنگ متن پیش‌فرض',
            'correspondence_font_size' => 'اندازه قلم پیش‌فرض',
            'correspondence_table_border_color' => 'رنگ خطوط جدول',
            'correspondence_shape_border_color' => 'رنگ خطوط شکل',
            'correspondence_page_size' => 'اندازه صفحه نامه',
            'composition_method' => 'روش نگارش نامه',
            'correspondence_font_family' => 'قلم پیش‌فرض نامه‌ها',
            'approval_incoming' => 'سیاست تأیید نامه وارده',
            'approval_outgoing' => 'سیاست تأیید نامه صادره',
            'approval_internal' => 'سیاست تأیید نامه داخلی',
            'management_department_id' => 'واحد مدیریتی',
            'project_management_department_id' => 'واحد مدیریت پروژه‌ها',
            'secretariat_department_id' => 'دبیرخانه',
        ];
    }
}
