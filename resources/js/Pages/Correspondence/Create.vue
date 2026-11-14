<script setup lang="ts">
import AppSelect from '@/Components/AppSelect.vue';
import CorrespondenceRichTextEditor from '@/Components/CorrespondenceRichTextEditor.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import JalaliDatePicker from '@/Components/JalaliDatePicker.vue';
import DepartmentTreeSelect from '@/Components/DepartmentTreeSelect.vue';
import axios from 'axios';

interface Department { id: number; name: string; parent_id: number | null; icon?: string | null; }
interface ExternalOrganization { id: number; name: string; }
interface ExternalDepartment { id: number; external_organization_id: number; parent_id?: number | null; manager_contact_id?: number | null; name: string; }
interface ExternalContact { id: number; external_organization_id: number; external_department_id: number | null; name: string; }
interface ReplyOption { id: number; letter_number: string | null; registration_number: string | null; external_registration_number: string | null; subject: string; type: string; letter_date: string | null; created_department: string | null; recipient_department: string | null; external_organization: string | null; external_organization_code: string | null; external_department: string | null; external_department_code: string | null; created_department_code: string | null; recipient_department_code: string | null; }
interface CreatedDepartment { id: number; name: string; parent_id?: number | null; icon?: string | null; }
type CompositionMode = 'editor' | 'file' | 'both';
type AppendixMode = 'none' | 'editor' | 'file' | 'both';

interface ExistingCorrespondence {
    id: number; type: 'incoming' | 'outgoing' | 'internal'; classification: string;
    subject: string; attachment_pages: number | null; letter_number: string | null; body: string | null;
    sender_name: string | null; recipient_name: string | null; received_at: string | null; sent_at: string | null;
    created_department_id: number | null; recipient_department_id: number | null;
    external_organization_id: number | null; external_department_id: number | null; external_contact_id: number | null;
    related_letters: ReplyOption[]; copy_department_ids: number[];
    original_documents: Array<{ id: number; title: string }>;
    appendix_mode: AppendixMode; appendix_body: string | null;
    appendix_documents: Array<{ id: number; title: string }>;
}
const props = defineProps<{
    types: Array<{ value: string; label: string }>;
    classifications: Array<{ value: string; label: string }>;
    departments: Department[];
    externalOrganizations: ExternalOrganization[];
    externalDepartments: ExternalDepartment[];
    externalContacts: ExternalContact[];
    createdDepartment: CreatedDepartment | null;
    createdDepartments: CreatedDepartment[];
    correspondenceFontFamily: string;
    compositionDefaults?: { 'correspondence.composition.font_color'?: string; 'correspondence.composition.font_size'?: string; 'correspondence.composition.table_border_color'?: string; 'correspondence.composition.shape_border_color'?: string; 'correspondence.composition.page_size'?: string };
    approvalSettings?: Record<string, string>;
    isEditing?: boolean;
    correspondence?: ExistingCorrespondence | null;
    taskId?: number | null;
    sourceCorrespondenceId?: number | null;
    sourceCorrespondence?: ReplyOption | null;
}>();

const relatedSearchQuery = ref('');
const relatedSearchResults = ref<ReplyOption[]>([]);
const selectedRelatedLetters = ref<ReplyOption[]>([]);
const relatedSearchLoading = ref(false);
let relatedSearchTimer: number | undefined;
let relatedSearchRequest = 0;

function formatRelatedLetterDate(value: string | null): string {
    if (!value) return 'تاریخ نامشخص';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return 'تاریخ نامشخص';
    return new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
        year: 'numeric', month: '2-digit', day: '2-digit', timeZone: 'Asia/Tehran',
    }).format(date);
}

function searchRelatedLetters(): void {
    if (relatedSearchTimer !== undefined) window.clearTimeout(relatedSearchTimer);
    const query = relatedSearchQuery.value.trim();
    if (query.length < 2) {
        relatedSearchResults.value = [];
        relatedSearchLoading.value = false;
        return;
    }

    relatedSearchTimer = window.setTimeout(async () => {
        const requestId = ++relatedSearchRequest;
        relatedSearchLoading.value = true;
        try {
            const response = await axios.get<{ data: ReplyOption[] }>(route('correspondence.related-search'), {
                params: { q: query },
            });
            if (requestId === relatedSearchRequest) relatedSearchResults.value = response.data.data;
        } catch {
            if (requestId === relatedSearchRequest) relatedSearchResults.value = [];
        } finally {
            if (requestId === relatedSearchRequest) relatedSearchLoading.value = false;
        }
    }, 250);
}

function selectRelatedLetter(letter: ReplyOption): void {
    if (!selectedRelatedLetters.value.some((item) => item.id === letter.id)) {
        selectedRelatedLetters.value.push(letter);
        form.reply_to_ids = selectedRelatedLetters.value.map((item) => item.id);
        form.reply_to_id = selectedRelatedLetters.value[0]?.id ?? '';
    }
    relatedSearchQuery.value = '';
    relatedSearchResults.value = [];
}
const scannerLoading = ref(false);
const scannerMessage = ref('');
const scannerFileName = ref('');
const scannerPreviewUrl = ref('');
const scannerPreviewOpen = ref(false);
const scannerPreviewCanConfirm = ref(false);
const scannerSession = ref<{ token: string; uploadUrl: string; statusUrl: string } | null>(null);
const scannerPreparing = ref(false);
const compositionModes: Array<{ value: CompositionMode; label: string; description: string }> = [
    { value: 'editor', label: 'ویرایشگر داخلی', description: 'نامه را داخل سامانه بنویسید.' },
    { value: 'file', label: 'پیوست فایل', description: 'فایل نهایی نامه را بارگذاری کنید و توضیح آن را بنویسید.' },
];

const existing = props.correspondence;
const form = useForm({
    source_correspondence_id: props.sourceCorrespondenceId ?? null,
    type: existing?.type ?? props.types[0]?.value ?? 'internal',
    created_department_id: existing?.created_department_id ?? props.createdDepartment?.id ?? '' as number | '',
    classification: existing?.classification ?? 'ordinary',
    subject: existing?.subject ?? '',
    attachment_pages: existing?.attachment_pages ?? null,
    appendix_mode: (existing?.appendix_mode ?? 'none') as AppendixMode,
    appendix_body: existing?.appendix_body ?? '',
    letter_number: existing?.letter_number ?? '',
    composition_mode: (existing?.type === 'incoming' ? 'file' : (existing?.body && existing.body.includes('<') ? 'editor' : 'file')) as CompositionMode,
    body: existing?.body ?? '',
    sender_name: existing?.sender_name ?? '',
    recipient_name: existing?.recipient_name ?? '',
    external_organization_id: existing?.external_organization_id ?? '' as number | '',
    external_department_id: existing?.external_department_id ?? '' as number | '',
    external_contact_id: existing?.external_contact_id ?? '' as number | '',
    received_at: existing?.received_at ?? '',
    sent_at: existing?.sent_at ?? '',
    recipient_department_id: existing?.recipient_department_id ?? '' as number | '',
    copy_department_ids: existing?.copy_department_ids ?? [] as number[],
    reply_to_id: existing?.related_letters?.[0]?.id ?? props.sourceCorrespondence?.id ?? '' as number | '',
    reply_to_ids: existing?.related_letters?.map((letter) => letter.id) ?? (props.sourceCorrespondence ? [props.sourceCorrespondence.id] : []) as number[],
    task_id: props.taskId ?? '' as number | '',
    original_letters: [] as File[],
    scanner_upload_token: '',
    attachments: [] as File[],
    appendix_files: [] as File[],
});
selectedRelatedLetters.value = existing?.related_letters ?? (props.sourceCorrespondence ? [props.sourceCorrespondence] : []);
const isDirectRegistration = computed(() => !props.isEditing
    && !props.sourceCorrespondenceId
    && props.approvalSettings?.[form.type] === 'direct');


watch(() => form.type, (type, previousType) => {
    if (type === 'incoming') {
        form.composition_mode = 'file';
        if (previousType !== 'incoming') form.body = '';
        void prepareScannerSession();
    } else if (previousType === 'incoming') {
        form.composition_mode = 'editor';
        form.body = '';
        form.original_letters = [];
        form.scanner_upload_token = '';
        scannerFileName.value = '';
        clearScannerPreview();
        scannerMessage.value = '';
        scannerSession.value = null;
    }
    if (type !== 'incoming') form.letter_number = '';
    if (type !== 'internal') form.recipient_department_id = '';
    if (type === 'internal') {
        form.external_organization_id = '';
        form.external_department_id = '';
    }
    if (type !== 'incoming') {
        form.sender_name = '';
        form.received_at = '';
    }
    if (type !== 'outgoing') {
        form.recipient_name = '';
        form.sent_at = '';
    }
});

const externalDepartmentsForOrganization = computed(() =>
    props.externalDepartments.filter((item) => String(item.external_organization_id) === String(form.external_organization_id)),
);

watch(() => form.external_organization_id, (organizationId) => {
    if (!externalDepartmentsForOrganization.value.some((item) => item.id === form.external_department_id)) {
        form.external_department_id = '';
    }
    if (!organizationId) form.external_department_id = '';
});

function selectDefaultExternalContact(): void {
    if (form.type !== 'outgoing' || !form.external_organization_id) {
        form.external_contact_id = '';
        return;
    }
    const departmentId = form.external_department_id || null;
    const selectedDepartment = departmentId
        ? props.externalDepartments.find((department) => department.id === Number(departmentId))
        : null;
    const unitManager = selectedDepartment?.manager_contact_id
        ? props.externalContacts.find((contact) => contact.id === selectedDepartment.manager_contact_id)
        : null;
    form.external_contact_id = unitManager?.id ?? '';
}

watch(() => [form.external_organization_id, form.external_department_id], () => {
    if (props.isEditing && form.external_contact_id) {
        const selected = props.externalContacts.find((contact) => contact.id === Number(form.external_contact_id));
        if (selected
            && selected.external_organization_id === Number(form.external_organization_id)
            && selected.external_department_id === (form.external_department_id ? Number(form.external_department_id) : null)) {
            return;
        }
    }
    selectDefaultExternalContact();
});

const selectedCopyDepartmentId = ref<number | string>('');
const departmentTreeOptions = computed(() => {
    const byParent = new Map<number | null, Department[]>();
    for (const department of props.departments) {
        const parentId = department.parent_id ?? null;
        byParent.set(parentId, [...(byParent.get(parentId) ?? []), department]);
    }
    const options: Array<{ id: number; label: string }> = [];
    const visit = (parentId: number | null, depth: number, ancestors: Set<number>): void => {
        const children = [...(byParent.get(parentId) ?? [])].sort((a, b) => a.name.localeCompare(b.name, 'fa'));
        for (const department of children) {
            if (ancestors.has(department.id)) continue;
            options.push({ id: department.id, label: '　'.repeat(depth) + (depth ? '└ ' : '') + department.name });
            const nextAncestors = new Set(ancestors); nextAncestors.add(department.id);
            visit(department.id, depth + 1, nextAncestors);
        }
    };
    visit(null, 0, new Set<number>());
    return options;
});
function addCopyDepartment(value: number | string | null): void {
    if (value === null || value === '') {
        selectedCopyDepartmentId.value = '';
        return;
    }
    const id = Number(value);
    if (Number.isInteger(id) && id > 0 && !form.copy_department_ids.includes(id)) form.copy_department_ids = [...form.copy_department_ids, id];
    selectedCopyDepartmentId.value = '';
}
function removeCopyDepartment(departmentId: number): void {
    form.copy_department_ids = form.copy_department_ids.filter((id) => id !== departmentId);
}
function toggleCopyDepartment(departmentId: number, checked: boolean): void {
    form.copy_department_ids = checked
        ? [...new Set([...form.copy_department_ids, departmentId])]
        : form.copy_department_ids.filter((id) => id !== departmentId);
}

function chooseAppendixFiles(event: Event): void {
    const target = event.target as HTMLInputElement;
    form.appendix_files = Array.from(target.files ?? []);
}

function chooseFiles(event: Event): void {
    const target = event.target as HTMLInputElement;
    form.attachments = Array.from(target.files ?? []);
}

function removeFile(index: number): void {
    form.attachments.splice(index, 1);
}

function chooseOriginalLetters(event: Event): void {
    const target = event.target as HTMLInputElement;
    form.original_letters = Array.from(target.files ?? []);
}

function removeOriginalLetter(index: number): void {
    form.original_letters.splice(index, 1);
}

function clearScannerPreview(): void {
    if (scannerPreviewUrl.value.startsWith('blob:')) URL.revokeObjectURL(scannerPreviewUrl.value);
    scannerPreviewUrl.value = '';
    scannerPreviewOpen.value = false;
    scannerPreviewCanConfirm.value = false;
}

function openScannedLetterPreview(): void {
    if (scannerPreviewUrl.value) scannerPreviewOpen.value = true;
}

function removeScannedLetter(): void {
    form.scanner_upload_token = '';
    scannerFileName.value = '';
    clearScannerPreview();
    scannerMessage.value = 'فایل اسکن‌شده از پیش‌نویس حذف شد.';
}

function confirmScannedLetter(): void {
    if (!scannerSession.value || !scannerPreviewUrl.value) return;
    form.scanner_upload_token = scannerSession.value.token;
    scannerPreviewCanConfirm.value = false;
    scannerPreviewOpen.value = false;
    scannerMessage.value = 'فایل اسکن‌شده پیوست شده است؛ با «مشاهده دوباره» می‌توانید آن را بررسی کنید.';
    scannerSession.value = null;
    void prepareScannerSession();
}

function discardScannedLetterPreview(): void {
    form.scanner_upload_token = '';
    scannerFileName.value = '';
    clearScannerPreview();
    scannerSession.value = null;
    scannerMessage.value = 'اسکن تأیید نشد. می‌توانید دوباره اسکن کنید.';
    void prepareScannerSession();
}

async function prepareScannerSession(): Promise<void> {
    if (scannerPreparing.value || scannerSession.value || form.type !== 'incoming') return;
    scannerPreparing.value = true;
    try {
        const started = await axios.post(route('correspondence.scanner.start'));
        scannerSession.value = {
            token: String(started.data.token),
            uploadUrl: String(started.data.upload_url),
            statusUrl: String(started.data.status_url),
        };
        if (!scannerMessage.value) {
            scannerMessage.value = 'اتصال اسکنر آماده است. برای اجرای NAPS2 روی دکمه اسکن کلیک کنید.';
        }
    } catch {
        scannerMessage.value = 'آماده‌سازی اتصال اسکنر ناموفق بود. اتصال سامانه را بررسی و صفحه را تازه‌سازی کنید.';
    } finally {
        scannerPreparing.value = false;
    }
}

onMounted(() => {
    if (!props.isEditing && form.type === 'incoming') void prepareScannerSession();
});

onBeforeUnmount(() => {
    clearScannerPreview();
    if (relatedSearchTimer !== undefined) window.clearTimeout(relatedSearchTimer);
});

async function scanOriginalLetter(): Promise<void> {
    if (scannerLoading.value) return;

    // The session is prepared before the click so the external protocol can be
    // invoked synchronously while the browser still has the user's activation.
    if (!scannerSession.value) {
        scannerMessage.value = 'اتصال اسکنر هنوز آماده نیست. چند ثانیه صبر کنید و دوباره کلیک کنید.';
        void prepareScannerSession();
        return;
    }

    const session = scannerSession.value;
    const launchUrl = 'officeautomation-scanner://scan?uploadUrl=' + encodeURIComponent(session.uploadUrl);
    const launchWindow = window.open(launchUrl, '_blank');
    scannerLoading.value = true;
    scannerMessage.value = launchWindow
        ? 'درخواست اجرای رابط اسکن ارسال شد. در صورت نمایش پیام مرورگر، اجرای برنامه را تأیید کنید.'
        : 'مرورگر پنجره اجرای اسکنر را مسدود کرد. اجازه بازشدن پنجره‌ها و اجرای برنامه خارجی را بررسی کنید.';
    scannerFileName.value = '';
    clearScannerPreview();
    form.scanner_upload_token = '';

    try {
        for (let attempt = 0; attempt < 600; attempt += 1) {
            await new Promise((resolve) => window.setTimeout(resolve, 2000));
            const status = await axios.get(session.statusUrl);
            if (status.data.status === 'ready') {
                scannerFileName.value = String(status.data.file_name || 'scanned-letter.pdf');
                const previewEndpoint = String(status.data.preview_url || '');
                if (!previewEndpoint) throw new Error('Scanner preview URL was not provided.');
                // Fetch the PDF as a blob and embed a local object URL. This prevents
                // download-manager browser integrations from intercepting the PDF route.
                const previewResponse = await axios.get<Blob>(previewEndpoint, {
                    responseType: 'blob',
                    headers: { Accept: 'application/pdf' },
                });
                clearScannerPreview();
                scannerPreviewUrl.value = URL.createObjectURL(previewResponse.data);
                scannerPreviewCanConfirm.value = true;
                scannerPreviewOpen.value = true;
                scannerMessage.value = 'اسکن دریافت شد. قبل از پیوست، همه صفحه‌ها را در پیش‌نمایش بررسی و تأیید کنید.';
                return;
            }
        }

        scannerMessage.value = 'پس از ۲۰ دقیقه فایلی دریافت نشد. اتصال اسکنر و برنامه واسط را بررسی کنید یا فایل را دستی بارگذاری کنید.';
        scannerSession.value = null;
        void prepareScannerSession();
    } catch {
        scannerMessage.value = 'دریافت وضعیت اسکن ناموفق بود. اتصال سامانه را بررسی کنید یا فایل اسکن‌شده را دستی انتخاب کنید.';
    } finally {
        scannerLoading.value = false;
    }
}

function openOutgoingPreview(): void {
    if (form.type !== 'outgoing') return;

    const targetName = 'correspondence-preview-' + Date.now();
    const previewWindow = window.open('', targetName);
    if (!previewWindow) {
        window.alert('مرورگر اجازه باز کردن پنجره پیش‌نمایش را نداد. پنجره‌های بازشو را برای سامانه فعال کنید.');
        return;
    }

    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    if (!csrfToken) {
        previewWindow.close();
        window.alert('توکن امنیتی فرم پیدا نشد. صفحه را تازه‌سازی و دوباره تلاش کنید.');
        return;
    }

    const payload = {
        created_department_id: form.created_department_id || null,
        subject: form.subject,
        composition_mode: form.composition_mode,
        body: form.body,
        sent_at: form.sent_at || null,
        recipient_name: form.recipient_name || null,
        external_organization_id: form.external_organization_id || null,
        external_department_id: form.external_department_id || null,
        external_contact_id: form.external_contact_id || null,
        appendix_mode: form.appendix_mode,
        appendix_body: form.appendix_body,
        appendix_file_count: form.appendix_files.length + (existing?.appendix_documents.length ?? 0),
    };

    const postForm = document.createElement('form');
    postForm.method = 'POST';
    postForm.action = route('correspondence.preview');
    postForm.target = targetName;
    postForm.hidden = true;

    for (const [name, value] of Object.entries({ _token: csrfToken, preview: JSON.stringify(payload) })) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        postForm.appendChild(input);
    }

    document.body.appendChild(postForm);
    postForm.submit();
    postForm.remove();
}

</script>

<template>
    <Head :title="isEditing ? 'ویرایش مکاتبه' : 'ایجاد مکاتبه'" />
    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-bold text-slate-900">{{ isEditing ? 'ویرایش مکاتبه' : 'ایجاد مکاتبه' }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ isEditing ? 'اطلاعات مکاتبه را در همان فرم ثبت اولیه اصلاح کنید.' : (isDirectRegistration ? 'پس از ذخیره، مکاتبه مستقیماً ثبت نهایی و شماره ثبت آن تخصیص داده می‌شود.' : 'پیش‌نویس ایجاد می‌شود و پس از تأیید، برای ثبت نهایی ارسال خواهد شد.') }}</p>
            </div>
        </template>



        <div class="p-4 sm:p-6">
            <form
                class="mx-auto max-w-5xl space-y-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"
                enctype="multipart/form-data"
                @submit.prevent="isEditing ? form.transform((data) => ({ ...data, _method: 'put' })).post(route('correspondence.update', correspondence!.id), { forceFormData: true }) : form.post(route('correspondence.store'), { forceFormData: true })"
            >
                <section class="space-y-4">
                    <h3 class="text-base font-bold text-slate-900">مشخصات مکاتبه</h3>
                    <div class="grid min-w-0 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label for="type" class="mb-1.5 block text-sm font-semibold text-slate-700">نوع مکاتبه <span class="text-rose-600">*</span></label>
                            <AppSelect id="type" v-model="form.type" :disabled="isEditing" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-100 disabled:text-slate-500">
                                <option v-for="item in types" :key="item.value" :value="item.value">{{ item.label }}</option>
                            </AppSelect>
                            <InputError class="mt-1" :message="form.errors.type" />
                        </div>
                        <div>
                            <div class="mb-1.5 flex items-center gap-1.5"><label for="classification" class="text-sm font-semibold text-slate-700">طبقه‌بندی دسترسی <span class="text-rose-600">*</span></label><details class="group relative"><summary aria-label="راهنمای طبقه‌بندی دسترسی" class="flex h-5 w-5 cursor-pointer list-none items-center justify-center rounded-full border border-slate-300 text-[11px] font-bold text-slate-500 hover:border-indigo-400 hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-300">؟</summary><div class="absolute right-0 top-7 z-20 w-64 rounded-lg border border-slate-200 bg-white p-3 text-xs font-normal leading-6 text-slate-700 shadow-lg">نامه عادی پس از ثبت نهایی برای کاربران دارای مجوز مشاهده قابل مشاهده است؛ نامه محرمانه فقط برای افراد مرتبط و دبیرخانه.</div></details></div>
                            <AppSelect id="classification" v-model="form.classification" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option v-for="item in classifications" :key="item.value" :value="item.value">{{ item.label }}</option>
                            </AppSelect>
                            <InputError class="mt-1" :message="form.errors.classification" />
                        </div>
                        <div v-if="form.type !== 'incoming'" :class="form.type === 'outgoing' || form.type === 'internal' ? 'lg:col-span-2' : ''">
                            <div class="mb-1.5 flex items-center gap-1.5"><label for="created_department_id" class="text-sm font-semibold text-slate-700">واحد ایجادکننده <span v-if="createdDepartments.length > 1" class="text-rose-600">*</span></label><details class="group relative"><summary aria-label="راهنمای واحد ایجادکننده" class="flex h-5 w-5 cursor-pointer list-none items-center justify-center rounded-full border border-slate-300 text-[11px] font-bold text-slate-500 hover:border-indigo-400 hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-300">؟</summary><div class="absolute right-0 top-7 z-20 w-64 rounded-lg border border-slate-200 bg-white p-3 text-xs font-normal leading-6 text-slate-700 shadow-lg">واحدی را انتخاب کنید که نامه از طرف آن ایجاد و شماره‌گذاری می‌شود.</div></details></div>
                            <DepartmentTreeSelect v-if="createdDepartments.length > 1" v-model="form.created_department_id" :departments="createdDepartments" placeholder="انتخاب واحدی که از طرف آن نامه ایجاد می‌شود" :clearable="false" />
                            <div v-else class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700">{{ createdDepartment?.name ?? 'برای کاربر واحد فعالی تعیین نشده است' }}</div>
                            <InputError class="mt-1" :message="form.errors.created_department_id" />
                        </div>
                        <div v-if="form.type === 'incoming' || form.type === 'internal'" class="min-w-0 lg:col-span-2">
                            <label for="recipient_department_id" class="mb-1.5 block text-sm font-semibold text-slate-700">واحد دریافت‌کننده <span class="text-rose-600">*</span></label>
                            <DepartmentTreeSelect v-model="form.recipient_department_id" :departments="departments" placeholder="انتخاب واحد دریافت‌کننده" :clearable="false" />
                            <InputError class="mt-1" :message="form.errors.recipient_department_id" />
                        </div>
                        <div v-if="form.type === 'internal'" class="min-w-0 lg:col-span-2">
                            <label for="subject" class="mb-1.5 block text-sm font-semibold text-slate-700">موضوع</label>
                            <input id="subject" v-model="form.subject" required maxlength="255" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <InputError class="mt-1" :message="form.errors.subject" />
                        </div>

                    </div>
                    <div class="grid min-w-0 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div v-if="form.type !== 'internal'" :class="form.type === 'outgoing' ? 'lg:col-span-3' : form.type === 'incoming' ? 'lg:col-span-2' : 'col-span-full'">
                            <label for="subject" class="mb-1.5 block text-sm font-semibold text-slate-700">موضوع</label>
                            <input id="subject" v-model="form.subject" required maxlength="255" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <InputError class="mt-1" :message="form.errors.subject" />
                        </div>
                        <div v-if="form.type === 'incoming'" class="min-w-0">
                            <div class="mb-1.5 flex items-center gap-1.5">
                                <label for="letter_number" class="required-field-label text-sm font-semibold text-slate-700">شماره نامه وارده</label>
                                <details class="group relative">
                                    <summary aria-label="راهنمای شماره نامه وارده" class="flex h-5 w-5 cursor-pointer list-none items-center justify-center rounded-full border border-slate-300 text-[11px] font-bold text-slate-500 hover:border-indigo-400 hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-300">؟</summary>
                                    <span class="absolute right-0 top-7 z-20 w-64 rounded-lg border border-slate-200 bg-white p-3 text-xs font-normal leading-6 text-slate-700 shadow-lg">شماره نامه درج‌شده روی اصل نامه وارده را وارد کنید.</span>
                                </details>
                            </div>
                            <input id="letter_number" v-model="form.letter_number" class="block min-h-11 w-full rounded-xl border-slate-300 text-right text-sm focus:border-indigo-500 focus:ring-indigo-500" maxlength="100" required :disabled="form.processing" placeholder="شماره درج‌شده روی اصل نامه"/>
                            <InputError class="mt-1.5" :message="form.errors.letter_number"/>
                        </div>
                        <div v-if="form.type === 'incoming'" class="min-w-0">
                            <label for="received_at" class="mb-1.5 block text-sm font-semibold text-slate-700">تاریخ دریافت <span class="text-rose-600">*</span></label>
                            <JalaliDatePicker v-model="form.received_at" placeholder="انتخاب تاریخ دریافت" required />
                            <InputError class="mt-1" :message="form.errors.received_at" />
                        </div>
                        <div v-if="form.type === 'outgoing'" class="min-w-0">
                            <label for="sent_at" class="mb-1.5 block text-sm font-semibold text-slate-700">تاریخ ارسال <span class="text-rose-600">*</span></label>
                            <JalaliDatePicker v-model="form.sent_at" placeholder="انتخاب تاریخ ارسال" required />
                            <InputError class="mt-1" :message="form.errors.sent_at" />
                        </div>
                        <div class="sm:col-span-2 flex min-h-0 flex-col rounded-xl border border-slate-200 p-4">
                            <div class="mb-1.5 flex items-center gap-1.5"><label for="related_correspondence_search" class="text-sm font-semibold text-slate-700">ارتباط با مکاتبات قبلی</label>
                                <details class="group relative"><summary aria-label="راهنمای ارتباط با مکاتبات قبلی" class="flex h-5 w-5 cursor-pointer list-none items-center justify-center rounded-full border border-slate-300 bg-white text-[11px] font-bold text-slate-500 hover:border-indigo-400 hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">؟</summary><span class="absolute right-0 top-7 z-30 w-64 rounded-lg bg-slate-900 p-3 text-xs font-normal leading-6 text-white shadow-lg">برای پیوند دادن این نامه به یک یا چند مکاتبه ثبت‌شده، موضوع یا شماره نامه را جست‌وجو و موارد موردنظر را انتخاب کنید.</span></details></div>
                            <div class="relative">
                                <input id="related_correspondence_search" v-model="relatedSearchQuery" type="search" autocomplete="off" placeholder="جستجو (حداقل ۲ نویسه)..." class="w-full shrink-0 rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" @input="searchRelatedLetters" />
                                <div v-if="relatedSearchQuery.trim().length >= 2" class="absolute inset-x-0 top-full z-20 mt-1 max-h-64 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg">
                                    <p v-if="relatedSearchLoading" class="p-3 text-sm text-slate-500">در حال جستجوی مکاتبات...</p>
                                    <p v-else-if="relatedSearchResults.length === 0" class="p-3 text-sm text-slate-500">مکاتبه‌ای با این مشخصات پیدا نشد.</p>
                                    <div v-else class="divide-y divide-slate-100">
                                        <button v-for="item in relatedSearchResults" :key="item.id" type="button" class="block w-full p-3 text-right transition hover:bg-indigo-50 disabled:cursor-default disabled:opacity-50" :disabled="selectedRelatedLetters.some((letter) => letter.id === item.id)" @click="selectRelatedLetter(item)">
                                            <span class="block text-sm font-semibold text-slate-900">{{ item.subject }}</span>
                                            <span class="mt-1 block text-xs leading-5 text-slate-600">شماره نامه: {{ item.letter_number ?? "—" }} | شماره ثبت: {{ item.registration_number ?? "—" }} | تاریخ نامه: {{ formatRelatedLetterDate(item.letter_date) }}</span>
                                            <span class="mt-1 block text-xs leading-5 text-slate-500">{{ [item.created_department, item.created_department_code, item.recipient_department, item.recipient_department_code, item.external_organization, item.external_organization_code, item.external_department, item.external_department_code].filter(Boolean).join(" · ") || "واحد یا سازمان ثبت نشده" }}<span v-if="item.external_registration_number"> · شماره ثبت طرف مقابل: {{ item.external_registration_number }}</span></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <ul v-if="selectedRelatedLetters.length" class="mt-3 max-h-52 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200">
                                <li v-for="letter in selectedRelatedLetters" :key="letter.id" class="flex items-start justify-between gap-3 px-3 py-2.5">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-800">{{ letter.subject }}</p>
                                        <p class="mt-1 text-xs leading-5 text-slate-500">شماره نامه: {{ letter.letter_number ?? "—" }} | شماره ثبت: {{ letter.registration_number ?? "—" }} | تاریخ نامه: {{ formatRelatedLetterDate(letter.letter_date) }}</p>
                                    </div>
                                    <button type="button" class="shrink-0 text-xs font-semibold text-rose-700 hover:underline" @click="selectedRelatedLetters = selectedRelatedLetters.filter((item) => item.id !== letter.id); form.reply_to_ids = selectedRelatedLetters.map((item) => item.id); form.reply_to_id = selectedRelatedLetters[0]?.id ?? ''">حذف</button>
                                </li>
                            </ul>
                            <InputError class="mt-1" :message="form.errors.reply_to_id" />
                            <InputError class="mt-1" :message="form.errors.reply_to_ids" />
                        </div>
                        <div class="sm:col-span-2 flex min-h-0 flex-col rounded-xl border border-slate-200 p-4">
                            <div class="mb-1.5 flex items-center gap-1.5"><label for="copy_department_select" class="text-sm font-semibold text-slate-700">رونوشت به</label><details class="group relative"><summary aria-label="راهنمای رونوشت به" class="flex h-5 w-5 cursor-pointer list-none items-center justify-center rounded-full border border-slate-300 bg-white text-[11px] font-bold text-slate-500 hover:border-indigo-400 hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">؟</summary><span class="absolute right-0 top-7 z-30 w-64 rounded-lg bg-slate-900 p-3 text-xs font-normal leading-6 text-white shadow-lg">واحدهای انتخاب‌شده یک نسخه برای اطلاع دریافت می‌کنند و از طریق رونوشت امکان اقدام یا ارجاع ندارند.</span></details></div>
                            <DepartmentTreeSelect :model-value="selectedCopyDepartmentId" :departments="departments" placeholder="انتخاب واحد برای رونوشت" @update:model-value="addCopyDepartment" />
                            <ul v-if="form.copy_department_ids.length" class="mt-3 max-h-52 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200">
                                <li v-for="id in form.copy_department_ids" :key="id" class="flex items-center justify-between gap-3 px-3 py-2"><span class="text-sm text-slate-800">{{ departmentTreeOptions.find((item) => item.id === id)?.label ?? departments.find((item) => item.id === id)?.name }}</span><button type="button" class="text-xs font-semibold text-rose-700 hover:underline" @click="removeCopyDepartment(id)">حذف</button></li>
                            </ul>
                            <p v-else class="mt-2 text-xs text-slate-500">هنوز واحدی برای رونوشت انتخاب نشده است.</p>
                            <InputError class="mt-1" :message="form.errors.copy_department_ids" /><InputError class="mt-1" :message="form.errors['copy_department_ids.0']" />
                        </div>
                        <div v-if="form.type !== 'internal'" class="col-span-full space-y-3 rounded-xl border border-teal-100 bg-teal-50/50 p-4">
                            <div class="flex items-center gap-2">
                                <h4 class="text-sm font-bold text-slate-800">{{ form.type === 'incoming' ? 'سازمان فرستنده' : 'سازمان گیرنده' }}</h4>
                                <details class="group relative"><summary aria-label="راهنمای طرف برون‌سازمانی" class="flex h-5 w-5 cursor-pointer list-none items-center justify-center rounded-full border border-slate-300 bg-white text-[11px] font-bold text-slate-500 hover:border-indigo-400 hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-300">؟</summary><div class="absolute right-0 top-7 z-20 w-64 rounded-lg border border-slate-200 bg-white p-3 text-xs font-normal leading-6 text-slate-700 shadow-lg">سازمان مبدأ برای نامه وارده و سازمان مخاطب برای نامه صادره الزامی است. در صورت نیاز، واحد مربوط به آن سازمان را نیز انتخاب کنید.</div></details>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="external_organization_id" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ form.type === 'incoming' ? 'سازمان مبدأ نامه' : 'سازمان مخاطب' }}</label>
                                    <AppSelect id="external_organization_id" v-model="form.external_organization_id" :required="['incoming', 'outgoing'].includes(form.type)" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">انتخاب سازمان بیرونی</option>
                                        <option v-for="item in externalOrganizations" :key="item.id" :value="item.id">{{ item.name }}</option>
                                    </AppSelect>
                                    <InputError class="mt-1" :message="form.errors.external_organization_id" />
                                </div>
                                <div>
                                    <label for="external_department_id" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ form.type === 'incoming' ? 'واحد فرستنده' : 'واحد مخاطب بیرونی' }}</label>
                                    <DepartmentTreeSelect id="external_department_id" v-model="form.external_department_id" :departments="externalDepartmentsForOrganization" :disabled="!form.external_organization_id" placeholder="انتخاب واحد بیرونی (اختیاری)" />
                                    <InputError class="mt-1" :message="form.errors.external_department_id" />
                                </div>
                                <div v-if="form.type === 'incoming'">
                                    <label for="sender_name" class="mb-1.5 block text-sm font-semibold text-slate-700">نام شخص یا سمت فرستنده (اختیاری)</label>
                                    <input id="sender_name" v-model="form.sender_name" maxlength="255" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    <InputError class="mt-1" :message="form.errors.sender_name" />
                                </div>
                                <div v-if="form.type === 'outgoing'">
                                    <label for="external_contact_id" class="mb-1.5 block text-sm font-semibold text-slate-700">مخاطب شخصی (اختیاری)</label>
                                    <AppSelect id="external_contact_id" v-model="form.external_contact_id" :disabled="!form.external_organization_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">بدون انتخاب شخص؛ خطاب به سازمان یا واحد</option>
                                        <option v-for="contact in externalContacts.filter(item => item.external_organization_id === Number(form.external_organization_id) && item.external_department_id === (form.external_department_id ? Number(form.external_department_id) : null))" :key="contact.id" :value="contact.id">
                                            {{ contact.name }}{{ contact.id === externalDepartments.find(item => item.id === Number(form.external_department_id))?.manager_contact_id ? ' (مدیر واحد)' : '' }}
                                        </option>
                                    </AppSelect>
                                    <InputError class="mt-1" :message="form.errors.external_contact_id" />
                                </div>
                                <div v-if="form.type === 'outgoing'">
                                    <label for="recipient_name" class="mb-1.5 block text-sm font-semibold text-slate-700">نام شخص یا سمت گیرنده (اختیاری)</label>
                                    <input id="recipient_name" v-model="form.recipient_name" maxlength="255" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    <InputError class="mt-1" :message="form.errors.recipient_name" />
                                </div>
                            </div>
                        </div>

                    </div>
                </section>

                <section v-if="form.type !== 'incoming'" class="space-y-3 border-t border-slate-100 pt-5">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">روش تهیه نامه</h3>
                        <p class="mt-1 text-sm text-slate-500">می‌توانید نامه را داخل سامانه بنویسید، فایل آماده را بارگذاری کنید یا هر دو را با هم داشته باشید.</p>
                    </div>
                    <div class="grid gap-3 md:grid-cols-4">
                        <label
                            v-for="mode in compositionModes"
                            :key="mode.value"
                            class="flex cursor-pointer gap-3 rounded-xl border p-4 transition"
                            :class="form.composition_mode === mode.value ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-200' : 'border-slate-200 hover:border-slate-300'"
                        >
                            <input v-model="form.composition_mode" type="radio" name="composition_mode" :value="mode.value" class="mt-1 border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            <span>
                                <span class="block text-sm font-bold text-slate-900">{{ mode.label }}</span>
                                <span class="mt-1 block text-xs leading-5 text-slate-500">{{ mode.description }}</span>
                            </span>
                        </label>
                    </div>
                    <InputError :message="form.errors.composition_mode" />
                </section>

                <section v-if="form.type === 'incoming'" class="space-y-3 border-t border-slate-100 pt-5">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">توضیحات نامه</h3>
                        <p class="mt-1 text-sm leading-6 text-slate-500">خلاصه یا توضیحی درباره موضوع و محتوای نامه وارده را وارد کنید. این توضیح جایگزین فایل اصل نامه نیست.</p>
                    </div>
                    <textarea
                        id="incoming_description"
                        v-model="form.body"
                        rows="5"
                        maxlength="100000"
                        class="w-full rounded-xl border-slate-300 text-sm leading-7 focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="توضیحات یا خلاصه نامه را بنویسید..."
                        :disabled="form.processing"
                    ></textarea>
                    <InputError :message="form.errors.body" />
                </section>

                <section v-if="form.type === 'outgoing' && form.composition_mode === 'file'" class="space-y-3 border-t border-slate-100 pt-5">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">توضیحات فایل نامه <span class="text-rose-600">*</span></h3>
                        <p class="mt-1 text-sm leading-6 text-slate-500">موضوع یا توضیح مختصری درباره فایل نهایی نامه را وارد کنید؛ متن اصلی در فایل بارگذاری‌شده قرار دارد.</p>
                    </div>
                    <textarea id="outgoing_file_description" v-model="form.body" rows="4" maxlength="100000" required class="w-full rounded-xl border-slate-300 text-sm leading-7 focus:border-indigo-500 focus:ring-indigo-500" placeholder="توضیحی درباره محتوای فایل نامه بنویسید..."></textarea>
                    <InputError :message="form.errors.body" />
                </section>

                <section v-if="form.type !== 'incoming' && form.composition_mode !== 'file'" class="space-y-3 border-t border-slate-100 pt-5">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">متن نامه</h3>
                        <p class="mt-1 text-sm text-slate-500">از نوار ابزار برای قالب‌بندی متن استفاده کنید. پیش‌نویس قبل از ثبت نهایی قابل ویرایش خواهد بود.</p>
                    </div>
                    <CorrespondenceRichTextEditor v-model="form.body" label="متن نامه" :font-family="correspondenceFontFamily" :font-color="compositionDefaults?.['correspondence.composition.font_color'] ?? '#202124'" :font-size="Number(compositionDefaults?.['correspondence.composition.font_size'] ?? 12)" :table-border-color="compositionDefaults?.['correspondence.composition.table_border_color'] ?? '#94a3b8'" :shape-border-color="compositionDefaults?.['correspondence.composition.shape_border_color'] ?? '#334155'" :disabled="form.processing" />
                    <InputError :message="form.errors.body" />
                </section>

                <section v-if="form.type === 'incoming'" class="space-y-3 border-t border-slate-100 pt-5">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">اصل نامه دریافتی <span v-if="!isEditing" class="text-rose-600">*</span></h3>
                        <p class="mt-1 text-sm leading-6 text-slate-500">{{ isEditing ? 'فایل‌های فعلی حفظ می‌شوند؛ در صورت نیاز می‌توانید فایل دیگری اضافه کنید.' : 'تصویر اسکن‌شده یا PDF اصل نامه را بارگذاری کنید. این فایل‌ها جدا از پیوست‌های همراه نامه نگهداری می‌شوند. حداکثر ۱۰ فایل، هرکدام تا ۲۰ مگابایت.' }}</p>
                        <ul v-if="isEditing && correspondence?.original_documents.length" class="space-y-1 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
                            <li v-for="document in correspondence.original_documents" :key="document.id">فایل فعلی: {{ document.title }}</li>
                        </ul>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label for="original_letters" class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-indigo-200 bg-indigo-50/40 px-5 py-7 text-center transition hover:border-indigo-400 hover:bg-indigo-50">
                            <span class="text-sm font-semibold text-slate-800">انتخاب فایل اسکن‌شده</span>
                            <span class="mt-1 text-xs text-slate-500">PDF، JPG یا PNG</span>
                            <input id="original_letters" type="file" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="mt-3 block max-w-full text-sm text-slate-600 file:ml-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-700" @change="chooseOriginalLetters" />
                        </label>
                        <div class="flex flex-col items-center justify-center rounded-xl border border-slate-200 px-5 py-6 text-center">
                            <button type="button" :disabled="scannerLoading" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-wait disabled:opacity-60" @click="scanOriginalLetter">
                                {{ scannerLoading ? 'در انتظار پایان اسکن...' : 'شروع اسکن چندصفحه‌ای' }}
                            </button>
                            <p class="mt-2 text-xs leading-5 text-slate-500">در NAPS2 همه صفحه‌ها را اسکن و در یک PDF ذخیره کنید؛ سپس پیش‌نمایش را بررسی و تأیید کنید.</p>
                        </div>
                    </div>
                    <p v-if="scannerMessage" class="text-sm leading-6 text-slate-600" role="status">{{ scannerMessage }}</p>
                    <div v-if="scannerFileName" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5">
                        <p class="min-w-0 truncate text-sm text-emerald-800">فایل اسکن‌شده: {{ scannerFileName }}</p>
                        <div class="flex shrink-0 items-center gap-3">
                            <button v-if="form.scanner_upload_token && scannerPreviewUrl" type="button" class="text-xs font-semibold text-indigo-700 hover:underline" @click="openScannedLetterPreview">مشاهده دوباره</button>
                            <button type="button" class="text-xs font-semibold text-rose-700 hover:underline" @click="removeScannedLetter">حذف اسکن</button>
                        </div>
                    </div>
                    <InputError :message="form.errors.original_letters" />
                    <InputError :message="form.errors.scanner_upload_token" />
                    <InputError :message="form.errors['original_letters.0']" />
                    <ul v-if="form.original_letters.length" class="divide-y divide-slate-100 rounded-xl border border-slate-200">
                        <li v-for="(file, index) in form.original_letters" :key="file.name + file.size + index" class="flex items-center justify-between gap-3 px-3 py-2.5">
                            <span class="min-w-0 truncate text-sm text-slate-700">{{ file.name }} <span class="text-xs text-slate-400">({{ Math.ceil(file.size / 1024) }} KB)</span></span>
                            <button type="button" class="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50" @click="removeOriginalLetter(index)">حذف</button>
                        </li>
                    </ul>
                </section>

                <section v-if="form.type !== 'incoming'" class="space-y-4 border-t border-slate-100 pt-5">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">پیوست نامه</h3>
                        <p class="mt-1 text-sm leading-6 text-slate-500">پیوست می‌تواند متن چندصفحه‌ای، فایل یا ترکیبی از هر دو باشد. تعداد برگ‌های متن در پیش‌نمایش چاپ محاسبه می‌شود و نیازی به ورود دستی تعداد صفحات نیست.</p>
                    </div>
                    <div class="grid gap-3 md:grid-cols-3">
                        <label v-for="mode in [{value:'none',label:'بدون پیوست'},{value:'editor',label:'متن پیوست'},{value:'file',label:'فایل پیوست'},{value:'both',label:'متن و فایل'}]" :key="mode.value" class="flex cursor-pointer items-center gap-2 rounded-xl border p-3 text-sm font-semibold" :class="form.appendix_mode === mode.value ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200'">
                            <input v-model="form.appendix_mode" type="radio" name="appendix_mode" :value="mode.value" class="border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            {{ mode.label }}
                        </label>
                    </div>
                    <InputError :message="form.errors.appendix_mode" />
                    <div v-if="form.appendix_mode === 'editor' || form.appendix_mode === 'both'" class="space-y-2">
                        <label for="appendix_body" class="block text-sm font-semibold text-slate-700">متن پیوست</label>
                        <CorrespondenceRichTextEditor v-model="form.appendix_body" label="متن پیوست" :font-family="correspondenceFontFamily" :disabled="form.processing" />
                        <InputError :message="form.errors.appendix_body" />
                    </div>
                        <ul v-if="isEditing && correspondence?.appendix_documents.length" class="space-y-1 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
                            <li v-for="document in correspondence.appendix_documents" :key="document.id">فایل موجود: {{ document.title }}</li>
                        </ul>
                    <div v-if="form.appendix_mode === 'file' || form.appendix_mode === 'both'" class="space-y-2">
                        <div>
                            <h4 class="text-sm font-semibold text-slate-700">فایل‌های پیوست</h4>
                            <p class="mt-1 text-xs text-slate-500">PDF، Word، Excel، PowerPoint، تصویر و فایل متنی؛ حداکثر ۲۰ مگابایت برای هر فایل و ۱۰ فایل.</p>
                        </div>
                        <input id="appendix_files" type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.txt,.csv,.zip" class="block max-w-full text-sm text-slate-600 file:ml-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-700" @change="chooseAppendixFiles" />
                        <InputError :message="form.errors.appendix_files" />
                        <InputError :message="form.errors['appendix_files.0']" />
                        <ul v-if="form.appendix_files.length" class="divide-y divide-slate-100 rounded-xl border border-slate-200">
                            <li v-for="(file, index) in form.appendix_files" :key="file.name + file.size + index" class="flex items-center justify-between gap-3 px-3 py-2.5">
                                <span class="min-w-0 truncate text-sm text-slate-700">{{ file.name }} ({{ Math.ceil(file.size / 1024) }} KB)</span>
                                <button type="button" class="shrink-0 text-xs font-semibold text-rose-700" @click="form.appendix_files.splice(index, 1)">حذف</button>
                            </li>
                        </ul>
                    </div>
                </section>

                <section v-if="form.type !== 'incoming' && form.composition_mode !== 'editor'" class="space-y-3 border-t border-slate-100 pt-5">
                    <label for="attachments" class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-5 py-7 text-center transition hover:border-indigo-400 hover:bg-indigo-50/50">
                        <h3 class="text-base font-bold text-slate-900">فایل نهایی نامه</h3>
                        <p class="mt-1 text-sm text-slate-500">فایل نهایی نامه را بارگذاری کنید. PDF، Word و تصویر پذیرفته می‌شود؛ حداکثر ۲۰ مگابایت برای هر فایل و ۱۰ فایل.</p>
                        <input id="attachments" type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.txt,.csv,.zip" class="mt-3 block max-w-full text-sm text-slate-600 file:ml-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-700" @change="chooseFiles" />
                    </label>
                    <InputError :message="form.errors.attachments" />
                    <InputError :message="form.errors['attachments.0']" />
                    <ul v-if="form.attachments.length" class="divide-y divide-slate-100 rounded-xl border border-slate-200">
                        <li v-for="(file, index) in form.attachments" :key="file.name + file.size + index" class="flex items-center justify-between gap-3 px-3 py-2.5">
                            <span class="min-w-0 truncate text-sm text-slate-700">{{ file.name }} <span class="text-xs text-slate-400">({{ Math.ceil(file.size / 1024) }} KB)</span></span>
                            <button type="button" class="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50" @click="removeFile(index)">حذف</button>
                        </li>
                    </ul>
                </section>


                <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-5">
                    <button v-if="form.type === 'outgoing'" type="button" class="rounded-xl border border-indigo-300 bg-indigo-50 px-4 py-2.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-100" @click="openOutgoingPreview">پیش‌نمایش نامه</button>
                    <Link :href="route('correspondence.index')" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">انصراف</Link>
                    <button type="submit" :disabled="form.processing" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                        {{ form.processing ? 'در حال ذخیره...' : (isEditing ? 'ذخیره تغییرات' : (isDirectRegistration ? 'ثبت نهایی مکاتبه' : 'ذخیره پیش‌نویس')) }}
                    </button>
                </div>
            </form>
        </div>
        <div v-if="scannerPreviewOpen && scannerPreviewUrl" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="scanner-preview-title">
            <div class="flex max-h-[95vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 sm:px-6">
                    <div><h2 id="scanner-preview-title" class="font-bold text-slate-900">پیش‌نمایش اصل نامه اسکن‌شده</h2><p class="mt-1 text-xs text-slate-500">{{ scannerFileName }}<span v-if="scannerPreviewCanConfirm"> — پیش از پیوست، همه صفحه‌ها را بررسی کنید.</span><span v-else> — فایل پیوست‌شده</span></p></div>
                    <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-100" @click="scannerPreviewOpen = false">بستن پیش‌نمایش</button>
                </div>
                <iframe :src="scannerPreviewUrl" title="پیش‌نمایش PDF اسکن‌شده" class="min-h-[55vh] flex-1 bg-slate-100 sm:min-h-[65vh]"></iframe>
                <div class="flex flex-wrap justify-end gap-3 border-t border-slate-200 bg-white px-4 py-3 sm:px-6">
                    <template v-if="scannerPreviewCanConfirm">
                        <button type="button" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="discardScannedLetterPreview">اسکن مجدد</button>
                        <button type="button" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700" @click="confirmScannedLetter">تأیید و پیوست اصل نامه</button>
                    </template>
                    <button v-else type="button" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700" @click="scannerPreviewOpen = false">بازگشت به فرم مکاتبه</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
</style>
