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
            'numbering_format' => ['required', 'string', Rule::in(['simple', 'year', 'year_department', 'custom', 'template'])],
            'numbering_template' => ['required', 'string', 'max:200'],
            'numbering_order_first' => ['required', 'string', Rule::in(['year', 'department', 'sequence'])],
            'numbering_custom_fields_count' => ['nullable', 'integer', Rule::in([2, 3])],
            'numbering_order_second' => ['required', 'string', Rule::in(['year', 'department', 'sequence'])],
            'numbering_order_third' => ['nullable', 'required_if:numbering_custom_fields_count,3', 'string', Rule::in(['year', 'department', 'sequence'])],
            'numbering_separator' => ['required', 'string', 'max:5'],
            'numbering_padding' => ['required', 'integer', 'min:4', 'max:12'],
            'registration_numbering_format' => ['required', 'string', Rule::in(['simple', 'year', 'year_department', 'custom'])],
            'registration_numbering_template' => ['required', 'string', 'max:200'],
            'registration_numbering_order_first' => ['required', 'string', Rule::in(['year', 'department', 'sequence'])],
            'registration_numbering_custom_fields_count' => ['nullable', 'integer', Rule::in([2, 3])],
            'registration_numbering_order_second' => ['required', 'string', Rule::in(['year', 'department', 'sequence'])],
            'registration_numbering_order_third' => ['nullable', 'required_if:registration_numbering_custom_fields_count,3', 'string', Rule::in(['year', 'department', 'sequence'])],
            'registration_numbering_separator' => ['required', 'string', 'max:5'],
            'registration_numbering_padding' => ['required', 'integer', 'min:4', 'max:12'],
            'composition_method' => ['required', 'string', Rule::in(['editor', 'word', 'both'])],
            'correspondence_font_family' => ['required', 'string', Rule::in(['Vazirmatn', 'Tahoma', 'Arial', 'B Nazanin', 'B Mitra', 'IRANSans', 'Times New Roman'])],
            'correspondence_font_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'correspondence_font_size' => ['required', 'integer', 'min:8', 'max:36'],
            'correspondence_table_border_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'correspondence_shape_border_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'correspondence_page_size' => ['required', 'string', Rule::in(['A4'])],
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

            $fieldCount = (int) ($this->input('numbering_custom_fields_count') ?: 3);
            $order = array_slice([$this->input('numbering_order_first'), $this->input('numbering_order_second'), $this->input('numbering_order_third')], 0, $fieldCount);
            if ($this->input('numbering_format') === 'custom' && (count(array_unique($order)) !== $fieldCount || in_array(null, $order, true) || in_array('', $order, true))) {
                $validator->errors()->add('numbering_order_second', 'هر جزء شماره نامه باید فقط یک‌بار در ترتیب سفارشی انتخاب شود.');
            }
            $registrationCount = (int) ($this->input('registration_numbering_custom_fields_count') ?: 3);
            $registrationOrder = array_slice([$this->input('registration_numbering_order_first'), $this->input('registration_numbering_order_second'), $this->input('registration_numbering_order_third')], 0, $registrationCount);
            if ($this->input('registration_numbering_format') === 'custom' && (count(array_unique($registrationOrder)) !== $registrationCount || in_array(null, $registrationOrder, true) || in_array('', $registrationOrder, true))) $validator->errors()->add('registration_numbering_order_second', 'هر جزء شماره ثبت باید فقط یک‌بار در ترتیب سفارشی انتخاب شود.');
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
            'numbering_format' => 'قالب شماره نامه',
            'numbering_template' => 'الگوی شماره نامه صادره',
            'registration_numbering_template' => 'الگوی شماره ثبت دبیرخانه',
            'correspondence_font_color' => 'رنگ متن پیش‌فرض',
            'correspondence_font_size' => 'اندازه قلم پیش‌فرض',
            'correspondence_table_border_color' => 'رنگ خطوط جدول',
            'correspondence_shape_border_color' => 'رنگ خطوط شکل',
            'correspondence_page_size' => 'اندازه صفحه نامه',
            'numbering_order_first' => 'جزء اول شماره نامه',
            'numbering_custom_fields_count' => 'تعداد اجزای قالب سفارشی',
            'numbering_order_second' => 'جزء دوم شماره نامه',
            'numbering_order_third' => 'جزء سوم شماره نامه',
            'numbering_separator' => 'جداکننده شماره نامه',
            'numbering_padding' => 'طول شماره ترتیبی',
            'registration_numbering_format' => 'قالب شماره ثبت دبیرخانه', 'registration_numbering_order_first' => 'جزء اول شماره ثبت', 'registration_numbering_order_second' => 'جزء دوم شماره ثبت', 'registration_numbering_order_third' => 'جزء سوم شماره ثبت', 'registration_numbering_custom_fields_count' => 'تعداد اجزای قالب شماره ثبت', 'registration_numbering_separator' => 'جداکننده شماره ثبت', 'registration_numbering_padding' => 'طول شماره ترتیبی ثبت',
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
