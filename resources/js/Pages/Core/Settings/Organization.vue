<script setup lang="ts">
import AppSelect from '@/Components/AppSelect.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import DepartmentTreeSelect from '@/Components/DepartmentTreeSelect.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

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
    postal_code: string | null;
    stamp_url?: string | null;
    letterhead_url?: string | null;
    stamp_active?: boolean;
    management_department_id: string | null;
    project_management_department_id: string | null;
    secretariat_department_id: string | null;
    default_response_department_id: string | null;
    numbering_format: string | null;
    numbering_template: string | null;
    numbering_custom_order: string | null;
    numbering_separator: string | null;
    numbering_padding: string | null;
    registration_numbering_format: string | null;
    registration_numbering_template: string | null;
    registration_numbering_custom_order: string | null;
    registration_numbering_separator: string | null;
    registration_numbering_padding: string | null;
    composition_method: string | null;
    correspondence_font_family: string | null;
    correspondence_font_color: string | null;
    correspondence_font_size: string | null;
    correspondence_table_border_color: string | null;
    correspondence_shape_border_color: string | null;
    correspondence_page_size: string | null;
    approval_incoming: string | null;
    approval_outgoing: string | null;
    approval_internal: string | null;
}

interface OrganizationDepartment {
    id: number;
    name: string;
    parent_id?: number | null;
    icon?: string | null;
    is_active: boolean;
}

const props = defineProps<{
    settings: OrganizationSettings;
    departments: OrganizationDepartment[];
}>();

const activeTab = ref<'organization' | 'structure' | 'correspondence'>('organization');
const correspondenceTab = ref<'numbering' | 'letter'>('numbering');
const numberingHelpOpen = ref(false);
const themeColors = ['#ffffff','#000000','#e7e6e6','#44546a','#5b9bd5','#ed7d31','#a5a5a5','#ffc000','#4472c4','#70ad47'];
const colorPalette = ['#000000','#1f1f1f','#404040','#595959','#7f7f7f','#a6a6a6','#bfbfbf','#d9d9d9','#f2f2f2','#ffffff','#c00000','#ff0000','#ff6600','#ffc000','#ffff00','#92d050','#00b050','#00b0f0','#0070c0','#002060','#7030a0','#7030a0','#c55a11','#843c0c','#e6b8af','#f4cccc','#fce4d6','#fff2cc','#e2f0d9','#d9eaf7','#e4dfec'];

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
    postal_code: props.settings.postal_code ?? '',
    stamp: null as File | null,
    stamp_active: props.settings.stamp_active ?? false,
    letterhead: null as File | null,
    remove_letterhead: false,
    remove_stamp: false,
    management_department_id: props.settings.management_department_id ?? '',
    project_management_department_id: props.settings.project_management_department_id ?? '',
    secretariat_department_id: props.settings.secretariat_department_id ?? '',
    default_response_department_id: props.settings.default_response_department_id ?? '',
    numbering_template: props.settings.numbering_template ?? '{{PYY}}/{{Dept}}/{{Num-6-r}}',
    registration_numbering_template: props.settings.registration_numbering_template ?? '{{PYY}}/{{Num-6-r}}',
    composition_method: props.settings.composition_method ?? 'both',
    correspondence_font_family: props.settings.correspondence_font_family ?? 'Vazirmatn',
    correspondence_font_color: props.settings.correspondence_font_color ?? '#202124',
    correspondence_font_size: props.settings.correspondence_font_size ?? '12',
    correspondence_table_border_color: props.settings.correspondence_table_border_color ?? '#000000',
    correspondence_shape_border_color: props.settings.correspondence_shape_border_color ?? '#000000',
    correspondence_page_size: props.settings.correspondence_page_size ?? 'A4',
    approval_incoming: props.settings.approval_incoming ?? 'approval',
    approval_outgoing: props.settings.approval_outgoing ?? 'approval',
    approval_internal: props.settings.approval_internal ?? 'direct',
});

function submit(): void {
    form.put(route('settings.organization.update'), {
        preserveScroll: true,
        forceFormData: true,
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
                        <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-4" role="tablist" aria-label="بخش‌های تنظیمات سازمان">
                            <button type="button" role="tab" :aria-selected="activeTab === 'organization'" :class="activeTab === 'organization' ? 'bg-teal-700 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100'" class="rounded-lg px-4 py-2.5 text-sm font-semibold transition" @click="activeTab = 'organization'">مشخصات سازمان</button>
                            <button type="button" role="tab" :aria-selected="activeTab === 'structure'" :class="activeTab === 'structure' ? 'bg-teal-700 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100'" class="rounded-lg px-4 py-2.5 text-sm font-semibold transition" @click="activeTab = 'structure'">واحدهای اصلی سازمان</button>
                            <button type="button" role="tab" :aria-selected="activeTab === 'correspondence'" :class="activeTab === 'correspondence' ? 'bg-teal-700 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100'" class="rounded-lg px-4 py-2.5 text-sm font-semibold transition" @click="activeTab = 'correspondence'">مکاتبات</button>
                        </div>
                        <div v-show="activeTab === 'organization'" class="space-y-6">
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
                            <div>
                                <label for="postal_code" class="mb-2 block text-sm font-semibold text-slate-700">کد پستی</label>
                                <TextInput id="postal_code" v-model="form.postal_code" dir="ltr" maxlength="20" class="block min-h-11 w-full rounded-xl text-left" placeholder="کد پستی سازمان" />
                                <InputError class="mt-1.5" :message="form.errors.postal_code" />
                            </div>
                        </div>

                        <div>
                            <label for="address" class="mb-2 block text-sm font-semibold text-slate-700">نشانی</label>
                            <textarea id="address" v-model="form.address" rows="4" class="block w-full rounded-xl border-slate-300 text-sm text-slate-900 shadow-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-200"></textarea>
                            <InputError class="mt-1.5" :message="form.errors.address" />
                        </div>


                        </div>
                        <section v-show="activeTab === 'structure'" class="space-y-5 rounded-xl border border-slate-200 bg-slate-50 p-5">
                            <div>
                                <h3 class="font-bold text-slate-900">تخصیص نقش به واحدها</h3>
                                <p class="mt-1 text-sm leading-6 text-slate-600">
                                    واحدها بر اساس شناسه انتخاب می‌شوند؛ تغییر نام واحد روی دسترسی‌ها اثر نمی‌گذارد. انتخاب این موارد الزامی نیست، اما قابلیت‌های وابسته تا زمان تعیین واحد فعال نخواهند شد.
                                </p>
                            </div>

                            <div class="grid gap-5 md:grid-cols-2">
                                <div>
                                    <label for="management_department_id" class="mb-2 block text-sm font-semibold text-slate-700">واحد مدیریتی</label>
                                    <DepartmentTreeSelect id="management_department_id" v-model="form.management_department_id" :departments="departments" placeholder="انتخاب نشده" />
                                    <InputError class="mt-1.5" :message="form.errors.management_department_id" />
                                </div>

                                <div>
                                    <label for="project_management_department_id" class="mb-2 block text-sm font-semibold text-slate-700">واحد مدیریت پروژه‌ها</label>
                                    <DepartmentTreeSelect id="project_management_department_id" v-model="form.project_management_department_id" :departments="departments" placeholder="انتخاب نشده" />
                                    <InputError class="mt-1.5" :message="form.errors.project_management_department_id" />
                                </div>

                                <div>
                                    <label for="default_response_department_id" class="mb-2 block text-sm font-semibold text-slate-700">واحد پیش‌فرض پاسخ‌گویی پروژه</label>
                                    <DepartmentTreeSelect id="default_response_department_id" v-model="form.default_response_department_id" :departments="departments.filter((department) => department.is_active)" placeholder="بدون پیش‌فرض" />
                                    <InputError class="mt-1.5" :message="form.errors.default_response_department_id" />
                                    <p class="mt-1 text-xs text-slate-500">این مقدار فقط فرم ایجاد پروژه را پیش‌پر می‌کند و در هر پروژه قابل تغییر است؛ واحد باید مدیر فعال داشته باشد.</p>
                                </div>

                                <div>
                                    <label for="secretariat_department_id" class="mb-2 block text-sm font-semibold text-slate-700">دبیرخانه</label>
                                    <DepartmentTreeSelect id="secretariat_department_id" v-model="form.secretariat_department_id" :departments="departments" placeholder="انتخاب نشده" />
                                    <InputError class="mt-1.5" :message="form.errors.secretariat_department_id" />
                                </div>
                            </div>
                        </section>

                        <section v-show="activeTab === 'correspondence'" class="space-y-5 rounded-xl border border-slate-200 bg-slate-50 p-5">
                            <div>
                                <h3 class="font-bold text-slate-900">تنظیمات مکاتبات</h3>
                                <p class="mt-1 text-sm leading-6 text-slate-600">قالب شماره نامه‌های صادره و داخلی، قالب مستقل شماره ثبت دبیرخانه، روش نگارش و سیاست تأیید را تنظیم کنید. شماره نامه وارده از روی اصل نامه وارد می‌شود.</p>
                            </div>
                            <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-3" role="tablist" aria-label="تنظیمات مکاتبات"><button type="button" role="tab" :aria-selected="correspondenceTab === 'numbering'" :class="correspondenceTab === 'numbering' ? 'bg-teal-700 text-white' : 'bg-white text-slate-600 hover:bg-slate-100'" class="rounded-lg px-4 py-2 text-sm font-semibold" @click="correspondenceTab = 'numbering'">شماره‌گذاری نامه</button><button type="button" role="tab" :aria-selected="correspondenceTab === 'letter'" :class="correspondenceTab === 'letter' ? 'bg-teal-700 text-white' : 'bg-white text-slate-600 hover:bg-slate-100'" class="rounded-lg px-4 py-2 text-sm font-semibold" @click="correspondenceTab = 'letter'">تنظیمات نامه</button></div><div v-show="correspondenceTab === 'numbering'" class="space-y-5"><div class="flex items-center justify-between gap-3"><h4 class="font-bold text-slate-900">تنظیمات شماره‌گذاری مکاتبات</h4><button type="button" class="rounded-lg border border-teal-200 bg-white px-3 py-2 text-sm font-semibold text-teal-700 hover:bg-teal-50" @click="numberingHelpOpen = true">راهنمای متغیرها</button></div><div class="space-y-4 rounded-xl border border-teal-200 bg-white p-4"><div><label for="numbering_template" class="mb-2 block text-sm font-semibold text-slate-700">قالب شماره نامه صادره</label><TextInput id="numbering_template" v-model="form.numbering_template" dir="ltr" class="block min-h-11 w-full rounded-xl font-mono text-left" placeholder="{{PYY}}/{{Dept}}/{{Num-6-r}}" /><InputError class="mt-1.5" :message="form.errors.numbering_template" /><p class="mt-2 text-xs text-slate-500">قالب از متغیرهای تاریخ، کد واحد و شمارنده ساخته می‌شود. جداکننده را مستقیماً بین متغیرها وارد کنید.</p></div><div><label for="registration_numbering_template" class="mb-2 block text-sm font-semibold text-slate-700">قالب شماره ثبت دبیرخانه</label><TextInput id="registration_numbering_template" v-model="form.registration_numbering_template" dir="ltr" class="block min-h-11 w-full rounded-xl font-mono text-left" placeholder="{{PYY}}/{{Num-6-r}}" /><InputError class="mt-1.5" :message="form.errors.registration_numbering_template" /></div><div v-if="numberingHelpOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/50 p-4" role="dialog" aria-modal="true" aria-labelledby="numbering-help-title" @click.self="numberingHelpOpen = false"><div class="max-h-[85vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl"><div class="flex items-center justify-between gap-3"><h3 id="numbering-help-title" class="text-lg font-bold text-slate-900">راهنمای متغیرهای شماره‌گذاری</h3><button type="button" class="rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100" aria-label="بستن" @click="numberingHelpOpen = false">✕</button></div><p class="mt-2 text-sm leading-6 text-slate-600">متغیرها را در قالب وارد کنید و جداکننده‌ها را مستقیماً بین آن‌ها بنویسید. برای هر قالب دقیقاً یک متغیر شمارنده لازم است.</p><div class="mt-4 overflow-x-auto"><table class="w-full text-right text-sm"><thead><tr class="border-b bg-slate-50"><th class="p-3">متغیر</th><th class="p-3">کاربرد</th><th class="p-3">نمونه</th></tr></thead><tbody><tr class="border-b"><td class="p-3 font-mono" dir="ltr">&#123;&#123;pdd&#125;&#125;</td><td class="p-3">روز شمسی کوتاه</td><td class="p-3">۰۵</td></tr><tr class="border-b"><td class="p-3 font-mono" dir="ltr">&#123;&#123;PDD&#125;&#125;</td><td class="p-3">روز شمسی بلند</td><td class="p-3">۵</td></tr><tr class="border-b"><td class="p-3 font-mono" dir="ltr">&#123;&#123;pmm&#125;&#125;</td><td class="p-3">ماه شمسی عددی</td><td class="p-3">۰۸</td></tr><tr class="border-b"><td class="p-3 font-mono" dir="ltr">&#123;&#123;PMM&#125;&#125;</td><td class="p-3">نام ماه شمسی</td><td class="p-3">آبان</td></tr><tr class="border-b"><td class="p-3 font-mono" dir="ltr">&#123;&#123;gdd&#125;&#125; / &#123;&#123;GDD&#125;&#125;</td><td class="p-3">روز میلادی با/بدون صفر ابتدایی</td><td class="p-3">04 / 4</td></tr><tr class="border-b"><td class="p-3 font-mono" dir="ltr">&#123;&#123;gmm&#125;&#125; / &#123;&#123;GMM&#125;&#125;</td><td class="p-3">ماه میلادی عددی / نام ماه</td><td class="p-3">11 / November</td></tr><tr class="border-b"><td class="p-3 font-mono" dir="ltr">&#123;&#123;pyy&#125;&#125; / &#123;&#123;PYY&#125;&#125;</td><td class="p-3">سال شمسی کوتاه / کامل</td><td class="p-3">05 / 1405</td></tr><tr class="border-b"><td class="p-3 font-mono" dir="ltr">&#123;&#123;gyy&#125;&#125; / &#123;&#123;GYY&#125;&#125;</td><td class="p-3">سال میلادی کوتاه / کامل</td><td class="p-3">26 / 2026</td></tr><tr class="border-b"><td class="p-3 font-mono" dir="ltr">&#123;&#123;Dept&#125;&#125;</td><td class="p-3">کد واحد ایجادکننده</td><td class="p-3">FIN</td></tr><tr class="border-b"><td class="p-3 font-mono" dir="ltr">&#123;&#123;Num-5-r&#125;&#125;</td><td class="p-3">شمارنده پنج‌رقمی؛ بازنشانی بر اساس متغیرهای دیگر قالب</td><td class="p-3">00012</td></tr><tr><td class="p-3 font-mono" dir="ltr">&#123;&#123;Num-5-c&#125;&#125;</td><td class="p-3">شمارنده پنج‌رقمی پیوسته و بدون بازنشانی</td><td class="p-3">00012</td></tr></tbody></table></div><p class="mt-3 text-xs text-slate-500">نمونه: &#123;&#123;PYY&#125;&#125;/&#123;&#123;Dept&#125;&#125;/&#123;&#123;Num-6-r&#125;&#125;</p></div></div></div><p class="rounded-lg border border-slate-200 bg-white p-3 text-sm text-slate-600">قالب شماره نامه برای مکاتبات صادره و داخلی استفاده می‌شود؛ شماره نامه وارده دستی و از روی اصل نامه ثبت می‌شود. شماره ثبت دبیرخانه طبق قالب مستقل و هنگام ثبت نهایی تخصیص می‌یابد.</p>
                            </div><div v-show="correspondenceTab === 'letter'" class="space-y-5"><h4 class="font-bold text-slate-900">تنظیمات نامه</h4>
 <div class="grid gap-4 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-2">
 <div class="space-y-3">
  <div><label for="organization_stamp" class="mb-2 block text-sm font-semibold text-slate-700">تصویر مهر رسمی سازمان</label><input id="organization_stamp" type="file" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm text-slate-600 file:ml-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:font-semibold file:text-slate-700" @change="(event) => { form.stamp = (event.target as HTMLInputElement).files?.[0] ?? null; form.remove_stamp = false; }" /><p class="mt-1 text-xs leading-5 text-slate-500">فرمت PNG، JPG یا WEBP و حداکثر حجم ۲ مگابایت. محل و اندازه مهر در طراح قالب نامه تعیین می‌شود.</p><InputError class="mt-1.5" :message="form.errors.stamp" /></div>
  <div v-if="props.settings.stamp_url" class="flex items-center gap-3"><img v-if="!form.remove_stamp" :src="props.settings.stamp_url" alt="مهر فعلی سازمان" class="h-20 w-20 rounded border border-slate-200 bg-white object-contain p-2" /><label class="inline-flex items-center gap-2 text-sm text-slate-700"><input v-model="form.remove_stamp" type="checkbox" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500" /> حذف مهر فعلی</label></div>
  <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700"><input v-model="form.stamp_active" type="checkbox" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500" /> فعال بودن مهر سازمان برای استفاده در قالب نامه</label>
 </div>
 <div class="space-y-3">
  <div><label for="organization_letterhead" class="mb-2 block text-sm font-semibold text-slate-700">تصویر سربرگ سازمان</label><input id="organization_letterhead" type="file" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm text-slate-600 file:ml-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:font-semibold file:text-slate-700" @change="(event) => { form.letterhead = (event.target as HTMLInputElement).files?.[0] ?? null; form.remove_letterhead = false; }" /><p class="mt-1 text-xs leading-5 text-slate-500">فرمت PNG، JPG یا WEBP، حداکثر حجم ۵ مگابایت؛ عرض مجاز ۱۵۰ تا ۲۵۰۰ پیکسل و ارتفاع مجاز ۶۰۰ تا ۵۰۰۰ پیکسل.</p><InputError class="mt-1.5" :message="form.errors.letterhead" /></div>
  <div v-if="props.settings.letterhead_url" class="flex flex-wrap items-center gap-3"><img v-if="!form.remove_letterhead" :src="props.settings.letterhead_url" alt="سربرگ فعلی سازمان" class="max-h-24 max-w-full rounded border border-slate-200 bg-white object-contain p-2" /><label class="inline-flex items-center gap-2 text-sm text-slate-700"><input v-model="form.remove_letterhead" type="checkbox" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500" /> حذف تصویر سربرگ فعلی</label></div>
 </div>
 <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-teal-200 bg-teal-50 p-4 md:col-span-2"><div><h3 class="font-bold text-teal-950">قالب نامه و محل سربرگ</h3><p class="mt-1 text-sm text-teal-800">ابعاد صفحه، جایگاه سربرگ، مهر، امضا و اجزای ثابت را در طراح قالب تنظیم کنید.</p></div><a :href="route('correspondence.template.edit')" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">باز کردن طراح قالب نامه</a></div>
</div>
<div class="grid gap-4 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-2"><div><label for="correspondence_font_color" class="mb-2 block text-sm font-semibold">رنگ پیش‌فرض متن</label><div class="space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-3"><div class="grid grid-cols-10 gap-1.5"><button v-for="color in themeColors" :key="'correspondence_font_color-theme-'+color" type="button" :title="color" :aria-label="'انتخاب رنگ '+color" class="h-6 w-full rounded border border-slate-300" :style="{backgroundColor:color,outline:form.correspondence_font_color===color?'2px solid #0f766e':'none',outlineOffset:'1px'}" @click="form.correspondence_font_color=color"/></div><div class="grid grid-cols-10 gap-1.5"><button v-for="(color,index) in colorPalette" :key="'correspondence_font_color-standard-'+index" type="button" :title="color" :aria-label="'انتخاب رنگ '+color" class="h-6 w-full rounded border border-slate-300" :style="{backgroundColor:color,outline:form.correspondence_font_color===color?'2px solid #0f766e':'none',outlineOffset:'1px'}" @click="form.correspondence_font_color=color"/></div><label class="flex items-center justify-between gap-3 text-xs text-slate-600">رنگ دلخواه<input id="correspondence_font_color" v-model="form.correspondence_font_color" type="color" class="h-9 w-14 cursor-pointer rounded border border-slate-300 bg-white p-1" /></label><p class="text-left font-mono text-xs text-slate-500" dir="ltr">{{form.correspondence_font_color}}</p></div><InputError :message="form.errors.correspondence_font_color" /></div><div><label for="correspondence_font_size" class="mb-2 block text-sm font-semibold">اندازه پیش‌فرض قلم (pt)</label><TextInput id="correspondence_font_size" v-model="form.correspondence_font_size" type="number" min="8" max="36" class="block min-h-11 w-full rounded-xl" /><InputError :message="form.errors.correspondence_font_size" /></div><div><label for="correspondence_table_border_color" class="mb-2 block text-sm font-semibold">رنگ پیش‌فرض خطوط جدول</label><div class="space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-3"><div class="grid grid-cols-10 gap-1.5"><button v-for="color in themeColors" :key="'correspondence_table_border_color-theme-'+color" type="button" :title="color" :aria-label="'انتخاب رنگ '+color" class="h-6 w-full rounded border border-slate-300" :style="{backgroundColor:color,outline:form.correspondence_table_border_color===color?'2px solid #0f766e':'none',outlineOffset:'1px'}" @click="form.correspondence_table_border_color=color"/></div><div class="grid grid-cols-10 gap-1.5"><button v-for="(color,index) in colorPalette" :key="'correspondence_table_border_color-standard-'+index" type="button" :title="color" :aria-label="'انتخاب رنگ '+color" class="h-6 w-full rounded border border-slate-300" :style="{backgroundColor:color,outline:form.correspondence_table_border_color===color?'2px solid #0f766e':'none',outlineOffset:'1px'}" @click="form.correspondence_table_border_color=color"/></div><label class="flex items-center justify-between gap-3 text-xs text-slate-600">رنگ دلخواه<input id="correspondence_table_border_color" v-model="form.correspondence_table_border_color" type="color" class="h-9 w-14 cursor-pointer rounded border border-slate-300 bg-white p-1" /></label><p class="text-left font-mono text-xs text-slate-500" dir="ltr">{{form.correspondence_table_border_color}}</p></div><InputError :message="form.errors.correspondence_table_border_color" /></div><div><label for="correspondence_shape_border_color" class="mb-2 block text-sm font-semibold">رنگ پیش‌فرض خطوط شکل‌ها</label><div class="space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-3"><div class="grid grid-cols-10 gap-1.5"><button v-for="color in themeColors" :key="'correspondence_shape_border_color-theme-'+color" type="button" :title="color" :aria-label="'انتخاب رنگ '+color" class="h-6 w-full rounded border border-slate-300" :style="{backgroundColor:color,outline:form.correspondence_shape_border_color===color?'2px solid #0f766e':'none',outlineOffset:'1px'}" @click="form.correspondence_shape_border_color=color"/></div><div class="grid grid-cols-10 gap-1.5"><button v-for="(color,index) in colorPalette" :key="'correspondence_shape_border_color-standard-'+index" type="button" :title="color" :aria-label="'انتخاب رنگ '+color" class="h-6 w-full rounded border border-slate-300" :style="{backgroundColor:color,outline:form.correspondence_shape_border_color===color?'2px solid #0f766e':'none',outlineOffset:'1px'}" @click="form.correspondence_shape_border_color=color"/></div><label class="flex items-center justify-between gap-3 text-xs text-slate-600">رنگ دلخواه<input id="correspondence_shape_border_color" v-model="form.correspondence_shape_border_color" type="color" class="h-9 w-14 cursor-pointer rounded border border-slate-300 bg-white p-1" /></label><p class="text-left font-mono text-xs text-slate-500" dir="ltr">{{form.correspondence_shape_border_color}}</p></div><InputError :message="form.errors.correspondence_shape_border_color" /></div><div><label for="correspondence_page_size" class="mb-2 block text-sm font-semibold">اندازه صفحه نامه</label><AppSelect id="correspondence_page_size" v-model="form.correspondence_page_size" class="block min-h-11 w-full rounded-xl border-slate-300 text-sm"><option value="A4">A4 — ۲۱۰ × ۲۹۷ میلی‌متر</option><option value="A5">A5 — ۱۴۸ × ۲۱۰ میلی‌متر</option></AppSelect><InputError :message="form.errors.correspondence_page_size" /></div></div><div>
                                <label for="correspondence_font_family" class="block text-sm font-semibold text-slate-700">قلم پیش‌فرض نامه‌ها</label>
                                <AppSelect id="correspondence_font_family" v-model="form.correspondence_font_family" class="mt-2 block min-h-11 w-full rounded-xl border-slate-300 text-right text-sm shadow-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
                                    <option value="Vazirmatn">وزیرمتن (Vazirmatn)</option>
                                    <option value="Tahoma">Tahoma</option>
                                    <option value="Arial">Arial</option>
                                    <option value="B Nazanin">B Nazanin</option>
                                    <option value="B Mitra">B Mitra</option>
                                    <option value="IRANSans">IRANSans</option>
                                    <option value="Times New Roman">Times New Roman</option>
                                </AppSelect>
                                <p class="mt-1 text-xs leading-5 text-slate-500">قلم انتخابی در ویرایشگر و نمایش متن نامه‌ها اعمال می‌شود. برای نمایش دقیق، قلم باید روی رایانه کاربران نصب یا در سامانه بارگذاری شده باشد.</p>
                                <InputError class="mt-1.5" :message="form.errors.correspondence_font_family" />
                            </div>
                            <div>
                                <label for="composition_method" class="block text-sm font-semibold text-slate-700">روش نگارش نامه</label>
                                <AppSelect id="composition_method" v-model="form.composition_method" class="mt-2 block min-h-11 w-full rounded-xl border-slate-300 text-right text-sm shadow-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
                                    <option value="editor">فقط ویرایشگر داخلی</option><option value="word">فقط فایل Word</option><option value="both">هر دو روش؛ انتخاب هنگام ایجاد نامه</option>
                                </AppSelect>
                                <InputError class="mt-1.5" :message="form.errors.composition_method" />
                            </div>
                            <div>
                                <h4 class="font-semibold text-slate-800">سیاست تأیید بر اساس نوع نامه</h4>
                                <p class="mt-1 text-sm text-slate-500">تعیین کنید کدام نوع نامه به تأیید نیاز دارد؛ مسیر ثبت نهایی باید این سیاست را رعایت کند.</p>
                                <div class="mt-4 grid gap-4 md:grid-cols-3">
                                    <div><label for="approval_incoming" class="mb-2 block text-sm font-semibold text-slate-700">نامه وارده</label><AppSelect id="approval_incoming" v-model="form.approval_incoming" class="block min-h-11 w-full rounded-xl border-slate-300 text-right text-sm shadow-sm"><option value="direct">ثبت مستقیم توسط کاربر مجاز</option><option value="approval">نیازمند تأیید</option></AppSelect><InputError class="mt-1.5" :message="form.errors.approval_incoming" /></div>
                                    <div><label for="approval_outgoing" class="mb-2 block text-sm font-semibold text-slate-700">نامه صادره</label><AppSelect id="approval_outgoing" v-model="form.approval_outgoing" class="block min-h-11 w-full rounded-xl border-slate-300 text-right text-sm shadow-sm"><option value="direct">ثبت مستقیم توسط کاربر مجاز</option><option value="approval">نیازمند تأیید</option></AppSelect><InputError class="mt-1.5" :message="form.errors.approval_outgoing" /></div>
                                    <div><label for="approval_internal" class="mb-2 block text-sm font-semibold text-slate-700">نامه داخلی</label><AppSelect id="approval_internal" v-model="form.approval_internal" class="block min-h-11 w-full rounded-xl border-slate-300 text-right text-sm shadow-sm"><option value="direct">ثبت مستقیم توسط کاربر مجاز</option><option value="approval">نیازمند تأیید</option></AppSelect><InputError class="mt-1.5" :message="form.errors.approval_internal" /></div>
                                </div>
                            </div></div>
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
