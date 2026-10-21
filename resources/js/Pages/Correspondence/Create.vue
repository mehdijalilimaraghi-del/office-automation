<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

interface Department { id: number; name: string; }
interface ReplyOption { id: number; registration_number: string | null; subject: string; }
const props = defineProps<{ types: Array<{ value: string; label: string }>; departments: Department[]; repliesTo: ReplyOption[] }>();
const form = useForm({
    type: 'incoming',
    subject: '',
    body: '',
    sender_name: '',
    recipient_name: '',
    received_at: '',
    sent_at: '',
    department_id: '' as number | '',
    reply_to_id: '' as number | '',
});
</script>

<template>
    <Head title="ایجاد مکاتبه" />
    <AuthenticatedLayout>
        <template #header><div><h2 class="text-xl font-bold text-slate-900">ایجاد مکاتبه</h2><p class="mt-1 text-sm text-slate-500">پیش‌نویس ایجاد می‌شود و شماره رسمی پس از ثبت نهایی اختصاص می‌یابد.</p></div></template>
        <div class="p-4 sm:p-6">
            <form class="mx-auto max-w-4xl space-y-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" @submit.prevent="form.post(route('correspondence.store'))">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div><label for="type" class="mb-1.5 block text-sm font-semibold text-slate-700">نوع مکاتبه <span class="text-rose-600">*</span></label><select id="type" v-model="form.type" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"><option v-for="item in types" :key="item.value" :value="item.value">{{ item.label }}</option></select><InputError class="mt-1" :message="form.errors.type" /></div>
                    <div><label for="department_id" class="mb-1.5 block text-sm font-semibold text-slate-700">واحد مرتبط</label><select id="department_id" v-model="form.department_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"><option value="">بدون انتخاب</option><option v-for="item in departments" :key="item.id" :value="item.id">{{ item.name }}</option></select><InputError class="mt-1" :message="form.errors.department_id" /></div>
                    <div class="sm:col-span-2"><label for="subject" class="mb-1.5 block text-sm font-semibold text-slate-700">موضوع <span class="text-rose-600">*</span></label><input id="subject" v-model="form.subject" required maxlength="255" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" /><InputError class="mt-1" :message="form.errors.subject" /></div>
                    <div><label for="sender_name" class="mb-1.5 block text-sm font-semibold text-slate-700">فرستنده</label><input id="sender_name" v-model="form.sender_name" maxlength="255" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" /><InputError class="mt-1" :message="form.errors.sender_name" /></div>
                    <div><label for="recipient_name" class="mb-1.5 block text-sm font-semibold text-slate-700">گیرنده</label><input id="recipient_name" v-model="form.recipient_name" maxlength="255" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" /><InputError class="mt-1" :message="form.errors.recipient_name" /></div>
                    <div v-if="form.type === 'incoming'"><label for="received_at" class="mb-1.5 block text-sm font-semibold text-slate-700">تاریخ دریافت</label><input id="received_at" v-model="form.received_at" type="date" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" /><InputError class="mt-1" :message="form.errors.received_at" /></div>
                    <div v-if="form.type === 'outgoing'"><label for="sent_at" class="mb-1.5 block text-sm font-semibold text-slate-700">تاریخ ارسال</label><input id="sent_at" v-model="form.sent_at" type="date" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" /><InputError class="mt-1" :message="form.errors.sent_at" /></div>
                    <div class="sm:col-span-2"><label for="reply_to_id" class="mb-1.5 block text-sm font-semibold text-slate-700">پاسخ به مکاتبه ثبت‌شده</label><select id="reply_to_id" v-model="form.reply_to_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"><option value="">مکاتبه‌ای انتخاب نشده</option><option v-for="item in repliesTo" :key="item.id" :value="item.id">{{ item.registration_number ?? 'بدون شماره' }} — {{ item.subject }}</option></select><InputError class="mt-1" :message="form.errors.reply_to_id" /></div>
                    <div class="sm:col-span-2"><label for="body" class="mb-1.5 block text-sm font-semibold text-slate-700">متن مکاتبه</label><textarea id="body" v-model="form.body" rows="10" class="w-full rounded-xl border-slate-300 text-sm leading-7 focus:border-indigo-500 focus:ring-indigo-500" placeholder="متن پیش‌نویس را وارد کنید..." /><InputError class="mt-1" :message="form.errors.body" /></div>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-5"><Link :href="route('correspondence.index')" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">انصراف</Link><button type="submit" :disabled="form.processing" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">{{ form.processing ? 'در حال ذخیره...' : 'ذخیره پیش‌نویس' }}</button></div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
