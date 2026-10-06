<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    token: string;
    email: string;
    username: string;
    name: string;
}>();

const form = useForm({
    token: props.token,
    password: '',
    password_confirmation: '',
});

function submit(): void {
    form.post(route('users.invitation.store'));
}
</script>

<template>
    <div dir="rtl" class="flex min-h-screen items-center justify-center bg-slate-50 px-4">
        <Head title="فعال‌سازی حساب" />
        <div class="w-full max-w-md rounded-3xl bg-white p-8 shadow-xl ring-1 ring-slate-200">
            <div class="mb-8">
                <p class="text-xs font-semibold text-teal-600">Office Automation</p>
                <h1 class="mt-2 text-2xl font-black text-slate-900">فعال‌سازی حساب</h1>
                <p class="mt-2 text-sm text-slate-500">{{ props.name }}</p>
                <p dir="ltr" class="mt-1 text-xs text-teal-600">@{{ props.username }}</p>
                <p dir="ltr" class="mt-1 text-xs text-slate-400">{{ props.email }}</p>
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-slate-700">رمز عبور جدید</label>
                    <input v-model="form.password" type="password" required class="w-full rounded-xl border-slate-200 bg-slate-50/50" />
                    <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-slate-700">تکرار رمز عبور</label>
                    <input v-model="form.password_confirmation" type="password" class="w-full rounded-xl border-slate-200 bg-slate-50/50" />
                </div>
                <p v-if="form.errors.token" class="text-xs text-red-600">{{ form.errors.token }}</p>
                <button type="submit" :disabled="form.processing" class="w-full rounded-xl bg-slate-900 px-4 py-3 text-sm font-bold text-white disabled:opacity-50">
                    {{ form.processing ? 'در حال فعال‌سازی...' : 'فعال‌سازی حساب' }}
                </button>
            </form>
        </div>
    </div>
</template>
