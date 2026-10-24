<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

interface OrganizationSettings {
    name: string | null;
    short_name: string | null;
    national_id: string | null;
    registration_number: string | null;
    economic_code: string | null;
    phone: string | null;
    email: string | null;
    website: string | null;
    address: string | null;
    management_department_id: string | null;
    project_management_department_id: string | null;
    secretariat_department_id: string | null;
    numbering_format: string | null;
    numbering_custom_order: string | null;
    numbering_separator: string | null;
    numbering_padding: string | null;
    composition_method: string | null;
    correspondence_font_family: string | null;
    approval_incoming: string | null;
    approval_outgoing: string | null;
    approval_internal: string | null;
}

interface OrganizationDepartment {
    id: number;
    name: string;
    is_active: boolean;
}

const props = defineProps<{
    settings: OrganizationSettings;
    departments: OrganizationDepartment[];
}>();

const customOrder = (props.settings.numbering_custom_order ?? 'year,department,sequence').split(',');

const form = useForm({
    name: props.settings.name ?? '',
    short_name: props.settings.short_name ?? '',
    national_id: props.settings.national_id ?? '',
    registration_number: props.settings.registration_number ?? '',
    economic_code: props.settings.economic_code ?? '',
    phone: props.settings.phone ?? '',
    email: props.settings.email ?? '',
    website: props.settings.website ?? '',
    address: props.settings.address ?? '',
    management_department_id: props.settings.management_department_id ?? '',
    project_management_department_id: props.settings.project_management_department_id ?? '',
    secretariat_department_id: props.settings.secretariat_department_id ?? '',
    numbering_format: props.settings.numbering_format ?? 'year',
    numbering_order_first: customOrder[0] ?? 'year',
    numbering_order_second: customOrder[1] ?? 'department',
    numbering_order_third: customOrder[2] ?? 'sequence',
    numbering_separator: props.settings.numbering_separator ?? '/',
    numbering_padding: props.settings.numbering_padding ?? '6',
    composition_method: props.settings.composition_method ?? 'both',
    correspondence_font_family: props.settings.correspondence_font_family ?? 'Vazirmatn',
    approval_incoming: props.settings.approval_incoming ?? 'approval',
    approval_outgoing: props.settings.approval_outgoing ?? 'approval',
    approval_internal: props.settings.approval_internal ?? 'direct',
});

function submit(): void {
    form.put(route('settings.organization.update'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="تنظیمات سازمان" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs font-semibold tracking-wide text-teal-600">مدیریت سامانه</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">تنظیمات سازمان</h2>
                <p class="mt-1 text-sm text-slate-500">اطلاعات پایه سازمان و نقش‌های اختصاص‌یافته به واحدها را مدیریت کنید.</p>
            </div>
        </template>

        <div class="min-h-[calc(100vh-8rem)] bg-slate-50 py-6 md:py-8" dir="rtl">
            <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-teal-50 text-teal-700">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16M9 7h2m-2 4h2m2-4h2m-2 4h2M8 21v-4h8v4" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-900">مشخصات سازمان</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-500">
                                این اطلاعات برای هویت سازمان در سامانه استفاده می‌شود. واحدها، مدیران و سمت‌ها در بخش «ساختار سازمانی» مدیریت می‌شوند.
                            </p>
                        </div>
                    </div>

                    <form class="mt-7 space-y-6" @submit.prevent="submit">
                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label for="organization_name" class="mb-2 block text-sm font-semibold text-slate-700">نام سازمان</label>
                                <TextInput id="organization_name" v-model="form.name" class="block min-h-11 w-full rounded-xl text-right" required />
                                <InputError class="mt-1.5" :message="form.errors.name" />
                            </div>

                            <div>
                                <label for="short_name" class="mb-2 block text-sm font-semibold text-slate-700">نام اختصاری</label>
                                <TextInput id="short_name" v-model="form.short_name" class="block min-h-11 w-full rounded-xl text-right" />
                                <InputError class="mt-1.5" :message="form.errors.short_name" />
                            </div>

                            <div>
                                <label for="national_id" class="mb-2 block text-sm font-semibold text-slate-700">شناسه ملی</label>
                                <TextInput id="national_id" v-model="form.national_id" dir="ltr" class="block min-h-11 w-full rounded-xl text-left" />
                                <InputError class="mt-1.5" :message="form.errors.national_id" />
                            </div>

                            <div>
                                <label for="registration_number" class="mb-2 block text-sm font-semibold text-slate-700">شماره ثبت</label>
                                <TextInput id="registration_number" v-model="form.registration_number" dir="ltr" class="block min-h-11 w-full rounded-xl text-left" />
                                <InputError class="mt-1.5" :message="form.errors.registration_number" />
                            </div>

                            <div>
                                <label for="economic_code" class="mb-2 block text-sm font-semibold text-slate-700">کد اقتصادی</label>
                                <TextInput id="economic_code" v-model="form.economic_code" dir="ltr" class="block min-h-11 w-full rounded-xl text-left" />
                                <InputError class="mt-1.5" :message="form.errors.economic_code" />
                            </div>

                            <div>
                                <label for="phone" class="mb-2 block text-sm font-semibold text-slate-700">تلفن</label>
                                <TextInput id="phone" v-model="form.phone" dir="ltr" class="block min-h-11 w-full rounded-xl text-left" />
                                <InputError class="mt-1.5" :message="form.errors.phone" />
                            </div>

                            <div>
                                <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">ایمیل سازمان</label>
                                <TextInput id="email" v-model="form.email" type="email" dir="ltr" class="block min-h-11 w-full rounded-xl text-left" autocomplete="email" />
                                <InputError class="mt-1.5" :message="form.errors.email" />
                            </div>

                            <div>
                                <label for="website" class="mb-2 block text-sm font-semibold text-slate-700">وب‌سایت</label>
                                <TextInput id="website" v-model="form.website" dir="ltr" class="block min-h-11 w-full rounded-xl text-left" placeholder="https://example.com" />
                                <InputError class="mt-1.5" :message="form.errors.website" />
                            </div>
                        </div>

                        <div>
                            <label for="address" class="mb-2 block text-sm font-semibold text-slate-700">نشانی</label>
                            <textarea id="address" v-model="form.address" rows="4" class="block w-full rounded-xl border-slate-300 text-sm text-slate-900 shadow-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-200"></textarea>
                            <InputError class="mt-1.5" :message="form.errors.address" />
                        </div>

                        <section class="space-y-5 rounded-xl border border-slate-200 bg-slate-50 p-5">
                            <div>
                                <h3 class="font-bold text-slate-900">تخصیص نقش به واحدها</h3>
                                <p class="mt-1 text-sm leading-6 text-slate-600">
                                    واحدها بر اساس شناسه انتخاب می‌شوند؛ تغییر نام واحد روی دسترسی‌ها اثر نمی‌گذارد. انتخاب این موارد الزامی نیست، اما قابلیت‌های وابسته تا زمان تعیین واحد فعال نخواهند شد.
                                </p>
                            </div>

                            <div class="grid gap-5 md:grid-cols-2">
                                <div>
                                    <label for="management_department_id" class="mb-2 block text-sm font-semibold text-slate-700">واحد مدیریتی</label>
                                    <select id="management_department_id" v-model="form.management_department_id" class="block min-h-11 w-full rounded-xl border-slate-300 text-right text-sm shadow-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
                                        <option value="">انتخاب نشده</option>
                                        <option v-for="department in departments" :key="department.id" :value="String(department.id)" :disabled="!department.is_active">
                                            {{ department.name }}{{ department.is_active ? '' : ' (غیرفعال)' }}
                                        </option>
                                    </select>
                                    <InputError class="mt-1.5" :message="form.errors.management_department_id" />
                                </div>

                                <div>
                                    <label for="project_management_department_id" class="mb-2 block text-sm font-semibold text-slate-700">واحد مدیریت پروژه‌ها</label>
                                    <select id="project_management_department_id" v-model="form.project_management_department_id" class="block min-h-11 w-full rounded-xl border-slate-300 text-right text-sm shadow-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
                                        <option value="">انتخاب نشده</option>
                                        <option v-for="department in departments" :key="department.id" :value="String(department.id)" :disabled="!department.is_active">
                                            {{ department.name }}{{ department.is_active ? '' : ' (غیرفعال)' }}
                                        </option>
                                    </select>
                                    <InputError class="mt-1.5" :message="form.errors.project_management_department_id" />
                                </div>

                                <div>
                                    <label for="secretariat_department_id" class="mb-2 block text-sm font-semibold text-slate-700">دبیرخانه</label>
                                    <select id="secretariat_department_id" v-model="form.secretariat_department_id" class="block min-h-11 w-full rounded-xl border-slate-300 text-right text-sm shadow-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
                                        <option value="">انتخاب نشده</option>
                                        <option v-for="department in departments" :key="department.id" :value="String(department.id)" :disabled="!department.is_active">
                                            {{ department.name }}{{ department.is_active ? '' : ' (غیرفعال)' }}
                                        </option>
                                    </select>
                                    <InputError class="mt-1.5" :message="form.errors.secretariat_department_id" />
                                </div>
                            </div>
                        </section>

                        <section class="space-y-5 rounded-xl border border-slate-200 bg-slate-50 p-5">
                            <div>
                                <h3 class="font-bold text-slate-900">تنظیمات مکاتبات</h3>
                                <p class="mt-1 text-sm leading-6 text-slate-600">قالب شماره‌گذاری، روش نگارش و سیاست تأیید را برای کل سازمان تنظیم کنید. شماره نهایی هنگام ثبت رسمی تخصیص می‌یابد.</p>
                            </div>
                            <div class="grid gap-5 md:grid-cols-2">
                                <div>
                                    <label for="numbering_format" class="mb-2 block text-sm font-semibold text-slate-700">قالب شماره نامه</label>
                                    <select id="numbering_format" v-model="form.numbering_format" class="block min-h-11 w-full rounded-xl border-slate-300 text-right text-sm shadow-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
                                        <option value="simple">شماره ترتیبی — ۰۰۰۱۲۳</option>
                                        <option value="year">سال + شماره — ۱۴۰۵/۰۰۰۱۲۳</option>
                                        <option value="year_department">سال + واحد + شماره — ۱۴۰۵/IT/۰۰۰۱۲۳</option>
                                        <option value="custom">سفارشی — انتخاب ترتیب اجزا</option>
                                    </select>
                                    <InputError class="mt-1.5" :message="form.errors.numbering_format" />
                                </div>
                                <div>
                                    <label for="numbering_separator" class="mb-2 block text-sm font-semibold text-slate-700">جداکننده اجزا</label>
                                    <TextInput id="numbering_separator" v-model="form.numbering_separator" class="block min-h-11 w-full rounded-xl text-right" maxlength="5" />
                                    <InputError class="mt-1.5" :message="form.errors.numbering_separator" />
                                </div>
                                <div>
                                    <label for="numbering_padding" class="mb-2 block text-sm font-semibold text-slate-700">طول شماره ترتیبی</label>
                                    <select id="numbering_padding" v-model="form.numbering_padding" class="block min-h-11 w-full rounded-xl border-slate-300 text-right text-sm shadow-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
                                        <option value="4">۴ رقم</option><option value="5">۵ رقم</option><option value="6">۶ رقم</option><option value="7">۷ رقم</option><option value="8">۸ رقم</option><option value="9">۹ رقم</option><option value="10">۱۰ رقم</option><option value="11">۱۱ رقم</option><option value="12">۱۲ رقم</option>
                                    </select>
                                    <InputError class="mt-1.5" :message="form.errors.numbering_padding" />
                                </div>
                            </div>
                            <div v-if="form.numbering_format === 'custom'" class="grid gap-5 md:grid-cols-3">
                                <div v-for="(field, index) in (['numbering_order_first', 'numbering_order_second', 'numbering_order_third'] as const)" :key="field">
                                    <label :for="field" class="mb-2 block text-sm font-semibold text-slate-700">جزء {{ ['اول', 'دوم', 'سوم'][index] }}</label>
                                    <select :id="field" v-model="form[field]" class="block min-h-11 w-full rounded-xl border-slate-300 text-right text-sm shadow-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
                                        <option value="year">سال شمسی</option><option value="department">کد واحد</option><option value="sequence">شماره ترتیبی</option>
                                    </select>
                                    <InputError class="mt-1.5" :message="form.errors[field]" />
                                </div>
                            </div>
                            <p class="rounded-lg border border-slate-200 bg-white p-3 text-sm text-slate-600">قالب‌های پیش‌فرض از سال شمسی، کد واحد و شمارنده استفاده می‌کنند. تغییر قالب فقط بر شماره‌های آینده اثر دارد.</p>
                            <div>
                                <label for="correspondence_font_family" class="block text-sm font-semibold text-slate-700">قلم پیش‌فرض نامه‌ها</label>
                                <select id="correspondence_font_family" v-model="form.correspondence_font_family" class="mt-2 block min-h-11 w-full rounded-xl border-slate-300 text-right text-sm shadow-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
                                    <option value="Vazirmatn">وزیرمتن (Vazirmatn)</option>
                                    <option value="Tahoma">Tahoma</option>
                                    <option value="Arial">Arial</option>
                                    <option value="B Nazanin">B Nazanin</option>
                                    <option value="B Mitra">B Mitra</option>
                                    <option value="IRANSans">IRANSans</option>
                                    <option value="Times New Roman">Times New Roman</option>
                                </select>
                                <p class="mt-1 text-xs leading-5 text-slate-500">قلم انتخابی در ویرایشگر و نمایش متن نامه‌ها اعمال می‌شود. برای نمایش دقیق، قلم باید روی رایانه کاربران نصب یا در سامانه بارگذاری شده باشد.</p>
                                <InputError class="mt-1.5" :message="form.errors.correspondence_font_family" />
                            </div>
                            <div>
                                <label for="composition_method" class="block text-sm font-semibold text-slate-700">روش نگارش نامه</label>
                                <select id="composition_method" v-model="form.composition_method" class="mt-2 block min-h-11 w-full rounded-xl border-slate-300 text-right text-sm shadow-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
                                    <option value="editor">فقط ویرایشگر داخلی</option><option value="word">فقط فایل Word</option><option value="both">هر دو روش؛ انتخاب هنگام ایجاد نامه</option>
                                </select>
                                <InputError class="mt-1.5" :message="form.errors.composition_method" />
                            </div>
                            <div>
                                <h4 class="font-semibold text-slate-800">سیاست تأیید بر اساس نوع نامه</h4>
                                <p class="mt-1 text-sm text-slate-500">تعیین کنید کدام نوع نامه به تأیید نیاز دارد؛ مسیر ثبت نهایی باید این سیاست را رعایت کند.</p>
                                <div class="mt-4 grid gap-4 md:grid-cols-3">
                                    <div><label for="approval_incoming" class="mb-2 block text-sm font-semibold text-slate-700">نامه وارده</label><select id="approval_incoming" v-model="form.approval_incoming" class="block min-h-11 w-full rounded-xl border-slate-300 text-right text-sm shadow-sm"><option value="direct">ثبت مستقیم توسط کاربر مجاز</option><option value="approval">نیازمند تأیید</option></select><InputError class="mt-1.5" :message="form.errors.approval_incoming" /></div>
                                    <div><label for="approval_outgoing" class="mb-2 block text-sm font-semibold text-slate-700">نامه صادره</label><select id="approval_outgoing" v-model="form.approval_outgoing" class="block min-h-11 w-full rounded-xl border-slate-300 text-right text-sm shadow-sm"><option value="direct">ثبت مستقیم توسط کاربر مجاز</option><option value="approval">نیازمند تأیید</option></select><InputError class="mt-1.5" :message="form.errors.approval_outgoing" /></div>
                                    <div><label for="approval_internal" class="mb-2 block text-sm font-semibold text-slate-700">نامه داخلی</label><select id="approval_internal" v-model="form.approval_internal" class="block min-h-11 w-full rounded-xl border-slate-300 text-right text-sm shadow-sm"><option value="direct">ثبت مستقیم توسط کاربر مجاز</option><option value="approval">نیازمند تأیید</option></select><InputError class="mt-1.5" :message="form.errors.approval_internal" /></div>
                                </div>
                            </div>
                        </section>

                        <div class="flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-xs text-slate-500">نقش‌های واحدها بر اساس شناسه ذخیره می‌شوند؛ تغییر نام واحدها دسترسی‌ها را تغییر نمی‌دهد.</p>
                            <PrimaryButton :disabled="form.processing" class="min-h-11 rounded-xl bg-slate-900 px-5 text-sm font-semibold tracking-normal hover:bg-slate-800">
                                {{ form.processing ? 'در حال ذخیره...' : 'ذخیره تنظیمات' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
