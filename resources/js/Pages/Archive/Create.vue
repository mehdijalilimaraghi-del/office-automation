<script setup lang="ts">
import { computed, watch } from 'vue';
import ArchiveVisibilityFields from '@/Components/ArchiveVisibilityFields.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    departments: { id: number; name: string; categories: { name: string; approval_required: boolean }[]; locations: string[] }[];
    relatedDepartments: { id: number; name: string }[];
    centralDepartmentId: number | null;
    centralMode: boolean;
    sourceLetter: {
        id: number; title: string; description: string; sender: string | null; letter_number: string | null;
        registration_number: string | null; received_at: string | null; related_department_id: number | null;
        preferred_department_id: number | null; default_category: string | null; confidential: boolean;
        files: { name: string; purpose: string }[];
    } | null;
    audienceDepartments: { id: number; name: string }[];
    audienceRoles: { id: number; name: string }[];
    audienceManagers: number[];
}>();
const initialDepartment = props.centralMode
    ? props.departments[0]
    : props.departments.find(unit => Number(unit.id) === Number(props.sourceLetter?.preferred_department_id))
        ?? (props.departments.length === 1 ? props.departments[0] : undefined);
const initialCategory = initialDepartment?.categories.some(category => category.name === props.sourceLetter?.default_category)
    ? props.sourceLetter?.default_category ?? '' : '';
const form = useForm({
    archive_department_id: initialDepartment?.id ?? '' as number | string,
    correspondence_id: props.sourceLetter?.id ?? null,
    related_department_id: props.sourceLetter?.related_department_id ?? '' as number | string, archive_restricted: props.sourceLetter?.confidential ?? false,
    archive_visibility: props.sourceLetter?.confidential ? 'restricted' : 'department', audience_departments: [] as number[], audience_roles: [] as number[],
    title: props.sourceLetter?.title ?? '', document_type: initialCategory, description: props.sourceLetter?.description ?? '', archive_kind: 'digital',
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
onMounted(() => { if (!props.sourceLetter) void prepareScan(); });
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

const department = computed(() => props.departments.find(unit => Number(unit.id) === Number(form.archive_department_id)));
const locations = computed(() => department.value?.locations ?? []);
watch(() => form.archive_department_id, () => {
    if (!department.value?.categories.some(category => category.name === form.document_type)) {
        form.document_type = department.value?.categories.some(category => category.name === props.sourceLetter?.default_category)
            ? props.sourceLetter?.default_category ?? '' : '';
    }
    form.archive_location = '';
    form.related_department_id = props.sourceLetter?.related_department_id ?? '';
    form.archive_visibility = props.sourceLetter?.confidential ? 'restricted' : 'department';
    form.audience_departments = [];
    form.audience_roles = [];
});
function receivedDate(value: string): string {
    return new Intl.DateTimeFormat('fa-IR-u-ca-persian', { dateStyle: 'medium' }).format(new Date(value));
}
</script>
<template>
    <Head :title="centralMode ? 'ثبت سند سازمانی' : 'ثبت سند واحد'" />
    <AuthenticatedLayout>
        <template #header><h1 class="text-xl font-bold">{{ centralMode ? 'ثبت سند در بایگانی مرکزی سازمان' : 'ثبت سند در بایگانی واحد' }}</h1></template>
        <main dir="rtl" class="mx-auto max-w-4xl space-y-5 p-4 sm:p-6">
            <Link :href="route(centralMode ? 'archive.central.index' : 'archive.index')" class="text-sm font-semibold text-indigo-700">بازگشت به فهرست اسناد</Link>
            <p class="text-sm leading-7 text-slate-600">{{ centralMode ? 'اصل یا فایل این سند در بایگانی مرکزی نگهداری می‌شود. واحد مرتبط با سند را انتخاب کنید و دامنه مشاهده را مشخص کنید.' : 'سند در واحد انتخاب‌شده نگهداری می‌شود. دسته و محل را از تنظیمات همان واحد انتخاب کنید.' }}</p>
            <section v-if="sourceLetter" class="space-y-4 rounded-2xl border border-indigo-200 bg-indigo-50 p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-semibold text-indigo-950">ثبت بایگانی از نامه وارده</h2>
                    <Link :href="route('correspondence.show', sourceLetter.id)" class="text-sm font-semibold text-indigo-700 hover:underline">بازگشت به نامه بدون ثبت بایگانی</Link>
                </div>
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-indigo-700">فرستنده</dt><dd class="mt-1 font-medium">{{ sourceLetter.sender || 'ثبت نشده' }}</dd></div>
                    <div><dt class="text-indigo-700">شماره نامه / ثبت</dt><dd class="mt-1 font-medium"><bdi>{{ sourceLetter.letter_number || '—' }} / {{ sourceLetter.registration_number || '—' }}</bdi></dd></div>
                    <div v-if="sourceLetter.received_at"><dt class="text-indigo-700">تاریخ دریافت</dt><dd class="mt-1">{{ receivedDate(sourceLetter.received_at) }}</dd></div>
                </dl>
                <div class="border-t border-indigo-200 pt-3">
                    <h3 class="text-sm font-semibold">فایل‌های ثبت‌شده نامه</h3>
                    <ul class="mt-2 space-y-1 text-sm text-indigo-900"><li v-for="(file, index) in sourceLetter.files" :key="index"><bdi class="break-all">{{ file.name }}</bdi> · {{ file.purpose === 'correspondence_original' ? 'اصل نامه' : 'پیوست' }}</li></ul>
                    <p class="mt-2 text-xs leading-6 text-indigo-800">همین فایل‌ها در پرونده بایگانی استفاده می‌شوند. برای نگهداری اصل کاغذی، نوع مدرک را «فیزیکی» انتخاب و محل را مشخص کنید.</p>
                </div>
            </section>
            <form v-if="departments.length" class="grid gap-4 rounded-xl border bg-white p-4 sm:grid-cols-2" @submit.prevent="register">
                <h2 class="font-semibold sm:col-span-2">مشخصات سند</h2>
                <div v-if="centralMode" class="rounded-xl border border-indigo-100 bg-indigo-50 p-4 text-sm text-indigo-900 sm:col-span-2"><strong>بایگانی مرکزی سازمان</strong><p class="mt-1">واحد نگهدارنده: {{ department?.name }}</p></div>
                <label v-else class="grid gap-2 text-sm font-medium">واحد نگهدارنده
                    <select v-model="form.archive_department_id" required class="rounded-lg border-slate-300"><option value="">انتخاب واحد</option><option v-for="unit in departments" :key="unit.id" :value="unit.id">{{ unit.name }}</option></select>
                </label>
                <label v-if="Number(form.archive_department_id) === centralDepartmentId" class="grid gap-2 text-sm font-medium">واحد مرتبط با سند
                    <select v-model="form.related_department_id" required class="rounded-lg border-slate-300"><option value="">انتخاب واحد مرتبط</option><option v-for="unit in relatedDepartments" :key="unit.id" :value="unit.id">{{ unit.name }}</option></select>
                </label>
                <label class="grid gap-1">عنوان<input v-model="form.title" class="rounded-md border p-2" maxlength="255" required /></label>
                <label class="grid gap-2 text-sm font-medium">دسته سند<select v-model="form.document_type" required class="rounded-lg border-slate-300"><option value="">انتخاب دسته</option><option v-for="category in department?.categories ?? []" :key="category.name" :value="category.name">{{ category.name }}</option></select></label>
                <p v-if="department && !department.categories.length" role="status" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900 sm:col-span-2">مسئول بایگانی این واحد باید ابتدا دسته‌های اسناد را در تنظیمات بایگانی تعریف کند.</p>
                <p v-if="sourceLetter?.default_category && department && !department.categories.some(category => category.name === sourceLetter?.default_category)" role="status" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900 sm:col-span-2">دسته پیش‌فرض مکاتبات در بایگانی انتخاب‌شده تعریف نشده است. دسته مناسب را از فهرست همین بایگانی انتخاب کنید.</p>
                <label class="grid gap-1">نوع مدرک
                    <select v-model="form.archive_kind" class="rounded-md border p-2">
                        <option value="digital">دیجیتال</option><option value="physical">فیزیکی</option>
                    </select>
                </label>
                <label v-if="form.archive_kind === 'physical'" class="grid gap-1">محل نگهداری اصل
                    <select v-if="locations.length" v-model="form.archive_location" required class="rounded-md border p-2">
                        <option value="">انتخاب محل</option>
                        <option v-for="location in locations" :key="location" :value="location">{{ location }}</option>
                    </select>
<p v-if="!locations.length" role="status" class="text-sm text-amber-800">برای ثبت اصل فیزیکی، مسئول بایگانی باید ابتدا محل نگهداری تعریف کند.</p>
                </label>
                <div class="rounded-xl border bg-slate-50/50 p-4 sm:col-span-2">
                    <p v-if="sourceLetter?.confidential" class="text-sm text-slate-700">این نامه محرمانه است. دسترسی افراد مشخص و مجوز مشاهده نامه مبدأ رعایت می‌شود.</p>
                    <ArchiveVisibilityFields v-else v-model:scope="form.archive_visibility" v-model:department-ids="form.audience_departments" v-model:role-ids="form.audience_roles"
                        :departments="audienceDepartments" :roles="audienceRoles" :can-broaden="audienceManagers.map(Number).includes(Number(form.archive_department_id))" />
                </div>
                <label class="grid gap-1">توضیحات<textarea v-model="form.description" class="rounded-md border p-2" maxlength="5000" /></label>
                <label v-if="!sourceLetter && !form.scanner_upload_token" class="grid gap-1">{{ form.archive_kind === 'physical' ? 'تصویر یا فایل اسکن اصل' : 'فایل مدرک' }}
                    <span class="flex min-h-12 items-center gap-3 rounded-lg border border-slate-300 p-2 focus-within:ring-2 focus-within:ring-indigo-500">
                        <span class="shrink-0 rounded-md bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700">انتخاب فایل</span>
                        <bdi class="min-w-0 truncate text-sm text-slate-500">{{ form.file?.name ?? 'فایلی انتخاب نشده است' }}</bdi>
                        <input type="file" class="sr-only" :accept="form.archive_kind === 'physical' ? '.pdf,.jpg,.jpeg,.png,.tif,.tiff' : '.pdf,.jpg,.jpeg,.png,.tif,.tiff,.doc,.docx,.xls,.xlsx'" required
                            @change="form.file = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                    </span>
                </label>
                <div v-if="!sourceLetter" class="space-y-2 rounded-md border p-3 sm:col-span-2">
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
                    <button type="submit" :disabled="form.processing || !form.document_type || (form.archive_kind === 'physical' && !locations.length)" class="rounded-md bg-indigo-700 px-4 py-2 text-white disabled:opacity-60">
                        {{ form.processing ? 'در حال ثبت…' : 'ثبت در آرشیو' }}
                    </button>
                </div>
            </form>


            <p v-if="!departments.length" class="rounded-xl border bg-white p-6 text-slate-600">برای ثبت سند باید عضو یک واحد فعال باشید.</p>
        </main>
    </AuthenticatedLayout>
</template>