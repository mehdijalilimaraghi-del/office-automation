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
            'numbering_format' => ['required', 'string', Rule::in(['simple', 'year', 'year_department', 'custom'])],
            'numbering_order_first' => ['required', 'string', Rule::in(['year', 'department', 'sequence'])],
            'numbering_custom_fields_count' => ['nullable', 'integer', Rule::in([2, 3])],
            'numbering_order_second' => ['required', 'string', Rule::in(['year', 'department', 'sequence'])],
            'numbering_order_third' => ['nullable', 'required_if:numbering_custom_fields_count,3', 'string', Rule::in(['year', 'department', 'sequence'])],
            'numbering_separator' => ['required', 'string', 'max:5'],
            'numbering_padding' => ['required', 'integer', 'min:4', 'max:12'],
            'composition_method' => ['required', 'string', Rule::in(['editor', 'word', 'both'])],
            'correspondence_font_family' => ['required', 'string', Rule::in(['Vazirmatn', 'Tahoma', 'Arial', 'B Nazanin', 'B Mitra', 'IRANSans', 'Times New Roman'])],
            'approval_incoming' => ['required', 'string', Rule::in(['direct', 'approval'])],
            'approval_outgoing' => ['required', 'string', Rule::in(['direct', 'approval'])],
            'approval_internal' => ['required', 'string', Rule::in(['direct', 'approval'])],
            'management_department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'project_management_department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'secretariat_department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $fieldCount = (int) ($this->input('numbering_custom_fields_count') ?: 3);
            $order = array_slice([$this->input('numbering_order_first'), $this->input('numbering_order_second'), $this->input('numbering_order_third')], 0, $fieldCount);
            if ($this->input('numbering_format') === 'custom' && (count(array_unique($order)) !== $fieldCount || in_array(null, $order, true) || in_array('', $order, true))) {
                $validator->errors()->add('numbering_order_second', 'هر جزء شماره نامه باید فقط یک‌بار در ترتیب سفارشی انتخاب شود.');
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
            'numbering_format' => 'قالب شماره نامه',
            'numbering_order_first' => 'جزء اول شماره نامه',
            'numbering_custom_fields_count' => 'تعداد اجزای قالب سفارشی',
            'numbering_order_second' => 'جزء دوم شماره نامه',
            'numbering_order_third' => 'جزء سوم شماره نامه',
            'numbering_separator' => 'جداکننده شماره نامه',
            'numbering_padding' => 'طول شماره ترتیبی',
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
