<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

interface RelatedCorrespondence {
    id: number;
    subject: string;
    registration_number: string | null;
    type: string;
    status: string;
}

interface CorrespondenceDetail {
    id: number;
    type: 'incoming' | 'outgoing' | 'internal';
    subject: string;
    body: string | null;
    status: 'draft' | 'registered';
    registration_number: string | null;
    registered_at: string | null;
    created_at: string | null;
    sender_name: string | null;
    recipient_name: string | null;
    received_at: string | null;
    sent_at: string | null;
    task_id: number | null;
    task: { id: number; title: string; status: string; assignee_id: number | null; created_by: number } | null;
    external_registration_number: string | null;
    external_registered_at: string | null;
    dispatch_method: string | null;
    external_tracking_notes: string | null;
    creator: { id: number; name: string } | null;
    registrar: { id: number; name: string } | null;
    created_department: { id: number; name: string } | null;
    recipient_department: { id: number; name: string } | null;
    external_organization: { id: number; name: string } | null;
    external_department: { id: number; name: string; external_organization_id: number } | null;
    reply_to: RelatedCorrespondence | null;
    replies: RelatedCorrespondence[];
    workflow_state: string;
    current_department: { id: number; name: string } | null;
    current_user_id: number | null;
    actions: Array<{ id: number; action_type: string; note: string | null; created_at: string; actor: { id: number; name: string } | null; fromDepartment: { id: number; name: string } | null; toDepartment: { id: number; name: string } | null }>;
    copies: Array<{ id: number; name: string }>;
}

interface Attachment {
    id: number;
    title: string;
    file_name: string;
    size: number | null;
}

const props = defineProps<{
    correspondence: CorrespondenceDetail;
    originalLetters: Attachment[];
    attachments: Attachment[];
    canRegister: boolean;
    correspondenceFontFamily: string;
    trackableTasks: { id: number; title: string; status: string }[];
    canUpdateExternalTracking: boolean;
    workflowDepartments: { id: number; name: string }[];
    canReturnToManager: boolean;
    canManageWorkflow: boolean;
}>();

const page = usePage<PageProps<{ errors: Record<string, string>; flash?: { success?: string } }>>();

const actionLabels: Record<string, string> = {
    employee_return_to_manager: 'بازگشت توضیح‌دار به مدیر',
    manager_approve: 'تأیید مدیر برای ثبت نهایی',
    manager_refer: 'ارجاع به واحد دیگر',
    request_task: 'درخواست شروع تسک',
    request_project: 'درخواست شروع پروژه',
    request_letter: 'درخواست تهیه نامه جدید',
    reject_request: 'رد درخواست',
    close_without_result: 'خاتمه بدون نتیجه',
    secretariat_initial_referral: 'ارجاع اولیه دبیرخانه',
    copy_added: 'ثبت رونوشت جهت اطلاع',
};
const actionForm = useForm({
    action_type: 'manager_refer',
    note: '',
    to_department_id: '' as number | '',
});
function submitWorkflowAction(): void {
    actionForm.post(route('correspondence.actions.store', props.correspondence.id), {
        preserveScroll: true,
        onSuccess: () => {
            actionForm.note = '';
            actionForm.to_department_id = '';
        },
    });
}

const typeLabels: Record<string, string> = { incoming: 'وارده', outgoing: 'صادره', internal: 'داخلی' };
const statusLabels: Record<string, string> = { draft: 'پیش‌نویس', registered: 'ثبت‌شده' };
const formatDate = (value: string | null) => value
    ? new Intl.DateTimeFormat('fa-IR', { dateStyle: 'medium' }).format(new Date(value))
    : '—';
const formatDateTime = (value: string | null) => value
    ? new Intl.DateTimeFormat('fa-IR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    : '—';
const formatSize = (value: number | null) => {
    if (value === null) return 'حجم نامشخص';
    if (value < 1024 * 1024) return Math.max(1, Math.round(value / 1024)) + ' کیلوبایت';
    return (value / (1024 * 1024)).toLocaleString('fa-IR', { maximumFractionDigits: 1 }) + ' مگابایت';
};
const trackingForm = {
    task_id: props.correspondence.task_id ? String(props.correspondence.task_id) : '',
    external_registration_number: props.correspondence.external_registration_number ?? '',
    external_registered_at: props.correspondence.external_registered_at ?? '',
    dispatch_method: props.correspondence.dispatch_method ?? '',
    external_tracking_notes: props.correspondence.external_tracking_notes ?? '',
    sent_at: props.correspondence.sent_at ? props.correspondence.sent_at.slice(0, 10) : '',
};
function saveExternalTracking(): void {
    router.put(route('correspondence.external-tracking.update', props.correspondence.id), {
        ...trackingForm,
        task_id: trackingForm.task_id || null,
        external_registration_number: trackingForm.external_registration_number || null,
        external_registered_at: trackingForm.external_registered_at || null,
        dispatch_method: trackingForm.dispatch_method || null,
        external_tracking_notes: trackingForm.external_tracking_notes || null,
        sent_at: trackingForm.sent_at || null,
    }, { preserveScroll: true });
}
function registerCorrespondence(): void {
    if (window.confirm('پس از ثبت نهایی، شماره مکاتبه ثابت خواهد شد. ادامه می‌دهید؟')) {
        router.post(route('correspondence.register', props.correspondence.id), {}, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="correspondence.subject" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl font-bold text-slate-900">{{ correspondence.subject }}</h1>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="correspondence.status === 'registered' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">{{ statusLabels[correspondence.status] }}</span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">{{ typeLabels[correspondence.type] }} <span v-if="correspondence.registration_number">· شماره ثبت {{ correspondence.registration_number }}</span></p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Link :href="route('correspondence.index')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">بازگشت به فهرست</Link>
                    <button v-if="canRegister && correspondence.status === 'draft'" type="button" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700" @click="registerCorrespondence">ثبت نهایی</button>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-5 p-4 sm:p-6">
            <div v-if="page.props.errors?.registration" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm leading-6 text-rose-800" role="alert">
                <p class="font-bold">ثبت نهایی انجام نشد</p>
                <p class="mt-1">{{ page.props.errors.registration }}</p>
            </div>
            <div v-if="page.props.flash?.success" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm leading-6 text-emerald-800" role="status">
                {{ page.props.flash.success }}
            </div>
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-medium text-slate-500">شماره ثبت</p><p class="mt-2 font-semibold text-slate-900">{{ correspondence.registration_number ?? 'پس از ثبت نهایی مشخص می‌شود' }}</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-medium text-slate-500">واحد ایجادکننده</p><p class="mt-2 font-semibold text-slate-900">{{ correspondence.created_department?.name ?? 'تعیین نشده' }}</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-medium text-slate-500">واحد گیرنده</p><p class="mt-2 font-semibold text-slate-900">{{ correspondence.recipient_department?.name ?? 'هنوز تعیین نشده' }}</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-medium text-slate-500">ایجادکننده</p><p class="mt-2 font-semibold text-slate-900">{{ correspondence.creator?.name ?? 'نامشخص' }}</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-medium text-slate-500">تاریخ ایجاد</p><p class="mt-2 font-semibold text-slate-900">{{ formatDateTime(correspondence.created_at) }}</p></div>
            </section>

            <section class="rounded-2xl border border-indigo-100 bg-white shadow-sm">
                <div class="border-b border-indigo-100 px-5 py-4">
                    <h2 class="font-bold text-slate-900">گردش و ارجاعات نامه</h2>
                    <p class="mt-1 text-sm text-slate-600">وضعیت فعلی: {{ correspondence.workflow_state }} <span v-if="correspondence.current_department"> · واحد مسئول: {{ correspondence.current_department.name }}</span></p>
                </div>
                <div class="space-y-4 p-5">
                    <div v-if="correspondence.copies.length" class="rounded-xl bg-sky-50 p-4">
                        <p class="text-sm font-semibold text-sky-900">رونوشت جهت اطلاع</p>
                        <p class="mt-1 text-sm text-sky-800">{{ correspondence.copies.map(item => item.name).join('، ') }}</p>
                        <p class="mt-1 text-xs text-sky-700">واحدهای رونوشت‌گیرنده فقط امکان مشاهده نامه ثبت‌شده را دارند و نمی‌توانند در گردش آن اقدام کنند.</p>
                    </div>
                    <form v-if="canManageWorkflow || canReturnToManager" class="grid gap-3 rounded-xl border border-slate-200 p-4 sm:grid-cols-2" @submit.prevent="submitWorkflowAction">
                        <div v-if="canManageWorkflow">
                            <label class="mb-1 block text-sm font-medium text-slate-700">اقدام مدیر</label>
                            <select v-model="actionForm.action_type" class="w-full rounded-xl border-slate-300 text-sm">
                                <option value="manager_approve">تأیید برای ثبت نهایی</option>
                                <option value="manager_refer">ارجاع به واحد دیگر</option>
                                <option value="request_task">درخواست شروع تسک</option>
                                <option value="request_project">درخواست شروع پروژه</option>
                                <option value="request_letter">درخواست تهیه نامه جدید</option>
                                <option value="reject_request">رد درخواست</option>
                                <option value="close_without_result">خاتمه بدون نتیجه</option>
                            </select>
                        </div>
                        <div v-else>
                            <label class="mb-1 block text-sm font-medium text-slate-700">اقدام</label>
                            <select v-model="actionForm.action_type" class="w-full rounded-xl border-slate-300 text-sm">
                                <option value="employee_return_to_manager">ثبت توضیح و بازگشت به مدیر</option>
                            </select>
                        </div>
                        <div v-if="canManageWorkflow && ['manager_refer', 'request_task', 'request_project', 'request_letter'].includes(actionForm.action_type)">
                            <label class="mb-1 block text-sm font-medium text-slate-700">واحد مقصد</label>
                            <select v-model="actionForm.to_department_id" required class="w-full rounded-xl border-slate-300 text-sm">
                                <option value="">انتخاب واحد مقصد</option>
                                <option v-for="department in workflowDepartments" :key="department.id" :value="department.id">{{ department.name }}</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-slate-700">توضیح / شرح اقدام</label>
                            <textarea v-model="actionForm.note" rows="3" maxlength="10000" class="w-full rounded-xl border-slate-300 text-sm" placeholder="توضیح اقدام یا علت ارجاع را وارد کنید"></textarea>
                            <p v-if="page.props.errors?.workflow_action" class="mt-1 text-sm text-rose-700">{{ page.props.errors.workflow_action }}</p>
                        </div>
                        <div class="sm:col-span-2 flex justify-end">
                            <button type="submit" :disabled="actionForm.processing" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">ثبت اقدام گردش نامه</button>
                        </div>
                    </form>
                    <div v-if="correspondence.actions.length" class="space-y-3">
                        <h3 class="text-sm font-bold text-slate-800">تاریخچه اقدامات</h3>
                        <div v-for="item in correspondence.actions" :key="item.id" class="rounded-xl border border-slate-200 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-900">{{ actionLabels[item.action_type] ?? item.action_type }}</p>
                                <span class="text-xs text-slate-500">{{ formatDateTime(item.created_at) }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">ثبت‌کننده: {{ item.actor?.name ?? 'کاربر حذف‌شده' }}<span v-if="item.fromDepartment"> · از {{ item.fromDepartment.name }}</span><span v-if="item.toDepartment"> · به {{ item.toDepartment.name }}</span></p>
                            <p v-if="item.note" class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ item.note }}</p>
                        </div>
                    </div>
                    <p v-else class="text-sm text-slate-500">هنوز اقدامی در تاریخچه گردش نامه ثبت نشده است.</p>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-900">مشخصات مکاتبه</h2></div>
                <dl class="grid gap-x-8 gap-y-5 p-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="text-xs text-slate-500">فرستنده</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ correspondence.sender_name ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">گیرنده</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ correspondence.recipient_name ?? '—' }}</dd></div>
                    <div v-if="correspondence.external_organization"><dt class="text-xs text-slate-500">سازمان بیرونی</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ correspondence.external_organization.name }}</dd></div>
                    <div v-if="correspondence.external_department"><dt class="text-xs text-slate-500">واحد بیرونی</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ correspondence.external_department.name }}</dd></div>
                    <div><dt class="text-xs text-slate-500">ثبت‌کننده</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ correspondence.registrar?.name ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">تاریخ دریافت</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ formatDate(correspondence.received_at) }}</dd></div>
                    <div><dt class="text-xs text-slate-500">تاریخ ارسال</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ formatDate(correspondence.sent_at) }}</dd></div>
                    <div><dt class="text-xs text-slate-500">تاریخ ثبت نهایی</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ formatDateTime(correspondence.registered_at) }}</dd></div>
                </dl>
            </section>

            <section v-if="correspondence.type === 'outgoing'" class="rounded-2xl border border-indigo-100 bg-white shadow-sm">
                <div class="border-b border-indigo-100 px-5 py-4">
                    <h2 class="font-bold text-slate-900">پیگیری نامه صادره</h2>
                    <p class="mt-1 text-sm text-slate-500">اطلاعات ارسال و شماره ثبت نامه در سازمان مقصد مستقل از وضعیت تکمیل تسک نگهداری می‌شود.</p>
                </div>
                <form class="grid gap-4 p-5 sm:grid-cols-2" @submit.prevent="saveExternalTracking">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">تسک مرتبط</label>
                        <select v-model="trackingForm.task_id" :disabled="!canUpdateExternalTracking" class="w-full rounded-xl border-slate-300 text-sm">
                            <option value="">بدون تسک مرتبط</option>
                            <option v-for="task in trackableTasks" :key="task.id" :value="String(task.id)">#{{ task.id }} — {{ task.title }}</option>
                            <option v-if="correspondence.task && !trackableTasks.some(task => task.id === correspondence.task?.id)" :value="String(correspondence.task.id)">#{{ correspondence.task.id }} — {{ correspondence.task.title }}</option>
                        </select>
                        <p v-if="correspondence.task" class="mt-1 text-xs text-slate-500">تسک فعلی: {{ correspondence.task.title }} ({{ correspondence.task.status }})</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">شماره ثبت در سازمان مقصد</label>
                        <input v-model="trackingForm.external_registration_number" :disabled="!canUpdateExternalTracking" maxlength="255" class="w-full rounded-xl border-slate-300 text-sm" placeholder="مثلاً شماره وارده در دبیرخانه مقصد" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">تاریخ ثبت در سازمان مقصد</label>
                        <input v-model="trackingForm.external_registered_at" :disabled="!canUpdateExternalTracking" type="date" class="w-full rounded-xl border-slate-300 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">روش ارسال</label>
                        <select v-model="trackingForm.dispatch_method" :disabled="!canUpdateExternalTracking" class="w-full rounded-xl border-slate-300 text-sm">
                            <option value="">انتخاب نشده</option>
                            <option value="official_portal">سامانه مکاتبات</option>
                            <option value="email">ایمیل</option>
                            <option value="courier">پیک / پست</option>
                            <option value="in_person">تحویل حضوری</option>
                            <option value="other">سایر</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">تاریخ ارسال</label>
                        <input v-model="trackingForm.sent_at" :disabled="!canUpdateExternalTracking" type="date" class="w-full rounded-xl border-slate-300 text-sm" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">توضیحات پیگیری</label>
                        <textarea v-model="trackingForm.external_tracking_notes" :disabled="!canUpdateExternalTracking" rows="3" maxlength="10000" class="w-full rounded-xl border-slate-300 text-sm" placeholder="شماره رهگیری، پاسخ سازمان مقصد یا توضیحات تکمیلی"></textarea>
                    </div>
                    <div v-if="page.props.errors?.external_tracking" class="sm:col-span-2 rounded-lg bg-rose-50 p-3 text-sm text-rose-700">{{ page.props.errors.external_tracking }}</div>
                    <div v-if="canUpdateExternalTracking" class="sm:col-span-2 flex justify-end">
                        <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">ذخیره اطلاعات پیگیری</button>
                    </div>
                    <p v-else class="sm:col-span-2 text-xs text-slate-500">برای تغییر اطلاعات پیگیری، دسترسی دبیرخانه، ایجادکننده نامه یا مسئول تسک لازم است.</p>
                </form>
            </section>

            <section v-if="correspondence.type === 'outgoing'" class="rounded-2xl border border-indigo-100 bg-white shadow-sm">
                <div class="border-b border-indigo-100 px-5 py-4">
                    <h2 class="font-bold text-slate-900">پیگیری نامه صادره</h2>
                    <p class="mt-1 text-sm text-slate-500">اطلاعات ارسال و شماره ثبت نامه در سازمان مقصد مستقل از وضعیت تکمیل تسک نگهداری می‌شود.</p>
                </div>
                <form class="grid gap-4 p-5 sm:grid-cols-2" @submit.prevent="saveExternalTracking">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">تسک مرتبط</label>
                        <select v-model="trackingForm.task_id" :disabled="!canUpdateExternalTracking" class="w-full rounded-xl border-slate-300 text-sm">
                            <option value="">بدون تسک مرتبط</option>
                            <option v-for="task in trackableTasks" :key="task.id" :value="String(task.id)">#{{ task.id }} — {{ task.title }}</option>
                            <option v-if="correspondence.task && !trackableTasks.some(task => task.id === correspondence.task?.id)" :value="String(correspondence.task.id)">#{{ correspondence.task.id }} — {{ correspondence.task.title }}</option>
                        </select>
                        <p v-if="correspondence.task" class="mt-1 text-xs text-slate-500">تسک فعلی: {{ correspondence.task.title }} ({{ correspondence.task.status }})</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">شماره ثبت در سازمان مقصد</label>
                        <input v-model="trackingForm.external_registration_number" :disabled="!canUpdateExternalTracking" maxlength="255" class="w-full rounded-xl border-slate-300 text-sm" placeholder="مثلاً شماره وارده در دبیرخانه مقصد" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">تاریخ ثبت در سازمان مقصد</label>
                        <input v-model="trackingForm.external_registered_at" :disabled="!canUpdateExternalTracking" type="date" class="w-full rounded-xl border-slate-300 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">روش ارسال</label>
                        <select v-model="trackingForm.dispatch_method" :disabled="!canUpdateExternalTracking" class="w-full rounded-xl border-slate-300 text-sm">
                            <option value="">انتخاب نشده</option>
                            <option value="official_portal">سامانه مکاتبات</option>
                            <option value="email">ایمیل</option>
                            <option value="courier">پیک / پست</option>
                            <option value="in_person">تحویل حضوری</option>
                            <option value="other">سایر</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">تاریخ ارسال</label>
                        <input v-model="trackingForm.sent_at" :disabled="!canUpdateExternalTracking" type="date" class="w-full rounded-xl border-slate-300 text-sm" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">توضیحات پیگیری</label>
                        <textarea v-model="trackingForm.external_tracking_notes" :disabled="!canUpdateExternalTracking" rows="3" maxlength="10000" class="w-full rounded-xl border-slate-300 text-sm" placeholder="شماره رهگیری، پاسخ سازمان مقصد یا توضیحات تکمیلی"></textarea>
                    </div>
                    <div v-if="page.props.errors?.external_tracking" class="sm:col-span-2 rounded-lg bg-rose-50 p-3 text-sm text-rose-700">{{ page.props.errors.external_tracking }}</div>
                    <div v-if="canUpdateExternalTracking" class="sm:col-span-2 flex justify-end">
                        <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">ذخیره اطلاعات پیگیری</button>
                    </div>
                    <p v-else class="sm:col-span-2 text-xs text-slate-500">برای تغییر اطلاعات پیگیری، دسترسی دبیرخانه، ایجادکننده نامه یا مسئول تسک لازم است.</p>
                </form>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-900">متن نامه</h2></div>
                <div v-if="correspondence.body" class="correspondence-body p-5 text-sm leading-8 text-slate-800" dir="rtl" :style="{ fontFamily: correspondenceFontFamily }" v-html="correspondence.body"></div>
                <div v-else class="p-5 text-sm text-slate-500">متن داخلی ثبت نشده است؛ محتوای نامه در فایل پیوست قرار دارد.</div>
            </section>

            <section v-if="correspondence.type === 'incoming' || originalLetters.length" class="rounded-2xl border border-indigo-100 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-indigo-100 px-5 py-4"><h2 class="font-bold text-slate-900">اصل نامه دریافتی</h2><span class="text-xs text-slate-500">{{ originalLetters.length }} فایل</span></div>
                <div v-if="originalLetters.length" class="divide-y divide-slate-100">
                    <div v-for="file in originalLetters" :key="file.id" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/></svg></span>
                            <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-900">{{ file.title || file.file_name }}</p><p class="mt-1 truncate text-xs text-slate-500">{{ file.file_name }} · {{ formatSize(file.size) }}</p></div>
                        </div>
                        <div class="flex flex-wrap gap-2"><a :href="route('documents.download', file.id) + '?inline=1'" target="_blank" rel="noopener" class="rounded-lg border border-indigo-200 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">مشاهده</a><a :href="route('documents.download', file.id)" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">دریافت فایل</a></div>
                    </div>
                </div>
                <div v-else class="px-5 py-6 text-sm text-slate-500">فایل اصل نامه‌ای برای این مکاتبه ثبت نشده است.</div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-900">پیوست‌ها</h2><span class="text-xs text-slate-500">{{ attachments.length }} فایل</span></div>
                <div v-if="attachments.length" class="divide-y divide-slate-100">
                    <div v-for="file in attachments" :key="file.id" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/></svg></span>
                            <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-900">{{ file.title || file.file_name }}</p><p class="mt-1 truncate text-xs text-slate-500">{{ file.file_name }} · {{ formatSize(file.size) }}</p></div>
                        </div>
                        <div class="flex flex-wrap gap-2"><a :href="route('documents.download', file.id) + '?inline=1'" target="_blank" rel="noopener" class="rounded-lg border border-indigo-200 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">مشاهده</a><a :href="route('documents.download', file.id)" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">دریافت فایل</a></div>
                    </div>
                </div>
                <div v-else class="px-5 py-8 text-center text-sm text-slate-500">برای این مکاتبه پیوستی ثبت نشده است.</div>
            </section>

            <section v-if="correspondence.reply_to || correspondence.replies.length" class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-900">ارتباط پاسخ‌ها</h2></div>
                <div class="space-y-3 p-5">
                    <div v-if="correspondence.reply_to" class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">این نامه در پاسخ به</p><Link :href="route('correspondence.show', correspondence.reply_to.id)" class="mt-1 inline-block text-sm font-semibold text-indigo-700 hover:underline">{{ correspondence.reply_to.subject }}</Link><p class="mt-1 text-xs text-slate-500">شماره ثبت: {{ correspondence.reply_to.registration_number ?? '—' }}</p></div>
                    <div v-if="correspondence.replies.length" class="space-y-2"><p class="text-xs text-slate-500">پاسخ‌های مرتبط</p><Link v-for="reply in correspondence.replies" :key="reply.id" :href="route('correspondence.show', reply.id)" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 p-4 hover:border-indigo-200 hover:bg-indigo-50/40"><span class="text-sm font-semibold text-indigo-700">{{ reply.subject }}</span><span class="text-xs text-slate-500">{{ reply.registration_number ?? 'بدون شماره ثبت' }}</span></Link></div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.correspondence-body :deep(p) { margin: 0 0 0.75rem; }
.correspondence-body :deep(ul) { list-style: disc; padding-right: 1.5rem; margin: 0.75rem 0; }
.correspondence-body :deep(ol) { list-style: decimal; padding-right: 1.5rem; margin: 0.75rem 0; }
.correspondence-body :deep(blockquote) { border-right: 3px solid #cbd5e1; padding-right: 1rem; color: #475569; }
.correspondence-body :deep(table) { width: 100%; border-collapse: collapse; }
.correspondence-body :deep(th), .correspondence-body :deep(td) { border: 1px solid #cbd5e1; padding: 0.5rem; }
</style>
