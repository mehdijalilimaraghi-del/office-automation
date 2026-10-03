<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, usePage } from '@inertiajs/vue3';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();

const user = usePage().props.auth.user;
</script>

<template>
    <Head title="پروفایل" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs font-semibold text-slate-400">حساب کاربری</p>
                <h2 class="mt-1 text-xl font-bold text-slate-900">پروفایل من</h2>
            </div>
        </template>

        <div class="min-h-screen bg-slate-50/70 py-8" dir="rtl">
            <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="overflow-hidden rounded-3xl bg-gradient-to-l from-slate-950 via-slate-900 to-teal-950 p-6 text-white shadow-xl sm:p-8">
                    <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
                        <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-3xl bg-white/10 text-3xl font-black ring-1 ring-white/15">
                            {{ user.name?.charAt(0) }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-teal-200">حساب کاربری</p>
                            <h1 class="mt-1 truncate text-2xl font-black">{{ user.name }}</h1>
                            <p class="mt-2 truncate text-sm text-slate-300" dir="ltr">{{ user.email }}</p>
                        </div>
                    </div>
                </section>

                <div class="grid gap-6 lg:grid-cols-2">
                    <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
                        <UpdateProfileInformationForm
                            :must-verify-email="mustVerifyEmail"
                            :status="status"
                        />
                    </section>

                    <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
                        <UpdatePasswordForm />
                    </section>

                    <section class="rounded-3xl border border-red-100 bg-red-50/50 p-6 shadow-sm sm:p-8 lg:col-span-2">
                        <DeleteUserForm />
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
