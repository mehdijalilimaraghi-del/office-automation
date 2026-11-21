<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Phone, Monitor, Search, CheckCircle2, Info } from '@lucide/vue';
defineProps<{ modems: { id: number; name: string; computer_name: string; port: string | null; driver_status: string; voice_advertised: boolean; probe_status: string; last_seen_at: string; fresh: boolean }[]; selectedId: string | null }>();
const discovery = useForm({});
const selection = useForm({ modem_id: 0 });
function select(id: number): void { selection.modem_id = id; selection.put(route('telephony.modems.select'), { preserveScroll: true }); }
</script>
<template>
<Head title="تلفن سازمانی" />
<AuthenticatedLayout>
<template #header><h1 class="text-xl font-bold">تلفن سازمانی</h1></template>
<main dir="rtl" class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6">
    <section class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border bg-white p-5 sm:p-6">
        <div class="flex items-center gap-3"><span class="rounded-2xl bg-indigo-50 p-3 text-indigo-600"><Phone class="size-7" /></span><div><h2 class="text-lg font-bold">اتصال خط تلفن شرکت</h2><p class="mt-1 text-sm text-slate-600">مودم‌های کامپیوتر واسط را شناسایی و برای آزمون انتخاب کنید.</p></div></div>
        <button type="button" :disabled="discovery.processing" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-indigo-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" @click="discovery.post(route('telephony.discoveries.store'))"><Search class="size-4" />بررسی کامپیوتر مودم</button>
    </section>
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-white px-5 py-4"><p class="text-sm text-slate-600">برنامه واسط را فقط روی کامپیوتر دارای مودم نصب کنید.</p><a :href="route('telephony.helper')" class="text-sm font-semibold text-indigo-700">دریافت برنامه واسط ویندوز</a></div>
    <section class="grid gap-3 sm:grid-cols-3" aria-label="مراحل راه‌اندازی">
        <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4"><p class="font-semibold text-indigo-900">۱. شناسایی و بررسی مودم</p><p class="mt-2 text-xs leading-6 text-indigo-800">با برنامه واسط روی کامپیوتر متصل به خط</p></div>
        <div class="rounded-xl border bg-white p-4"><p class="font-semibold">۲. آزمون مکالمه دوطرفه</p><p class="mt-2 text-xs leading-6 text-slate-500">در واسط، با اجازه شما و یک تماس ورودی</p></div>
        <div class="rounded-xl border bg-white p-4"><p class="font-semibold">۳. داخلی‌ها و مسیر تماس</p><p class="mt-2 text-xs leading-6 text-slate-500">پس از اتصال به مرکز تلفن آزمایشی</p></div>
    </section>
    <p class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-7 text-amber-950"><Info class="mt-1 size-5 shrink-0" />اعلام قابلیت صوت توسط مودم، تأیید مکالمه دوطرفه نیست. انتخاب مودم خط را فعال نمی‌کند. آزمون عملی تماس در واسط، فقط با تأیید صریح شما شروع می‌شود.</p>
    <p v-if="selection.errors.modem_id" role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ selection.errors.modem_id }}</p>
    <section class="rounded-2xl border bg-white p-5 sm:p-6">
        <h2 class="font-bold">مودم‌های شناسایی‌شده</h2>
        <div v-if="!modems.length" class="py-12 text-center"><Monitor class="mx-auto size-12 text-slate-300" /><h3 class="mt-4 font-semibold">هنوز گزارشی از کامپیوتر مودم دریافت نشده است</h3><p class="mx-auto mt-2 max-w-lg text-sm leading-7 text-slate-500">این صفحه را روی کامپیوتری که مودم دارد باز کنید و «بررسی کامپیوتر مودم» را بزنید.</p></div>
        <ul v-else class="mt-4 grid gap-4 lg:grid-cols-2">
            <li v-for="modem in modems" :key="modem.id" class="space-y-4 rounded-xl border p-4">
                <div class="flex items-start justify-between gap-3"><div class="min-w-0"><h3 class="break-words font-semibold"><bdi>{{ modem.name }}</bdi></h3><p class="mt-2 text-xs text-slate-500">کامپیوتر: <bdi>{{ modem.computer_name }}</bdi> · درگاه: <bdi>{{ modem.port || 'شناسایی نشده' }}</bdi></p></div><CheckCircle2 v-if="Number(selectedId) === Number(modem.id)" class="size-5 shrink-0 text-indigo-600" /></div>
                <p class="rounded-lg p-3 text-sm" :class="modem.voice_advertised && modem.fresh && modem.driver_status === 'OK' ? 'bg-indigo-50 text-indigo-900' : 'bg-slate-100 text-slate-700'">{{ !modem.fresh ? 'گزارش قدیمی؛ بررسی دوباره لازم است' : modem.driver_status !== 'OK' || !modem.port ? 'مودم یا درایور آماده نیست' : modem.voice_advertised ? 'قابلیت صوت اعلام شده؛ نیازمند آزمون مکالمه' : modem.probe_status === 'failed' ? 'بررسی درگاه موفق نبود؛ برنامه واسط را بررسی کنید' : 'قابلیت صوت هنوز تأیید نشده است' }}</p>
                <div class="flex flex-wrap items-center justify-between gap-3"><span class="text-xs text-slate-500">{{ new Intl.DateTimeFormat('fa-IR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(modem.last_seen_at)) }}</span><button v-if="modem.voice_advertised && modem.fresh && modem.driver_status === 'OK' && modem.port" type="button" :disabled="selection.processing" class="rounded-lg border border-indigo-200 px-3 py-2 text-sm font-semibold text-indigo-700 disabled:opacity-50" @click="select(modem.id)">{{ Number(selectedId) === Number(modem.id) ? 'انتخاب‌شده برای آزمون' : 'انتخاب برای آزمون مکالمه' }}</button></div>
            </li>
        </ul>
    </section>
</main>
</AuthenticatedLayout>
</template>
