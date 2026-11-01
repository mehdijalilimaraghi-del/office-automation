<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';
import DepartmentTreeSelect from '@/Components/DepartmentTreeSelect.vue';
import JalaliDatePicker from '@/Components/JalaliDatePicker.vue';

interface RelatedCorrespondence {
    id: number;
    subject: string;
    letter_number: string | null;
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
    letter_number: string | null;
    registration_number: string | null;
    registered_at: string | null;
    created_at: string | null;
    sender_name: string | null;
    recipient_name: string | null;
    received_at: string | null;
    sent_at: string | null;
    task_id: number | null;
    project_id: number | null;
    project: { id: number; name: string; code: string | null } | null;
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
    actions: Array<{ id: number; action_type: string; note: string | null; created_at: string; actor: { id: number; name: string } | null; from_department: { id: number; name: string } | null; to_department: { id: number; name: string } | null; to_user?: { id: number; name: string } | null }>;
    copies: Array<{ id: number; name: string }>;
}

interface WorkflowInstanceView {
    id: number;
    status: string;
    definition: { id: number; name: string } | null;
    steps: Array<{ id: number; status: string; task: { id: number; title: string; status: string; assignee: { id: number; name: string } | null } }>;
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
    taskInstructionUrl: string | null;
    canSubmitGeneralRequest: boolean;
    canReviewGeneralRequest: boolean;
    canRecallReferral: boolean;
    generalRequestType: string | null;
    requestedLetterCreateUrl: string | null;
    canCreateRequestedLetter: boolean;
    canManagerCreateRequestedLetter: boolean;
    canDelegateLetterDraft: boolean;
    canPrepareRequestedLetterDraft: boolean;
    workflowDefinitions: Array<{ id: number; name: string }>;
    workflowInstance: WorkflowInstanceView | null;
    canCreateProject: boolean;
    responseDepartments: { id: number; name: string; manager_id: number }[];
    responseDepartmentDefaultId: string | number | null;
    correspondenceFontFamily: string;
    trackableTasks: { id: number; title: string; status: string }[];
    canUpdateExternalTracking: boolean;
    workflowDepartments: { id: number; name: string }[];
    workflowUsers: { id: number; name: string }[];
    copyDepartments: { id: number; name: string }[];
    canAddCopies: boolean;
    canReturnToManager: boolean;
    canManageWorkflow: boolean;
    canProjectEmployeeReview: boolean;
    canReviewProjectRequest: boolean;
    canSecretariatReturn: boolean;
    canResubmitToSecretariat: boolean;
}>();

const page = usePage<PageProps<{ errors: Record<string, string>; flash?: { success?: string } }>>();

const referralActions = props.correspondence.actions.filter((item) => Boolean(item.to_department) || ['manager_refer', 'secretariat_initial_referral', 'request_task', 'request_project', 'request_letter'].includes(item.action_type));

const actionLabels: Record<string, string> = {
    submit_result: 'ثبت نتیجه بررسی و ارسال به مدیر',
    manager_approve: 'تأیید مدیر برای ثبت نهایی',
    manager_refer: 'ارجاع',
    manager_recall_referral: 'پس گرفتن ارجاع',
    secretariat_return: 'برگشت برای اصلاح توسط دبیرخانه',
    resubmit_to_secretariat: 'ارسال مجدد به دبیرخانه',
    dispatched: 'ثبت ارسال نهایی',
    request_task: 'درخواست شروع تسک',
    request_project: 'درخواست شروع پروژه',
    request_letter: 'درخواست تهیه نامه جدید',
    pm_request_refer: 'درخواست ارجاع به واحد دیگر',
    pm_request_project: 'درخواست پروژه',
    pm_request_reject: 'رد درخواست',
    pm_request_return: 'برگشت جهت اصلاح',
    pm_manager_approve_request: 'تأیید درخواست کارمند',
    pm_manager_reject_request: 'عدم تأیید درخواست کارمند و بازگشت برای بررسی مجدد',
    confirm_result: 'تأیید نتیجه و خاتمه گردش',
    secretariat_initial_referral: 'ارجاع اولیه دبیرخانه',
    copy_added: 'ثبت رونوشت جهت اطلاع',
    project_created: 'ایجاد پروژه و تسک پاسخ',
    employee_request_none: 'درخواست کارمند: بدون درخواست',
    employee_request_task: 'درخواست کارمند: ایجاد دستورکار',
    employee_request_letter: 'درخواست کارمند: ثبت نامه',
    employee_request_workflow: 'درخواست کارمند: ثبت گردش کار',
    employee_request_project: 'درخواست کارمند: ایجاد پروژه',
    manager_reject_employee_request: 'رد درخواست و بازگشت به کارمند',
    manager_refer_project_management: 'ارجاع به واحد مدیریت پروژه‌ها',
    manager_create_task: 'ایجاد دستورکار مرتبط با نامه',
    manager_create_requested_letter: 'مدیر: ایجاد نامه در پاسخ به درخواست',
    manager_assign_letter_draft: 'ارجاع برای تهیه پیش‌نویس نامه',
    requested_letter_draft_created: 'ایجاد پیش‌نویس نامه مرتبط',
    requested_letter_registered: 'ثبت نهایی نامه پاسخ مرتبط',
    manager_start_requested_workflow: 'شروع گردش کار درخواستی',
};
const copyForm = useForm({ department_ids: [] as number[] });
function toggleCopyDepartment(departmentId: number, checked: boolean): void {
    copyForm.department_ids = checked
        ? [...new Set([...copyForm.department_ids, departmentId])]
        : copyForm.department_ids.filter((id) => id !== departmentId);
}
function submitCopies(): void {
    copyForm.post(route('correspondence.copies.store', props.correspondence.id), {
        preserveScroll: true,
        onSuccess: () => { copyForm.reset(); },
    });
}

const projectForm = useForm({
    name: 'پاسخ به نامه: ' + props.correspondence.subject,
    description: '',
    response_department_id: props.responseDepartmentDefaultId ? Number(props.responseDepartmentDefaultId) : '' as number | '',
});
function createResponseProject(): void {
    projectForm.post(route('correspondence.create-project', props.correspondence.id), { preserveScroll: true });
}

const actionForm = useForm({
    action_type: props.canSubmitGeneralRequest ? 'employee_request_none' : props.canReviewGeneralRequest ? (props.generalRequestType === 'employee_request_none' ? 'manager_refer' : props.generalRequestType === 'employee_request_task' ? 'manager_create_task' : props.generalRequestType === 'employee_request_project' ? 'manager_refer_project_management' : props.generalRequestType === 'employee_request_workflow' && props.workflowDefinitions.length ? 'manager_start_requested_workflow' : props.generalRequestType === 'employee_request_letter' && props.canManagerCreateRequestedLetter ? 'manager_create_requested_letter' : props.generalRequestType === 'employee_request_letter' && props.canDelegateLetterDraft ? 'manager_assign_letter_draft' : 'manager_reject_employee_request') : props.canRecallReferral ? 'manager_recall_referral' : props.canProjectEmployeeReview ? 'pm_request_project' : props.canReviewProjectRequest ? 'pm_manager_approve_request' : props.canSecretariatReturn ? 'secretariat_return' : props.canResubmitToSecretariat ? 'resubmit_to_secretariat' : props.canManageWorkflow ? 'manager_refer' : 'submit_result',
    note: '',
    target_type: 'department' as 'department' | 'user',
    to_department_id: '' as number | '',
    to_user_id: '' as number | '',
    workflow_definition_id: '' as number | '',
});
function submitWorkflowAction(): void {
    actionForm.post(route('correspondence.actions.store', props.correspondence.id), {
        preserveScroll: true,
        onSuccess: () => {
            actionForm.note = '';
            actionForm.to_department_id = '';
            actionForm.to_user_id = '';
        },
    });
}

const typeLabels: Record<string, string> = { incoming: 'وارده', outgoing: 'صادره', internal: 'داخلی' };
const statusLabels: Record<string, string> = { draft: 'پیش‌نویس', registered: 'ثبت‌شده' };
const workflowStateLabels: Record<string, string> = {
    draft: 'پیش‌نویس', awaiting_registration: 'در انتظار ثبت نهایی', awaiting_manager_approval: 'در انتظار تأیید مدیر', approved_for_registration: 'تأییدشده برای ثبت نهایی', manager_review: 'در انتظار تصمیم مدیر', project_review_pending_manager: 'در انتظار تصمیم مدیر واحد پروژه‌ها', project_request_approved: 'درخواست پروژه تأیید شده؛ در انتظار ایجاد پروژه', result_pending_manager: 'نتیجه ثبت شده؛ در انتظار تصمیم مدیر', general_review_pending_manager: 'در انتظار بررسی درخواست کارمند توسط مدیر', letter_creation_by_manager: 'در انتظار ایجاد نامه توسط مدیر', letter_draft_pending_employee: 'در انتظار تهیه پیش‌نویس توسط کارمند', letter_draft_pending_manager: 'پیش‌نویس ایجاد شده؛ در انتظار بررسی مدیر', workflow_started: 'گردش کار مرتبط آغاز شده', workflow_completed: 'گردش کار مرتبط تکمیل شده', letter_registered: 'نامه پاسخ مرتبط ثبت نهایی شده', completed_with_result: 'خاتمه‌یافته با نتیجه ثبت‌شده', in_progress: 'در حال گردش', assigned_to_employee: 'ارجاع‌شده به کارمند', awaiting_dispatch: 'در انتظار بررسی دبیرخانه و ارسال', returned_for_correction: 'برگشت برای اصلاح', project_created: 'پروژه و تسک پاسخ ایجاد شده', dispatched: 'ارسال نهایی‌شده',
};
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
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Link :href="route('correspondence.index')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">بازگشت به فهرست</Link>
                    <button v-if="canRegister && correspondence.status === 'draft'" type="button" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700" @click="registerCorrespondence">ثبت نهایی</button>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-screen-2xl space-y-5 p-4 sm:p-6">
            <div v-if="page.props.errors?.registration" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm leading-6 text-rose-800" role="alert">
                <p class="font-bold">ثبت نهایی انجام نشد</p>
                <p class="mt-1">{{ page.props.errors.registration }}</p>
            </div>
            <section v-if="canCreateProject" class="rounded-2xl border border-indigo-200 bg-indigo-50/60 p-5">
                <h2 class="font-bold text-slate-900">ایجاد پروژه برای پاسخ به نامه</h2>
                <p class="mt-1 text-sm text-slate-600">واحد پاسخ‌گو را برای این پروژه انتخاب کنید. فقط برای همین واحد بخش و تسک پاسخ ایجاد می‌شود.</p>
                <form class="mt-4 grid gap-4 md:grid-cols-2" @submit.prevent="createResponseProject">
                    <div>
                        <label class="mb-1 block text-sm font-medium">نام پروژه *</label>
                        <input v-model="projectForm.name" required maxlength="150" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" />
                        <p v-if="projectForm.errors.name" class="mt-1 text-xs text-rose-600">{{ projectForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">واحد مسئول پاسخ‌گویی *</label>
                        <select v-model="projectForm.response_department_id" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="">انتخاب واحد پاسخ‌گو</option>
                            <option v-for="department in responseDepartments" :key="department.id" :value="department.id">{{ department.name }}</option>
                        </select>
                        <p v-if="projectForm.errors.response_department_id" class="mt-1 text-xs text-rose-600">{{ projectForm.errors.response_department_id }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium">توضیحات</label>
                        <textarea v-model="projectForm.description" rows="2" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" />
                    </div>
                    <div class="md:col-span-2 flex justify-end">
                        <button type="submit" :disabled="projectForm.processing" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{{ projectForm.processing ? 'در حال ایجاد...' : 'ایجاد پروژه و تسک پاسخ' }}</button>
                    </div>
                </form>
                <p v-if="page.props.errors?.project" class="mt-2 text-sm text-rose-700">{{ page.props.errors?.project }}</p>
            </section>
            <section v-if="workflowInstance" class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-bold text-slate-900">گردش کار مرتبط</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ workflowInstance.definition?.name ?? 'گردش کار' }} · وضعیت: {{ workflowInstance.status }}</p>
                    </div>
                    <span class="text-xs text-slate-500">شناسه گردش: #{{ workflowInstance.id }}</span>
                </div>
                <div v-if="workflowInstance.steps.length" class="mt-3 space-y-2">
                    <div v-for="step in workflowInstance.steps" :key="step.id" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-100 p-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">{{ step.task.title }}</p>
                            <p class="mt-1 text-xs text-slate-500">وضعیت: {{ step.task.status }} · مسئول: {{ step.task.assignee?.name ?? 'تعیین‌نشده' }}</p>
                        </div>
                        <Link :href="route('tasks.show', step.task.id)" class="text-sm font-semibold text-indigo-700 hover:underline">مشاهده تسک</Link>
                    </div>
                </div>
            </section>
            <section v-if="correspondence.project" class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div><h2 class="font-bold">پروژه مرتبط</h2><p class="mt-1 text-sm text-slate-600">{{ correspondence.project.name }}</p></div>
                    <Link :href="route('projects.show', correspondence.project.id)" class="rounded-lg border px-4 py-2 text-sm font-semibold">مشاهده پروژه</Link>
                </div>
            </section>
            <div v-if="page.props.flash?.success" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm leading-6 text-emerald-800" role="status">
                {{ page.props.flash.success }}
            </div>
<div dir="ltr" class="grid items-start gap-5 xl:grid-cols-[minmax(0,0.7fr)_minmax(0,1.65fr)]">
                <div dir="rtl" class="min-w-0 space-y-5">
                    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-900">اطلاعات مکاتبه</h2></div>
                <dl class="divide-y divide-slate-100 p-5">
                    <div class="flex items-start justify-between gap-4 py-3"><dt class="shrink-0 text-xs text-slate-500">نوع نامه</dt><dd class="min-w-0 text-left text-sm font-medium text-slate-800">{{ typeLabels[correspondence.type] }}</dd></div>
                    <div class="flex items-start justify-between gap-4 py-3"><dt class="shrink-0 text-xs text-slate-500">وضعیت</dt><dd class="min-w-0 text-left text-sm font-medium text-slate-800">{{ statusLabels[correspondence.status] }}</dd></div>
                    <div class="flex items-start justify-between gap-4 py-3"><dt class="shrink-0 text-xs text-slate-500">شماره نامه</dt><dd class="min-w-0 text-left text-sm font-medium text-slate-800">{{ correspondence.letter_number ?? '—' }}</dd></div>
                    <div class="flex items-start justify-between gap-4 py-3"><dt class="shrink-0 text-xs text-slate-500">شماره ثبت</dt><dd class="min-w-0 text-left text-sm font-medium text-slate-800">{{ correspondence.registration_number ?? 'پس از ثبت نهایی مشخص می‌شود' }}</dd></div>
                    <div class="flex items-start justify-between gap-4 py-3"><dt class="shrink-0 text-xs text-slate-500">تاریخ ثبت نهایی</dt><dd class="min-w-0 text-left text-sm font-medium text-slate-800">{{ formatDateTime(correspondence.registered_at) }}</dd></div>
                    <div class="flex items-start justify-between gap-4 py-3"><dt class="shrink-0 text-xs text-slate-500">تاریخ ایجاد</dt><dd class="min-w-0 text-left text-sm font-medium text-slate-800">{{ formatDateTime(correspondence.created_at) }}</dd></div>
                    <div class="flex items-start justify-between gap-4 py-3"><dt class="shrink-0 text-xs text-slate-500">واحد ایجادکننده</dt><dd class="min-w-0 text-left text-sm font-medium text-slate-800">{{ correspondence.created_department?.name ?? 'تعیین نشده' }}</dd></div>
                    <div class="flex items-start justify-between gap-4 py-3"><dt class="shrink-0 text-xs text-slate-500">واحد گیرنده</dt><dd class="min-w-0 text-left text-sm font-medium text-slate-800">{{ correspondence.recipient_department?.name ?? 'هنوز تعیین نشده' }}</dd></div>
                    <div class="flex items-start justify-between gap-4 py-3"><dt class="shrink-0 text-xs text-slate-500">ایجادکننده</dt><dd class="min-w-0 text-left text-sm font-medium text-slate-800">{{ correspondence.creator?.name ?? 'نامشخص' }}</dd></div>
                    <div class="flex items-start justify-between gap-4 py-3"><dt class="shrink-0 text-xs text-slate-500">ثبت‌کننده</dt><dd class="min-w-0 text-left text-sm font-medium text-slate-800">{{ correspondence.registrar?.name ?? '—' }}</dd></div>
                    <div class="flex items-start justify-between gap-4 py-3"><dt class="shrink-0 text-xs text-slate-500">فرستنده</dt><dd class="min-w-0 text-left text-sm font-medium text-slate-800">{{ correspondence.sender_name ?? '—' }}</dd></div>
                    <div class="flex items-start justify-between gap-4 py-3"><dt class="shrink-0 text-xs text-slate-500">گیرنده</dt><dd class="min-w-0 text-left text-sm font-medium text-slate-800">{{ correspondence.recipient_name ?? '—' }}</dd></div>
                    <div v-if="correspondence.external_organization" class="flex items-start justify-between gap-4 py-3"><dt class="shrink-0 text-xs text-slate-500">سازمان بیرونی</dt><dd class="min-w-0 text-left text-sm font-medium text-slate-800">{{ correspondence.external_organization.name }}</dd></div>
                    <div v-if="correspondence.external_department" class="flex items-start justify-between gap-4 py-3"><dt class="shrink-0 text-xs text-slate-500">واحد بیرونی</dt><dd class="min-w-0 text-left text-sm font-medium text-slate-800">{{ correspondence.external_department.name }}</dd></div>
                    <div class="flex items-start justify-between gap-4 py-3"><dt class="shrink-0 text-xs text-slate-500">تاریخ دریافت</dt><dd class="min-w-0 text-left text-sm font-medium text-slate-800">{{ formatDate(correspondence.received_at) }}</dd></div>
                    <div class="flex items-start justify-between gap-4 py-3"><dt class="shrink-0 text-xs text-slate-500">تاریخ ارسال</dt><dd class="min-w-0 text-left text-sm font-medium text-slate-800">{{ formatDate(correspondence.sent_at) }}</dd></div>
                </dl>
            </section>
                                        
            
                                <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-900">{{ correspondence.type === 'outgoing' && correspondence.body && !correspondence.body.includes('<') ? 'فایل نامه' : 'پیوست‌های همراه نامه' }}</h2><span class="text-xs text-slate-500">{{ attachments.length }} فایل</span></div>
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
                </div>
                <div dir="rtl" class="min-w-0 space-y-5">
                                <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-900">{{ correspondence.type === 'incoming' ? 'توضیحات نامه' : (correspondence.type === 'outgoing' && correspondence.body && !correspondence.body.includes('<') ? 'توضیحات فایل نامه' : 'متن نامه') }}</h2></div>
                <div v-if="(correspondence.type === 'incoming' || (correspondence.type === 'outgoing' && correspondence.body && !correspondence.body.includes('<'))) && correspondence.body" class="whitespace-pre-wrap p-5 text-sm leading-8 text-slate-800" dir="rtl">{{ correspondence.body }}</div>
                <div v-else-if="correspondence.type !== 'incoming' && correspondence.body" class="correspondence-body p-5 text-sm leading-8 text-slate-800" dir="rtl" :style="{ fontFamily: correspondenceFontFamily }" v-html="correspondence.body"></div>
                <div v-else class="p-5 text-sm text-slate-500">{{ correspondence.type === 'incoming' ? 'توضیحی برای این نامه ثبت نشده است.' : 'متن داخلی ثبت نشده است؛ محتوای نامه در فایل پیوست قرار دارد.' }}</div>
            </section>
                    <section v-if="correspondence.type === 'incoming' || originalLetters.length" class="rounded-2xl border border-indigo-100 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-indigo-100 px-5 py-4"><h2 class="font-bold text-slate-900">فایل نامه</h2><span class="text-xs text-slate-500">{{ originalLetters.length }} فایل</span></div>
                <div v-if="originalLetters.length" class="divide-y divide-slate-100">
                    <div v-for="file in originalLetters" :key="file.id" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/></svg></span>
                            <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-900">{{ file.title || file.file_name }}</p><p class="mt-1 truncate text-xs text-slate-500">{{ file.file_name }} · {{ formatSize(file.size) }}</p></div>
                        </div>
                        <div class="flex flex-wrap gap-2"><a :href="route('documents.download', file.id) + '?inline=1'" target="_blank" rel="noopener" class="rounded-lg border border-indigo-200 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">مشاهده</a><a :href="route('documents.download', file.id)" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">دریافت فایل</a></div>
                    </div>
                </div>
                <div v-else class="px-5 py-6 text-sm text-slate-500">فایلی برای نامه ثبت نشده است.</div>
            </section>
                                <section class="rounded-2xl border border-indigo-100 bg-white shadow-sm">
                <div class="border-b border-indigo-100 px-5 py-4">
                    <h2 class="font-bold text-slate-900">اقدامات نامه</h2>
                    <p class="mt-1 text-sm text-slate-600">وضعیت فعلی: {{ workflowStateLabels[correspondence.workflow_state] ?? correspondence.workflow_state }} <span v-if="correspondence.current_department"> · واحد مسئول: {{ correspondence.current_department.name }}</span></p>
                </div>
                <div class="space-y-4 p-5">
                    <div v-if="requestedLetterCreateUrl" class="rounded-xl border border-sky-200 bg-sky-50/50 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">تهیه نامه در پاسخ به درخواست</h3>
                                <p class="mt-1 text-xs leading-5 text-slate-600">{{ canPrepareRequestedLetterDraft ? 'پیش‌نویس را تهیه کنید؛ پس از ذخیره برای بررسی مدیر ارسال می‌شود.' : 'نامه جدید را در پاسخ به این مکاتبه ایجاد کنید.' }}</p>
                            </div>
                            <Link :href="requestedLetterCreateUrl" class="rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800">{{ canPrepareRequestedLetterDraft ? 'تهیه پیش‌نویس نامه' : 'ایجاد نامه جدید' }}</Link>
                        </div>
                    </div>
                    <div v-if="taskInstructionUrl" class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">ثبت دستورکار برای این نامه</h3>
                                <p class="mt-1 text-xs leading-5 text-slate-600">فرم فعلی ایجاد دستورکار باز می‌شود و دستورکار در پروژه عمومی، بخش همین واحد ایجاد و به این نامه متصل خواهد شد.</p>
                            </div>
                            <Link :href="taskInstructionUrl" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">ایجاد دستورکار مرتبط</Link>
                        </div>
                    </div>
                    <form v-if="canManageWorkflow || canReturnToManager || canProjectEmployeeReview || canReviewProjectRequest || canSecretariatReturn || canResubmitToSecretariat || canSubmitGeneralRequest || canReviewGeneralRequest" class="grid gap-3 rounded-xl border border-slate-200 p-4 sm:grid-cols-2" @submit.prevent="submitWorkflowAction">
                        <div v-if="canManageWorkflow">
                            <label class="mb-1 block text-sm font-medium text-slate-700">اقدام مدیر</label>
                            <select v-model="actionForm.action_type" class="w-full rounded-xl border-slate-300 text-sm">
                                <option v-if="correspondence.status === 'draft' && correspondence.workflow_state === 'awaiting_manager_approval'" value="manager_approve">تأیید برای ثبت نهایی</option>
                                <option v-if="canReviewProjectRequest" value="pm_manager_approve_request">تأیید درخواست کارمند</option>
                                <option v-if="canReviewProjectRequest" value="pm_manager_reject_request">عدم تأیید و بازگشت به کارمند</option>
                                <option v-if="canReviewGeneralRequest && generalRequestType === 'employee_request_none'" value="manager_refer">ارجاع نامه</option>
                                <option v-if="canReviewGeneralRequest && generalRequestType === 'employee_request_task'" value="manager_create_task">ایجاد دستورکار در پروژه عمومی</option>
                                <option v-if="canRecallReferral" value="manager_recall_referral">پس گرفتن ارجاع از کارمند</option>
                                <option v-if="canReviewGeneralRequest && generalRequestType === 'employee_request_project'" value="manager_refer_project_management">ارجاع به مدیریت پروژه‌ها</option>
                                <option v-if="canReviewGeneralRequest && generalRequestType === 'employee_request_workflow' && workflowDefinitions.length" value="manager_start_requested_workflow">ثبت گردش کار</option>
                                <option v-if="canReviewGeneralRequest && canManagerCreateRequestedLetter && generalRequestType === 'employee_request_letter'" value="manager_create_requested_letter">مدیر خودش نامه را ایجاد کند</option>
                                <option v-if="canReviewGeneralRequest && canDelegateLetterDraft && generalRequestType === 'employee_request_letter'" value="manager_assign_letter_draft">ارجاع به کارمند برای تهیه پیش‌نویس</option>
                                <option v-if="canReviewGeneralRequest && generalRequestType !== 'employee_request_none'" value="manager_reject_employee_request">رد درخواست و بازگشت به کارمند</option>
                                <option v-if="!canReviewProjectRequest && !canReviewGeneralRequest && !canRecallReferral && correspondence.status === 'registered' && correspondence.type !== 'outgoing'" value="manager_refer">ارجاع به</option>
                                <option v-if="canSecretariatReturn" value="secretariat_return">برگشت برای اصلاح</option>
                                <option v-if="canResubmitToSecretariat" value="resubmit_to_secretariat">ارسال مجدد به دبیرخانه</option>
                                <option v-if="correspondence.workflow_state === 'result_pending_manager' && ['incoming', 'internal'].includes(correspondence.type)" value="confirm_result">تأیید نتیجه و خاتمه گردش</option>
                            </select>
                        </div>
                        <div v-else-if="canSubmitGeneralRequest">
                            <label class="mb-1 block text-sm font-medium text-slate-700">نوع درخواست کارمند</label>
                            <select v-model="actionForm.action_type" class="w-full rounded-xl border-slate-300 text-sm">
                                <option value="employee_request_none">بدون درخواست</option>
                                <option value="employee_request_task">درخواست ایجاد دستورکار</option>
                                <option value="employee_request_letter">درخواست ثبت نامه</option>
                                <option value="employee_request_workflow">درخواست ثبت گردش کار</option>
                                <option value="employee_request_project">درخواست پروژه</option>
                            </select>
                        </div>
                        <div v-else-if="canProjectEmployeeReview">
                            <label class="mb-1 block text-sm font-medium text-slate-700">درخواست اقدام کارمند واحد پروژه‌ها</label>
                            <select v-model="actionForm.action_type" class="w-full rounded-xl border-slate-300 text-sm">
                                <option value="pm_request_refer">درخواست ارجاع به واحد دیگر</option>
                                <option value="pm_request_project">درخواست پروژه</option>
                                <option value="pm_request_reject">رد درخواست</option>
                                <option value="pm_request_return">برگشت جهت اصلاح</option>
                            </select>
                        </div>
                        <div v-else-if="canReturnToManager">
                            <label class="mb-1 block text-sm font-medium text-slate-700">اقدام</label>
                            <select v-model="actionForm.action_type" class="w-full rounded-xl border-slate-300 text-sm">
                                <option value="submit_result">ثبت نتیجه بررسی و ارسال به مدیر</option>
                            </select>
                        </div>
                        <div v-else-if="canSecretariatReturn">
                            <label class="mb-1 block text-sm font-medium text-slate-700">اقدام دبیرخانه</label>
                            <select v-model="actionForm.action_type" class="w-full rounded-xl border-slate-300 text-sm">
                                <option value="secretariat_return">برگشت نامه برای اصلاح</option>
                            </select>
                        </div>
                        <div v-else>
                            <label class="mb-1 block text-sm font-medium text-slate-700">اقدام</label>
                            <select v-model="actionForm.action_type" class="w-full rounded-xl border-slate-300 text-sm">
                                <option value="resubmit_to_secretariat">ارسال مجدد به دبیرخانه</option>
                            </select>
                        </div>
<p v-if="canReviewGeneralRequest && generalRequestType === 'employee_request_workflow' && !workflowDefinitions.length" class="sm:col-span-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">هیچ گردش کار فعالی تعریف نشده است؛ ابتدا باید یک Workflow فعال در سامانه تعریف شود.</p>
                        <div v-if="canReviewGeneralRequest && actionForm.action_type === 'manager_start_requested_workflow'" class="sm:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-slate-700">گردش کار فعال *</label>
                            <select v-model="actionForm.workflow_definition_id" required class="w-full rounded-xl border-slate-300 text-sm">
                                <option value="">انتخاب گردش کار</option>
                                <option v-for="definition in workflowDefinitions" :key="definition.id" :value="definition.id">{{ definition.name }}</option>
                            </select>
                        </div>
                        <template v-if="canProjectEmployeeReview && actionForm.action_type === 'pm_request_refer'">
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-slate-700">واحد مقصد درخواست ارجاع</label>
                                <DepartmentTreeSelect v-model="actionForm.to_department_id" :departments="workflowDepartments" placeholder="انتخاب واحد مقصد" :clearable="false" />
                            </div>
                        </template>
                        <template v-if="canManageWorkflow && actionForm.action_type === 'manager_refer'">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">نوع ارجاع</label>
                                <select v-model="actionForm.target_type" class="w-full rounded-xl border-slate-300 text-sm">
                                    <option value="department">واحد دیگر</option>
                                    <option value="user">کارمند همین واحد</option>
                                </select>
                            </div>
                            <div v-if="actionForm.target_type === 'department'">
                                <label class="mb-1 block text-sm font-medium text-slate-700">واحد مقصد</label>
                                <DepartmentTreeSelect v-model="actionForm.to_department_id" :departments="workflowDepartments" placeholder="انتخاب واحد مقصد" :clearable="false" />
                            </div>
                            <div v-else>
                                <label class="mb-1 block text-sm font-medium text-slate-700">کارمند مقصد</label>
                                <select v-model="actionForm.to_user_id" class="w-full rounded-xl border-slate-300 text-sm">
                                    <option value="">انتخاب کارمند</option>
                                    <option v-for="user in workflowUsers" :key="user.id" :value="String(user.id)">{{ user.name }}</option>
                                </select>
                            </div>
                        </template>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-slate-700">توضیح / شرح اقدام</label>
                            <textarea v-model="actionForm.note" rows="3" maxlength="10000" class="w-full rounded-xl border-slate-300 text-sm"   :required="['submit_result', 'confirm_result', 'secretariat_return', 'pm_request_refer', 'pm_request_project', 'pm_request_reject', 'pm_request_return', 'pm_manager_approve_request', 'pm_manager_reject_request', 'employee_request_none', 'employee_request_task', 'employee_request_letter', 'employee_request_workflow', 'employee_request_project', 'manager_reject_employee_request', 'manager_create_task', 'manager_refer_project_management', 'manager_create_requested_letter', 'manager_assign_letter_draft', 'manager_start_requested_workflow'].includes(actionForm.action_type)" placeholder="شرح بررسی، نتیجه یا علت برگشت/ارجاع را وارد کنید"></textarea>
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
                            <p class="mt-1 text-xs text-slate-500">ثبت‌کننده: {{ item.actor?.name ?? 'کاربر حذف‌شده' }}<span v-if="item.from_department"> · از {{ item.from_department.name }}</span><span v-if="item.to_department"> · به {{ item.to_department.name }}</span><span v-if="item.to_user"> · کاربر مقصد: {{ item.to_user.name }}</span></p>
                            <p v-if="item.note" class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ item.note }}</p>
                        </div>
                    </div>
                    <p v-else class="text-sm text-slate-500">هنوز اقدامی در تاریخچه گردش نامه ثبت نشده است.</p>
                </div>
            </section>
                </div>
            </div>
                        <section class="grid gap-5 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-900">لیست ارجاعات</h2></div>
                    <div v-if="referralActions.length" class="divide-y divide-slate-100">
                        <div v-for="item in referralActions" :key="item.id" class="px-5 py-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-900">{{ actionLabels[item.action_type] ?? item.action_type }}</p>
                                <span class="text-xs text-slate-500">{{ formatDateTime(item.created_at) }}</span>
                            </div>
                            <p class="mt-1 text-sm text-slate-700"><span v-if="item.from_department">از {{ item.from_department.name }}</span><span v-if="item.from_department && item.to_department"> ← </span><span v-if="item.to_department">به {{ item.to_department.name }}</span><span v-if="item.to_user">به کاربر {{ item.to_user.name }}</span><span v-if="!item.from_department && !item.to_department && !item.to_user">واحد مبدأ یا مقصد مشخص نشده</span></p>
                            <p class="mt-1 text-xs text-slate-500">ثبت‌کننده: {{ item.actor?.name ?? 'کاربر حذف‌شده' }}</p>
                            <p v-if="item.note" class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ item.note }}</p>
                        </div>
                    </div>
                    <p v-else class="px-5 py-6 text-sm text-slate-500">هنوز ارجاعی برای این نامه ثبت نشده است.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-900">لیست رونوشت‌ها</h2></div>
                    <div v-if="correspondence.copies.length" class="divide-y divide-slate-100">
                        <div v-for="copy in correspondence.copies" :key="copy.id" class="px-5 py-4">
                            <p class="text-sm font-semibold text-slate-900">{{ copy.name }}</p>
                            <p class="mt-1 text-xs text-slate-500">دریافت‌کننده رونوشت جهت اطلاع؛ مجوز اقدام در گردش ایجاد نمی‌کند.</p>
                        </div>
                    </div>
                    <p v-else class="px-5 py-6 text-sm text-slate-500">برای این نامه رونوشتی ثبت نشده است.</p>
                    <form v-if="canAddCopies && copyDepartments.length" class="space-y-3 border-t border-slate-100 p-4" @submit.prevent="submitCopies">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">افزودن رونوشت در ادامه گردش</h3>
                            <p class="mt-1 text-xs leading-5 text-slate-600">واحدهای موردنظر را برای اطلاع انتخاب کنید.</p>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label v-for="department in copyDepartments" :key="department.id" class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5">
                                <input type="checkbox" :checked="copyForm.department_ids.includes(department.id)" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @change="toggleCopyDepartment(department.id, ($event.target as HTMLInputElement).checked)" />
                                <span class="text-sm text-slate-800">{{ department.name }}</span>
                            </label>
                        </div>
                        <p v-if="copyForm.errors.department_ids" class="text-sm text-rose-700">{{ copyForm.errors.department_ids }}</p>
                        <button type="submit" :disabled="copyForm.processing || copyForm.department_ids.length === 0" class="rounded-xl bg-sky-700 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">افزودن رونوشت</button>
                    </form>
                </div>
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
                        <JalaliDatePicker v-model="trackingForm.external_registered_at" :disabled="!canUpdateExternalTracking" placeholder="انتخاب تاریخ ثبت خارجی" />
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
                        <JalaliDatePicker v-model="trackingForm.sent_at" :disabled="!canUpdateExternalTracking" placeholder="انتخاب تاریخ ارسال" />
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

            <section v-if="correspondence.reply_to || correspondence.replies.length" class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-900">ارتباط پاسخ‌ها</h2></div>
                <div class="space-y-3 p-5">
                    <div v-if="correspondence.reply_to" class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">این نامه در پاسخ به</p><Link :href="route('correspondence.show', correspondence.reply_to.id)" class="mt-1 inline-block text-sm font-semibold text-indigo-700 hover:underline">{{ correspondence.reply_to.subject }}</Link><p class="mt-1 text-xs text-slate-500">شماره نامه: {{ correspondence.reply_to.letter_number ?? correspondence.reply_to.registration_number ?? '—' }}</p></div>
                    <div v-if="correspondence.replies.length" class="space-y-2"><p class="text-xs text-slate-500">پاسخ‌های مرتبط</p><Link v-for="reply in correspondence.replies" :key="reply.id" :href="route('correspondence.show', reply.id)" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 p-4 hover:border-indigo-200 hover:bg-indigo-50/40"><span class="text-sm font-semibold text-indigo-700">{{ reply.subject }}</span><span class="text-xs text-slate-500">{{ reply.letter_number ?? reply.registration_number ?? 'بدون شماره' }}</span></Link></div>
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
