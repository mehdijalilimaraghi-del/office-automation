<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import { Monitor, ArrowLeft, ShieldCheck } from '@lucide/vue';
const props = defineProps<{ discovery: { id: number; expires_at: string; completed_at: string | null; device_count: number | null }; protocolUrl: string | null }>();
const expired = ref(false);
const failed = ref(false);
let timer: number | undefined;
const controller = new AbortController();
onMounted(() => {
    if (!props.protocolUrl) return;
    timer = window.setInterval(async () => {
        try {
            const response = await fetch(route('telephony.discoveries.status', props.discovery.id), { headers: { Accept: 'application/json' }, signal: controller.signal });
            if (!response.ok) { failed.value = true; return; }
            const data = await response.json() as { complete: boolean; expired: boolean };
            failed.value = false;
            if (data.complete) { window.clearInterval(timer); router.visit(route('telephony.index')); }
            else if (data.expired) { expired.value = true; window.clearInterval(timer); }
        } catch { if (!controller.signal.aborted) failed.value = true; }
    }, 3000);
});
onUnmounted(() => { controller.abort(); window.clearInterval(timer); });
</script>
<template>
<Head title="بررسی کامپیوتر مودم" />
<AuthenticatedLayout>
<template #header><h1 class="text-xl font-bold">بررسی کامپیوتر مودم</h1></template>
<main dir="rtl" class="mx-auto max-w-3xl space-y-5 p-4 sm:p-6">
    <Link :href="route('telephony.index')" class="inline-flex items-center gap-2 text-sm font-semibold text-indigo-700"><ArrowLeft class="size-4" />بازگشت به تلفن سازمانی</Link>
    <section class="space-y-5 rounded-2xl border bg-white p-5 sm:p-8">
        <Monitor class="size-10 text-indigo-600" /><h2 class="text-lg font-bold">این مرحله را روی کامپیوتری که مودم دارد انجام دهید</h2>
        <ol class="list-inside list-decimal space-y-3 text-sm leading-7 text-slate-600"><li>برنامه واسط مودم را با نشانی همین سامانه نصب کنید.</li><li>دکمه زیر را بزنید و بازشدن برنامه را تأیید کنید.</li><li>در برنامه، مودم را انتخاب کنید؛ بررسی اولیه خودکار اجرا می‌شود و نتیجه را همان‌جا می‌بینید. سپس گزارش را ارسال کنید.</li></ol>
        <a v-if="protocolUrl && !expired" :href="protocolUrl" class="inline-flex min-h-11 items-center rounded-xl bg-indigo-700 px-5 py-3 text-sm font-semibold text-white">بازکردن برنامه واسط مودم</a>
        <p v-if="protocolUrl && !expired" role="status" class="text-sm text-slate-500">منتظر گزارش برنامه واسط… اعتبار این درخواست ۱۰ دقیقه است.</p>
        <p v-else role="status" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">این درخواست دیگر فعال نیست. به صفحه قبل بازگردید و درخواست تازه ایجاد کنید.</p>
        <p v-if="failed" role="alert" class="text-sm text-red-700">دریافت وضعیت ممکن نشد؛ اتصال به سامانه را بررسی کنید.</p>
        <p class="flex items-start gap-2 rounded-xl bg-slate-50 p-4 text-xs leading-6 text-slate-600"><ShieldCheck class="mt-1 size-4 shrink-0" />این درخواست فقط گزارش شناسایی و بررسی مودم را دریافت می‌کند. اتصال آزمایشی به خط یا مکالمه خودکار اجرا نمی‌شود.</p>
    </section>
</main>
</AuthenticatedLayout>
</template>
