<script setup lang="ts">
import TelephonyNav from '@/Components/TelephonyNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Monitor, Link2, RefreshCw, Copy, ShieldCheck, Unplug, Phone } from '@lucide/vue';

type Device = { id: number; name: string; computer_name: string | null; status: string; runtime_state: string | null; last_seen_at: string | null; online: boolean; configuration_pending: boolean; error_code: string | null };
type Pairing = { device_id: number; name: string; code: string; expires_at: string };
const props = defineProps<{ devices: Device[]; pairing: Pairing | null; configurationReady: boolean; errors?: { pairing?: string } }>();
const form = useForm({ name: '' });
const copied = ref(false);
const clipboardError = ref('');
const clock = ref(Date.now());
let timer: ReturnType<typeof setInterval> | undefined;
onMounted(() => { timer = setInterval(() => { clock.value = Date.now(); router.reload({ only: ['devices', 'configurationReady'] }); }, 15000); });
onBeforeUnmount(() => { if (timer) clearInterval(timer); });
watch(() => props.pairing?.code, () => { copied.value = false; clipboardError.value = ''; });
const pairingUsed = computed(() => props.devices.some(device => device.id === props.pairing?.device_id && device.status === 'linked'));
const expired = computed(() => !!props.pairing && new Date(props.pairing.expires_at).getTime() <= clock.value);
const online = computed(() => props.devices.filter(device => device.online).length);
const pending = computed(() => props.devices.filter(device => device.status === 'linked' && device.configuration_pending).length);
function submit(): void { form.post(route('telephony.devices.store'), { preserveScroll: true, onSuccess: () => form.reset() }); }
async function copy(): Promise<void> {
    if (!props.pairing || expired.value) return;
    clipboardError.value = '';
    try { await navigator.clipboard.writeText(props.pairing.code); copied.value = true; }
    catch { clipboardError.value = 'کپی خودکار ممکن نشد؛ کد را انتخاب و کپی کنید.'; }
}
function revoke(device: Device): void {
    if (window.confirm('اتصال «' + device.name + '» لغو شود؟ دریافت تنظیمات متوقف می‌شود؛ تماس جاری از راه دور قطع نمی‌شود.')) {
        router.post(route('telephony.devices.revoke', device.id), {}, { preserveScroll: true });
    }
}
function regenerate(device: Device): void { router.post(route('telephony.devices.pairing', device.id), {}, { preserveScroll: true }); }
function state(device: Device): string {
    if (device.status === 'revoked') return 'اتصال لغو شده';
    if (device.status === 'awaiting_pairing') return 'منتظر اتصال برنامه';
    if (!device.online) return 'ارتباط برقرار نیست';
    return ({ ready: 'آماده پاسخ‌گویی', busy: 'در حال مکالمه', stopped: 'پاسخ‌گویی متوقف', error: 'نیازمند بررسی' } as Record<string, string>)[device.runtime_state ?? ''] ?? 'متصل';
}
function date(value: string): string { return new Intl.DateTimeFormat('fa-IR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)); }
</script>

<template>
    <Head title="اتصال کامپیوتر تلفن" />
    <AuthenticatedLayout>
        <template #header><h1 class="text-xl font-bold">اتصال کامپیوتر تلفن</h1></template>
        <main dir="rtl" class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6">
            <TelephonyNav active="devices" />
            <header class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border bg-white p-6">
                <div class="flex items-center gap-4">
                    <span class="rounded-2xl bg-indigo-50 p-3 text-indigo-700"><Monitor class="size-7" /></span>
                    <div><h2 class="text-lg font-bold">اتصال برنامه ویندوز به اتوماسیون</h2><p class="mt-2 text-sm leading-7 text-slate-600">داخلی‌ها و پیام راهنما را اینجا مدیریت کنید؛ برنامه متصل، تنظیمات را دریافت می‌کند.</p></div>
                </div>
                <button type="button" class="inline-flex min-h-11 items-center gap-2 rounded-xl border px-4 text-sm" @click="router.reload({ only: ['devices', 'configurationReady'] })"><RefreshCw class="size-4" />به‌روزرسانی وضعیت</button>
            </header>
            <section class="grid gap-3 sm:grid-cols-3" aria-label="وضعیت اتصال">
                <div class="rounded-xl border bg-white p-5"><p class="text-sm text-slate-500">کامپیوترهای تعریف‌شده</p><p class="mt-2 text-2xl font-bold">{{ devices.length.toLocaleString('fa-IR') }}</p></div>
                <div class="rounded-xl border bg-white p-5"><p class="text-sm text-slate-500">ارتباط برقرار</p><p class="mt-2 text-2xl font-bold text-emerald-700">{{ online.toLocaleString('fa-IR') }}</p></div>
                <div class="rounded-xl border bg-white p-5"><p class="text-sm text-slate-500">تنظیمات منتظر اعمال</p><p class="mt-2 text-2xl font-bold text-amber-700">{{ pending.toLocaleString('fa-IR') }}</p></div>
            </section>
            <div v-if="!configurationReady" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950"><p>برای آماده‌شدن تنظیمات مرکز تلفن، ابتدا حداقل یک داخلی فعال تعریف کنید.</p><Link :href="route('telephony.extensions.index')" class="font-semibold underline">مدیریت داخلی‌ها</Link></div>
            <p v-if="errors?.pairing" role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ errors.pairing }}</p>
            <section v-if="pairing && !pairingUsed" class="space-y-4 rounded-2xl border border-indigo-200 bg-indigo-50 p-6" aria-labelledby="pairing-title">
                <h3 id="pairing-title" class="font-bold text-indigo-950">کد اتصال «{{ pairing.name }}»</h3>
                <p class="text-sm leading-7 text-indigo-900">در برنامه ویندوز، بخش «اتصال به اتوماسیون» را باز کنید و آدرس سامانه و این کد یک‌بارمصرف را وارد کنید. کد تا {{ date(pairing.expires_at) }} اعتبار دارد.</p>
                <p v-if="expired" role="status" class="rounded-xl bg-white p-4 text-sm">اعتبار کد پایان یافته است؛ از فهرست پایین کد جدید بگیرید.</p>
                <div v-else class="flex flex-wrap items-center gap-3"><input :value="pairing.code" readonly dir="ltr" aria-label="کد یک‌بارمصرف اتصال" class="min-w-0 flex-1 rounded-xl border border-indigo-200 bg-white p-3 font-mono text-sm" @focus="($event.target as HTMLInputElement).select()" /><button type="button" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-indigo-700 px-4 text-sm font-semibold text-white" @click="copy"><Copy class="size-4" />{{ copied ? 'کپی شد' : 'کپی کد' }}</button></div>
                <p v-if="clipboardError" role="status" class="text-sm">{{ clipboardError }}</p>
            </section>
            <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <section class="space-y-4 rounded-2xl border bg-white p-5 sm:p-6">
                    <h3 class="font-bold">کامپیوترهای مرکز تلفن</h3>
                    <div v-if="!devices.length" class="py-12 text-center"><Monitor class="mx-auto size-12 text-slate-300" /><h4 class="mt-4 font-semibold">هنوز کامپیوتری متصل نشده است</h4><p class="mx-auto mt-2 max-w-md text-sm leading-7 text-slate-500">نام کامپیوتر مودم را در فرم بنویسید و کد اتصال بگیرید؛ سپس کد را در برنامه نصب‌شده وارد کنید.</p></div>
                    <article v-for="device in devices" :key="device.id" class="space-y-4 rounded-xl border p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3"><div><h4 class="font-semibold">{{ device.name }}</h4><p class="mt-2 text-xs text-slate-500">نام ویندوز: <bdi>{{ device.computer_name || 'هنوز دریافت نشده' }}</bdi></p></div><span class="rounded-full px-3 py-1 text-xs font-semibold" :class="device.online ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'">{{ state(device) }}</span></div>
                        <p v-if="device.status === 'linked'" class="rounded-lg bg-slate-50 p-3 text-sm" :class="device.configuration_pending ? 'text-amber-800' : 'text-emerald-800'">{{ !configurationReady ? 'تنظیمات مرکز تلفن هنوز آماده نیست.' : device.configuration_pending ? 'نسخه جدید تنظیمات هنوز روی این کامپیوتر اعمال نشده است.' : 'این کامپیوتر آخرین تنظیمات را اعمال کرده است.' }}</p>
                        <p v-if="device.error_code" role="status" class="rounded-lg bg-red-50 p-3 text-sm leading-7 text-red-800">برنامه ویندوز نیازمند بررسی است. جزئیات را در همان برنامه ببینید. <bdi class="font-mono text-xs">{{ device.error_code }}</bdi></p>
                        <div class="flex flex-wrap items-center justify-between gap-3"><p class="text-xs text-slate-500">{{ device.last_seen_at ? 'آخرین ارتباط: ' + date(device.last_seen_at) : 'هنوز ارتباطی ثبت نشده است.' }}</p><div class="flex gap-3"><button v-if="device.status !== 'linked'" type="button" class="min-h-11 rounded-lg border px-3 text-sm text-indigo-700" @click="regenerate(device)">دریافت کد جدید</button><button v-if="device.status !== 'revoked'" type="button" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-red-100 px-3 text-sm text-red-700" @click="revoke(device)"><Unplug class="size-4" />لغو اتصال</button></div></div>
                    </article>
                </section>
                <aside class="space-y-5">
                    <form class="space-y-4 rounded-2xl border bg-white p-6" @submit.prevent="submit"><h3 class="flex items-center gap-2 font-bold"><Link2 class="size-5 text-indigo-700" />اتصال کامپیوتر جدید</h3><label class="block text-sm font-semibold">نام کامپیوتر<input v-model="form.name" required maxlength="120" placeholder="مثلاً کامپیوتر تلفن دبیرخانه" class="mt-2 min-h-11 w-full rounded-xl border-slate-300 text-sm" /></label><p v-if="form.errors.name" role="alert" class="text-sm text-red-700">{{ form.errors.name }}</p><button :disabled="form.processing" class="min-h-11 w-full rounded-xl bg-indigo-700 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50">{{ form.processing ? 'در حال ایجاد کد…' : 'دریافت کد اتصال' }}</button></form>
                    <div class="space-y-3 rounded-2xl border bg-white p-5 text-sm leading-7 text-slate-600"><ShieldCheck class="size-6 text-indigo-700" /><p>کد اتصال ده دقیقه اعتبار دارد و فقط یک بار قابل استفاده است. هر کامپیوتر اتصال مستقل دارد و می‌توانید دسترسی آن را لغو کنید.</p><p class="flex items-start gap-2"><Phone class="mt-1 size-4 shrink-0" />تنظیمات هنگام مکالمه تغییر نمی‌کنند؛ تا پایان تماس منتظر می‌مانند.</p></div>
                </aside>
            </div>
        </main>
    </AuthenticatedLayout>
</template>
