<script setup lang="ts">
import JalaliDatePicker from '@/Components/JalaliDatePicker.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { onBeforeUnmount, onMounted, ref } from 'vue';

type Kind = '' | 'digital' | 'physical';
interface ArchiveRecord {
    id: number;
    title: string;
    document_type: string;
    archive_kind: Exclude<Kind, ''>;
    archive_location: string | null;
    retain_until: string | null;
}
const props = defineProps<{
    records: { data: ArchiveRecord[]; prev_page_url: string | null; next_page_url: string | null };
    filters: { search?: string; kind?: Kind };
    canCreate: boolean;
    approvalExceptions: string[] | null;
}>();

const approvalForm = useForm({ categories: props.approvalExceptions?.join('\n') ?? '' });
const filters = useForm({ search: props.filters.search ?? '', kind: props.filters.kind ?? '' });
const form = useForm({
    title: '', document_type: '', description: '', archive_kind: 'digital',
    archive_location: '', retain_until: null as string | null, file: null as File | null, scanner_upload_token: '',
});
interface ScanSession { token: string; upload_url: string; status_url: string }
const scanner = ref<ScanSession | null>(null);
const scanState = ref<'preparing' | 'ready' | 'scanning' | 'error'>('preparing');
const scanPreview = ref('');
const scanName = ref('');
const scannerAbort = new AbortController();
async function prepareScan(): Promise<void> {
    scanState.value = 'preparing';
    try {
        const { data } = await axios.post<ScanSession>(route('archive.scanner.start'), {}, { signal: scannerAbort.signal });
        scanner.value = data;
        scanState.value = 'ready';
    } catch {
        scanState.value = 'error';
    }
}
onMounted(() => { if (props.canCreate) void prepareScan(); });
onBeforeUnmount(() => scannerAbort.abort());

async function startScan(): Promise<void> {
    const session = scanner.value;
    if (!session || scanState.value !== 'ready') return;
    scanPreview.value = '';
    scanName.value = '';
    form.scanner_upload_token = '';
    scanState.value = 'scanning';
    window.open('officeautomation-scanner://scan?uploadUrl=' + encodeURIComponent(session.upload_url), '_blank');
    try {
        for (let attempt = 0; attempt < 300 && !scannerAbort.signal.aborted; attempt += 1) {
            await new Promise(resolve => window.setTimeout(resolve, 2000));
            const { data } = await axios.get(session.status_url, { signal: scannerAbort.signal });
            if (data.status === 'ready') {
                scanPreview.value = data.preview_url;
                scanName.value = data.file_name;
                return;
            }
        }
        scanState.value = 'error';
    } catch {
        scanState.value = 'error';
    }
}

function acceptScan(): void {
    if (!scanner.value || !scanPreview.value) return;
    form.scanner_upload_token = scanner.value.token;
    form.file = null;
}

function clearScan(): void {
    form.scanner_upload_token = '';
    scanner.value = null;
    scanPreview.value = '';
    scanName.value = '';
    void prepareScan();
}

function register(event: Event): void {
    const element = event.currentTarget as HTMLFormElement;
    form.post(route('archive.store'), {
        preserveScroll: true,
        onSuccess: () => {
            element.reset();
            form.reset();
            if (scanPreview.value) clearScan();
        },
    });
}
</script>

<template>
    <Head title="آرشیو اسناد" />
    <AuthenticatedLayout>
        <template #header><h1 class="text-xl font-semibold">آرشیو اسناد</h1></template>
        <div dir="rtl" class="mx-auto grid max-w-6xl gap-6 p-4 sm:p-6">
            <form v-if="canCreate" class="grid gap-4 rounded-xl border bg-white p-4 sm:grid-cols-2" @submit.prevent="register">
                <h2 class="font-semibold sm:col-span-2">ثبت مدرک</h2>
                <label class="grid gap-1">عنوان<input v-model="form.title" class="rounded-md border p-2" maxlength="255" required /></label>
                <label class="grid gap-1">دسته مدرک<input v-model="form.document_type" class="rounded-md border p-2" maxlength="100" required /></label>
                <label class="grid gap-1">نوع مدرک
                    <select v-model="form.archive_kind" class="rounded-md border p-2">
                        <option value="digital">دیجیتال</option><option value="physical">فیزیکی</option>
                    </select>
                </label>
                <label v-if="form.archive_kind === 'physical'" class="grid gap-1">محل نگهداری اصل
                    <input v-model="form.archive_location" class="rounded-md border p-2" placeholder="بایگانی، قفسه، زونکن" maxlength="255" required />
                </label>
                <div class="grid gap-1" role="group" aria-labelledby="retain-until-label">
                    <span id="retain-until-label">تاریخ پایان نگهداری</span>
                    <JalaliDatePicker v-model="form.retain_until" />
                </div>
                <label class="grid gap-1">توضیحات<textarea v-model="form.description" class="rounded-md border p-2" maxlength="5000" /></label>
                <label v-if="!form.scanner_upload_token" class="grid gap-1">{{ form.archive_kind === 'physical' ? 'تصویر یا فایل اسکن اصل' : 'فایل مدرک' }}
                    <input type="file" class="w-full rounded-md border p-2" :accept="form.archive_kind === 'physical' ? '.pdf,.jpg,.jpeg,.png,.tif,.tiff' : '.pdf,.jpg,.jpeg,.png,.tif,.tiff,.doc,.docx,.xls,.xlsx'" required
                        @change="form.file = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                </label>
                <div class="space-y-2 rounded-md border p-3 sm:col-span-2">
                    <div class="flex flex-wrap items-center gap-3">
                        <button v-if="scanState === 'ready'" type="button" class="rounded-md border px-3 py-1" :disabled="form.processing" @click="startScan">اسکن با NAPS2</button>
                        <button v-if="scanState === 'error'" type="button" class="rounded-md border px-3 py-1" @click="clearScan">آماده‌سازی دوباره اسکنر</button>
                        <p v-if="scanState === 'preparing'" role="status" class="text-sm">در حال آماده‌سازی اسکنر…</p>
                        <p v-if="scanState === 'scanning' && !scanPreview" role="status" class="text-sm">در انتظار دریافت فایل از NAPS2…</p>
                        <p v-if="scanState === 'error'" role="alert" class="text-sm text-red-700">دریافت اسکن انجام نشد. اتصال اسکنر و اجازه اجرای برنامه را بررسی کنید.</p>
                    </div>
                    <div v-if="scanPreview" class="flex flex-wrap items-center gap-3">
                        <a :href="scanPreview" target="_blank" rel="noopener" class="text-indigo-700 underline">پیش‌نمایش {{ scanName }}</a>
                        <button v-if="!form.scanner_upload_token" type="button" class="rounded-md border px-3 py-1" @click="acceptScan">استفاده از اسکن</button>
                        <span v-else role="status" class="text-sm text-green-700">اسکن برای ثبت آماده است.</span>
                        <button type="button" class="rounded-md border px-3 py-1" :disabled="form.processing" @click="clearScan">حذف اسکن</button>
                    </div>
                </div>
                <ul v-if="form.hasErrors" role="alert" class="list-inside list-disc text-sm text-red-700 sm:col-span-2">
                    <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
                </ul>
                <div class="sm:col-span-2">
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-700 px-4 py-2 text-white disabled:opacity-60">
                        {{ form.processing ? 'در حال ثبت…' : 'ثبت در آرشیو' }}
                    </button>
                </div>
            </form>

            <details v-if="approvalExceptions !== null" class="rounded-xl border bg-white p-4">
                <summary class="cursor-pointer font-semibold">سیاست تأیید خروج اصل مدرک</summary>
                <form class="mt-3 grid gap-3" @submit.prevent="approvalForm.put(route('archive.approval-policy.update'), { preserveScroll: true })">
                    <label class="grid gap-1">
                        دسته‌های معاف از تأیید
                        <textarea v-model="approvalForm.categories" rows="4" maxlength="5000" class="rounded-md border p-2"
                            aria-describedby="archive-approval-help" />
                    </label>
                    <p id="archive-approval-help" class="text-sm text-slate-600">نام هر دسته را در یک خط وارد کنید. سایر دسته‌ها نیاز به تأیید دارند. این تنظیم برای درخواست‌های جدید اعمال می‌شود.</p>
                    <ul v-if="approvalForm.hasErrors" role="alert" class="text-sm text-red-700">
                        <li v-for="(error, field) in approvalForm.errors" :key="field">{{ error }}</li>
                    </ul>
                    <button type="submit" :disabled="approvalForm.processing" class="justify-self-start rounded-md bg-indigo-700 px-4 py-2 text-white disabled:opacity-60">ذخیره سیاست تأیید</button>
                </form>
            </details>
            <section class="grid gap-4 rounded-xl border bg-white p-4">
                <form class="grid gap-3 sm:grid-cols-[1fr_auto_auto]" @submit.prevent="filters.get(route('archive.index'), { preserveState: true, preserveScroll: true })">
                    <label class="grid gap-1">جست‌وجوی مدارک<input v-model="filters.search" maxlength="100" class="rounded-md border p-2" /></label>
                    <label class="grid gap-1">نوع مدرک
                        <select v-model="filters.kind" class="rounded-md border p-2">
                            <option value="">همه</option><option value="digital">دیجیتال</option><option value="physical">فیزیکی</option>
                        </select>
                    </label>
                    <button type="submit" :disabled="filters.processing" class="self-end rounded-md border px-4 py-2">جست‌وجو</button>
                </form>
                <p v-if="!records.data.length" class="text-sm text-slate-600">مدرکی پیدا نشد.</p>
                <ul v-else class="divide-y">
                    <li v-for="record in records.data" :key="record.id" class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div>
                            <Link :href="route('archive.show', record.id)" class="font-semibold text-indigo-700 hover:underline">{{ record.title }}</Link>
                            <p class="text-sm text-slate-600">{{ record.document_type }} · {{ record.archive_kind === 'physical' ? 'فیزیکی' : 'دیجیتال' }}</p>
                            <p v-if="record.archive_location" class="text-sm text-slate-600">{{ record.archive_location }}</p>
                        </div>
                        <a :href="route('documents.download', record.id)" class="text-sm font-semibold text-indigo-700">دریافت فایل {{ record.title }}</a>
                    </li>
                </ul>
                <nav v-if="records.prev_page_url || records.next_page_url" aria-label="صفحه‌بندی آرشیو" class="flex gap-4">
                    <Link v-if="records.prev_page_url" :href="records.prev_page_url" preserve-state>صفحه قبل</Link>
                    <Link v-if="records.next_page_url" :href="records.next_page_url" preserve-state>صفحه بعد</Link>
                </nav>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
