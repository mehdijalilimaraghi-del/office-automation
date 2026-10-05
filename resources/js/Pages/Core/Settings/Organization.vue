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
}

const props = defineProps<{
    settings: OrganizationSettings;
}>();

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
                <p class="mt-1 text-sm text-slate-500">اطلاعات پایه شرکت را مستقل از ساختار دپارتمان‌ها مدیریت کنید.</p>
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

                        <div class="flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-xs text-slate-500">این تنظیمات به ساختار داخلی دپارتمان‌ها وابسته نیست.</p>
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
