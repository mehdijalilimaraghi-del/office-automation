<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TelephonyNav from '@/Components/TelephonyNav.vue';
import { Download, Plus, Trash2, Volume2 } from '@lucide/vue';
type Menu = { enabled: boolean; timeout_seconds: number; retries: number; operator_number: string | null; choices: { digit: string; number: string }[]; audio_name: string | null };
const props = defineProps<{ menu: Menu; extensions: { number: string; label: string }[] }>();
const form = useForm({ enabled: props.menu.enabled, timeout_seconds: props.menu.timeout_seconds, retries: props.menu.retries, operator_number: props.menu.operator_number, choices: props.menu.choices.map(choice => ({ ...choice })), audio: null as File | null });
function add(): void {
    const digit = Array.from({ length: 10 }, (_, index) => String(index)).find(value => !form.choices.some(choice => choice.digit === value));
    if (digit !== undefined) form.choices.push({ digit, number: '' });
}
function fileChanged(event: Event): void { form.audio = (event.target as HTMLInputElement).files?.[0] ?? null; }
</script>
<template>
<Head title="راهنمای صوتی تلفن" />
<AuthenticatedLayout>
<template #header><h1 class="text-xl font-bold">تلفن سازمانی</h1></template>
<main dir="rtl" class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6">
    <TelephonyNav active="ivr" />
    <header class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border bg-white p-6"><div class="flex items-center gap-4"><Volume2 class="size-8 text-indigo-700" /><div><h2 class="text-lg font-bold">راهنمای صوتی تماس‌گیرنده</h2><p class="mt-2 text-sm text-slate-600">پیام خوشامدگویی و مقصد کلیدهای تلفن را تنظیم کنید.</p></div></div><a v-if="extensions.length" :href="route('telephony.configuration.download')" class="inline-flex min-h-11 items-center gap-2 rounded-xl border px-4 text-sm font-semibold"><Download class="size-4" />دریافت بسته تنظیمات</a></header>
    <form class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]" @submit.prevent="form.post(route('telephony.ivr.save'), { forceFormData: true, preserveScroll: true })">
        <div class="space-y-6">
            <section class="space-y-5 rounded-2xl border bg-white p-6">
                <div class="flex items-center justify-between gap-4"><h3 class="font-bold">پیام راهنما</h3><label class="flex items-center gap-2 text-sm"><input v-model="form.enabled" type="checkbox" class="rounded border-slate-300" />فعال باشد</label></div>
                <label class="block text-sm font-semibold">فایل صوتی WAV<input type="file" accept=".wav,audio/wav,audio/x-wav" class="mt-3 block w-full rounded-xl border p-3 text-sm" @change="fileChanged" /></label>
                <p class="text-xs leading-6 text-slate-500">{{ form.audio?.name ?? menu.audio_name ?? 'هنوز فایل صوتی انتخاب نشده است.' }} — فایل PCM تا ۲۰ مگابایت؛ در واسط، صدا به قالب تلفنی تبدیل می‌شود.</p>
                <p class="rounded-xl bg-slate-50 p-4 text-sm leading-7 text-slate-600">نمونه متن: «به شرکت خوش آمدید. شماره داخلی را وارد کنید؛ برای دبیرخانه عدد ۱ و برای اپراتور عدد صفر را فشار دهید.» پیام ضبط‌شده باید با کلیدهای زیر هماهنگ باشد.</p>
            </section>
            <section class="space-y-5 rounded-2xl border bg-white p-6">
                <div class="flex items-center justify-between"><h3 class="font-bold">کلیدها و مقصد تماس</h3><button type="button" :disabled="form.choices.length >= 10" class="inline-flex min-h-11 items-center gap-2 rounded-xl border px-3 text-sm disabled:opacity-50" @click="add"><Plus class="size-4" />افزودن کلید</button></div>
                <p v-if="!form.choices.length" class="rounded-xl bg-slate-50 p-5 text-sm leading-7 text-slate-500">کلیدی تعریف نشده است. تماس‌گیرنده می‌تواند شماره کامل داخلی را وارد کند.</p>
                <div v-for="(choice, index) in form.choices" :key="index" class="flex flex-wrap items-end gap-3 rounded-xl border bg-slate-50 p-4">
                    <label class="text-sm">کلید<select v-model="choice.digit" class="mt-2 block w-20 rounded-xl border-slate-300"><option v-for="digit in 10" :key="digit" :value="String(digit-1)">{{ digit-1 }}</option></select></label>
                    <label class="min-w-40 flex-1 text-sm">مقصد<select v-model="choice.number" required class="mt-2 block w-full rounded-xl border-slate-300"><option value="" disabled>انتخاب داخلی</option><option v-for="extension in extensions" :key="extension.number" :value="extension.number">{{ extension.number }} — {{ extension.label }}</option></select></label>
                    <button type="button" :aria-label="'حذف کلید '+choice.digit" class="min-h-11 rounded-xl border border-red-100 p-3 text-red-700" @click="form.choices.splice(index, 1)"><Trash2 class="size-4" /></button>
                </div>
            </section>
        </div>
        <aside class="space-y-5 rounded-2xl border bg-white p-6">
            <h3 class="font-bold">انتظار و پاسخ‌گویی</h3>
            <label class="block text-sm">فرصت ورود شماره (ثانیه)<input v-model.number="form.timeout_seconds" type="number" min="3" max="30" required class="mt-2 w-full rounded-xl border-slate-300" /></label>
            <label class="block text-sm">تعداد تلاش<input v-model.number="form.retries" type="number" min="1" max="3" required class="mt-2 w-full rounded-xl border-slate-300" /></label>
            <label class="block text-sm">داخلی اپراتور<select v-model="form.operator_number" :required="form.enabled" class="mt-2 w-full rounded-xl border-slate-300"><option :value="null">انتخاب داخلی</option><option v-for="extension in extensions" :key="extension.number" :value="extension.number">{{ extension.number }} — {{ extension.label }}</option></select></label>
            <p class="text-xs leading-6 text-slate-500">پس از پایان فرصت یا ورود نامعتبر، تماس به اپراتور هدایت می‌شود. تغییرات پس از اجرای بسته روی مرکز تلفن اعمال می‌شوند.</p>
            <ul v-if="Object.keys(form.errors).length" role="alert" class="space-y-2 rounded-xl bg-red-50 p-3 text-sm text-red-700"><li v-for="(error, field) in form.errors" :key="field">{{ error }}</li></ul>
            <button :disabled="form.processing" class="min-h-11 w-full rounded-xl bg-indigo-700 px-4 py-3 font-semibold text-white disabled:opacity-50">{{ form.processing ? 'در حال ذخیره…' : 'ذخیره راهنمای صوتی' }}</button>
            <p v-if="form.progress" role="status" class="text-sm text-indigo-700">بارگذاری: {{ form.progress.percentage }}٪</p>
        </aside>
    </form>
</main>
</AuthenticatedLayout>
</template>
