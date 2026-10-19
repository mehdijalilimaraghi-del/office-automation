<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import JalaliDatePicker from '@/Components/JalaliDatePicker.vue';
import CustomFieldsForm from '@/Components/CustomFieldsForm.vue';
import JalaliDateTimePicker from '@/Components/JalaliDateTimePicker.vue';
import { formatPersianDate, formatPersianDateTime } from '@/Utils/date';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface User { id: number; name: string; email?: string | null; mobile?: string | null; phone?: string | null; extension?: string | null; }
interface ExternalOrganizationOption { id: number; name: string; }
interface ExternalDepartmentOption { id: number; external_organization_id: number; name: string; }
interface ExternalContactOption { id: number; external_organization_id: number; external_department_id: number | null; name: string; position: string | null; mobile: string | null; phone: string | null; extension: string | null; email: string | null; organization?: { id: number; name: string } | null; department?: { id: number; name: string } | null; pivot?: { external_organization_id: number | null; external_department_id: number | null }; }
interface ExternalPartyOptions { external_organizations: ExternalOrganizationOption[]; external_departments: ExternalDepartmentOption[]; external_contacts: ExternalContactOption[]; }

interface TaskActivity {
    id: number;
    title: string;
    description: string | null;
    status: 'pending' | 'completed';
    activity_type: 'planned' | 'logged';
    sequence: number;
    assignee_id: number | null;
    assignee: User | null;
    creator: User | null;
    assignments: TaskAssignment[];
    completedBy: User | null;
    completed_by: User | null;
    started_at: string | null;
    created_at: string;
    completed_at: string | null;
}

interface Reminder {
    id: number;
    title: string;
    description: string | null;
    remind_at: string;
    status: string;
    user: User | null;
}
interface TaskAssignment {
    id: number;
    from_user: User | null;
    to_user: User | null;
    assigned_by: User | null;
    type: string;
    note: string | null;
    started_at: string | null;
    ended_at: string | null;
}
interface Task {
    id: number;
    title: string;
    description: string | null;
    status: string;
    priority: string;
    review_required: boolean;
    review_status: string;
    review_note: string | null;
    review_requested_at: string | null;
    reviewed_at: string | null;
    reviewed_by: User | null;
    is_active: boolean;
    start_date: string | null;
    due_date: string | null;
    assignee: User | null;
    creator: User | null;
    department: { id: number; name: string; manager: User | null } | null;
    external_organization: ExternalOrganizationOption | null;
    external_department: ExternalDepartmentOption | null;
    external_contacts: ExternalContactOption[];
    project: { id: number; name: string } | null;
    reminders: Reminder[];
    documents: TaskDocument[];
    activities: TaskActivity[];
    assignments: TaskAssignment[];
    comments: Array<{ id: number; body: string; user: User | null }>;
}
interface TimelineItem {
    id?: number;
    type: string;
    title: string;
    description: string | null;
    occurred_at: string | null;
    user: User | null;
    meta?: { started_at?: string | null; completed_at?: string | null };
}
interface Abilities {
    refer: boolean;
    comment: boolean;
    log_work: boolean;
    create_reminder: boolean;
    change_status: boolean;
    submit: boolean;
    review: boolean;
    update: boolean;
    delete: boolean;
    documents_create: boolean;
}

interface TaskDocument {
    id: number;
    purpose: string | null;
    document: {
        id: number;
        title: string;
        document_type: string | null;
        latest_version: {
            original_name: string;
            mime_type: string | null;
            size: number;
        } | null;
    } | null;
}

const props = defineProps<{
    task: Task;
    timeline: TimelineItem[];
    currentUserId: number;
    abilities: Abilities;
    assigneeOptions: User[];
    externalPartyOptions: ExternalPartyOptions;
    customFields: Array<{
        id: number;
        key: string;
        label: string;
        type: 'text' | 'textarea' | 'number' | 'date' | 'boolean' | 'select';
        is_required: boolean;
        options: string[] | null;
    }>;
    customFieldValues: Record<string, string | number | boolean | null>;
}>();

const commentForm = useForm({ body: '' });
const editCommentForm = useForm({ body: '' });
const editingCommentId = ref<number | null>(null);
const workLogForm = useForm({ title: '', description: '', started_at: '', completed_at: '' });
const editingWorkLogId = ref<number | null>(null);
const reminderForm = useForm({
    user_id: props.currentUserId,
    title: '',
    description: '',
    remind_at: '',
});
const referralForm = useForm({ assignee_id: '', note: '' });
const reviewForm = useForm({ note: '' });
const statusForm = useForm({ status: props.task.status });
const reminderStatusForm = useForm({ status: '' as 'completed' | 'cancelled' | '' });
const editReminderForm = useForm({ title: '', description: '', remind_at: '' });
const editingReminderId = ref<number | null>(null);
const activityForm = useForm({ title: '', description: '', activity_type: 'planned' as 'planned' | 'logged' });
const editingActivityId = ref<number | null>(null);
const showActivityModal = ref(false);
const showEditModal = ref(false);
const showReferralModal = ref(false);
const showReturnReviewModal = ref(false);
const showActivityHelp = ref(false);
const showCommentModal = ref(false);
const showWorkLogModal = ref(false);
const showReminderModal = ref(false);
const showEditCommentModal = ref(false);
const showEditReminderModal = ref(false);
const deleteForm = useForm({});
const documentForm = useForm({
    title: '',
    document_type: '',
    file: null as File | null,
});
const documentFileInput = ref<HTMLInputElement | null>(null);
const editForm = useForm({
    title: props.task.title,
    description: props.task.description ?? '',
    priority: props.task.priority,
    start_date: props.task.start_date ?? '',
    due_date: props.task.due_date ?? '',
    is_active: props.task.is_active,
    custom_fields: props.customFieldValues ?? {},
});
const externalForm = useForm({
    external_organization_id: null as number | null,
    external_department_id: null as number | null,
    external_contact_ids: [] as number[],
});
const showExternalModal = ref(false);
const editingExternalContactId = ref<number | null>(null);

const externalDepartments = computed(() => props.externalPartyOptions.external_departments.filter(item => item.external_organization_id === externalForm.external_organization_id));
const externalContacts = computed(() => props.externalPartyOptions.external_contacts.filter(item => item.external_organization_id === externalForm.external_organization_id && (externalForm.external_department_id === null || item.external_department_id === externalForm.external_department_id)));
const handleExternalOrganizationChange = (): void => {
    externalForm.external_department_id = null;
    externalForm.external_contact_ids = [];
};
const handleExternalDepartmentChange = (): void => {
    const allowedIds = new Set(externalContacts.value.map(item => item.id));
    externalForm.external_contact_ids = externalForm.external_contact_ids.filter(id => allowedIds.has(id));
};

const toggleExternalContact = (contactId: number): void => {
    const selected = externalForm.external_contact_ids.map(Number);
    externalForm.external_contact_ids = selected.includes(contactId)
        ? selected.filter(id => id !== contactId)
        : [...selected, contactId];
};

const openExternalModal = (): void => {
    editingExternalContactId.value = null;
    externalForm.reset();
    externalForm.clearErrors();
    showExternalModal.value = true;
};

const editExternalContact = (contactId: number): void => {
    const contact = props.task.external_contacts?.find(item => item.id === contactId);
    editingExternalContactId.value = contactId;
    externalForm.external_organization_id = contact?.pivot?.external_organization_id ?? contact?.external_organization_id ?? null;
    externalForm.external_department_id = contact?.pivot?.external_department_id ?? contact?.external_department_id ?? null;
    externalForm.external_contact_ids = [contactId];
    externalForm.clearErrors();
    showExternalModal.value = true;
};

const removeExternalContact = (contactId: number): void => {
    externalForm.external_contact_ids = [];
    editingExternalContactId.value = contactId;
    submitExternal();
};

const statusLabels: Record<string, string> = {
    todo: 'انجام نشده',
    in_progress: 'در حال انجام',
    completed: 'تکمیل شده',
    submitted: 'در انتظار بررسی مدیر',
    cancelled: 'لغو شده',
};
const priorityLabels: Record<string, string> = {
    low: 'کم',
    normal: 'عادی',
    high: 'زیاد',
    urgent: 'فوری',
};
const timelineLabels: Record<string, string> = {
    assignment: 'تخصیص یا ارجاع',
    status: 'تغییر وضعیت',
    work_log: 'گزارش فعالیت',
    comment: 'نظر',
    reminder: 'یادآور',
};

const statusClasses: Record<string, string> = {
    todo: 'bg-slate-100 text-slate-700 ring-slate-200',
    in_progress: 'bg-blue-50 text-blue-700 ring-blue-200',
    completed: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    submitted: 'bg-amber-50 text-amber-800 ring-amber-200',
    cancelled: 'bg-red-50 text-red-700 ring-red-200',
};
const priorityClasses: Record<string, string> = {
    low: 'bg-slate-100 text-slate-700 ring-slate-200',
    normal: 'bg-gray-100 text-gray-700 ring-gray-200',
    high: 'bg-amber-50 text-amber-700 ring-amber-200',
    urgent: 'bg-red-50 text-red-700 ring-red-200',
};
const timelineClasses: Record<string, string> = {
    assignment: 'bg-violet-50 text-violet-700 ring-violet-200',
    status: 'bg-blue-50 text-blue-700 ring-blue-200',
    work_log: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    comment: 'bg-amber-50 text-amber-700 ring-amber-200',
    reminder: 'bg-rose-50 text-rose-700 ring-rose-200',
};

const submitComment = (): void => {
    commentForm.post(route('tasks.comments.store', props.task.id), {
        preserveScroll: true,
        onSuccess: () => { commentForm.reset(); showCommentModal.value = false; },
    });
};
const editComment = (comment: { id: number; body: string }): void => {
    editingCommentId.value = comment.id;
    editCommentForm.body = comment.body;
    editCommentForm.clearErrors();
    showEditCommentModal.value = true;
};

const submitCommentEdit = (): void => {
    if (!editingCommentId.value) return;

    editCommentForm.put(route('tasks.comments.update', [props.task.id, editingCommentId.value]), {
        preserveScroll: true,
        onSuccess: () => {
            showEditCommentModal.value = false;
            editingCommentId.value = null;
            editCommentForm.reset();
        },
    });
};

const deleteComment = (commentId: number): void => {
    if (!window.confirm('آیا از حذف این نظر مطمئن هستید؟')) return;

    editCommentForm.delete(route('tasks.comments.destroy', [props.task.id, commentId]), {
        preserveScroll: true,
    });
};

const editReminder = (reminder: Reminder): void => {
    editingReminderId.value = reminder.id;
    editReminderForm.title = reminder.title;
    editReminderForm.description = reminder.description ?? '';
    editReminderForm.remind_at = reminder.remind_at ? reminder.remind_at.replace('T', ' ').slice(0, 19).replace(' ', 'T') : '';
    editReminderForm.clearErrors();
    showEditReminderModal.value = true;
};

const submitReminderEdit = (): void => {
    if (!editingReminderId.value) return;

    editReminderForm.put(route('tasks.reminders.update', [props.task.id, editingReminderId.value]), {
        preserveScroll: true,
        onSuccess: () => {
            showEditReminderModal.value = false;
            editingReminderId.value = null;
            editReminderForm.reset();
        },
    });
};

const deleteReminder = (reminderId: number): void => {
    if (!window.confirm('آیا از حذف این یادآوری مطمئن هستید؟')) return;

    editReminderForm.delete(route('tasks.reminders.destroy', [props.task.id, reminderId]), {
        preserveScroll: true,
    });
};

const submitWorkLog = (): void => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            workLogForm.reset();
            editingWorkLogId.value = null;
            showWorkLogModal.value = false;
        },
    };

    if (editingWorkLogId.value) {
        workLogForm.put(route('tasks.activities.update', [props.task.id, editingWorkLogId.value]), options);
        return;
    }

    workLogForm.post(route('tasks.work-logs.store', props.task.id), options);
};

const editWorkLog = (item: TaskActivity): void => {
    editingWorkLogId.value = item.id;
    workLogForm.title = item.title;
    workLogForm.description = item.description ?? '';
    workLogForm.started_at = item.started_at ? item.started_at.replace(' ', 'T').slice(0, 19) : '';
    workLogForm.completed_at = item.completed_at ? item.completed_at.replace(' ', 'T').slice(0, 19) : '';
    workLogForm.clearErrors();
    showWorkLogModal.value = true;
};

const deleteWorkLog = (workLogId: number): void => {
    if (!window.confirm('آیا از حذف این گزارش فعالیت مطمئن هستید؟')) return;

    workLogForm.delete(route('tasks.activities.destroy', [props.task.id, workLogId]), {
        preserveScroll: true,
    });
};
const submitReminder = (): void => {
    reminderForm.post(route('tasks.reminders.store', props.task.id), {
        preserveScroll: true,
        onSuccess: () => { reminderForm.reset('title', 'description', 'remind_at'); showReminderModal.value = false; },
    });
};

const handleDocumentFileChange = (event: Event): void => {
    const input = event.target as HTMLInputElement;

    documentForm.file = input.files?.[0] ?? null;

    if (documentForm.file && documentForm.title === '') {
        documentForm.title = documentForm.file.name;
    }
};

const deleteTaskDocument = (documentId: number): void => {
    if (!window.confirm('آیا از حذف این پیوست مطمئن هستید؟')) return;
    router.delete(route('tasks.documents.destroy', [props.task.id, documentId]), { preserveScroll: true });
};

const submitDocument = (): void => {
    documentForm.post(route('tasks.documents.store', props.task.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            documentForm.reset();
            documentForm.clearErrors();

            if (documentFileInput.value) {
                documentFileInput.value.value = '';
            }
        },
    });
};

const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return String(bytes) + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';

    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

const submitEdit = (): void => {
    editForm.put(route('tasks.update', props.task.id), {
        preserveScroll: true,
        onSuccess: () => { showEditModal.value = false; },
    });
};
const submitExternal = (): void => {
    externalForm.transform(data => ({
        ...data,
        replace_external_contact_id: editingExternalContactId.value,
    })).put(route('tasks.external-parties.update', props.task.id), {
        preserveScroll: true,
        onSuccess: () => {
            showExternalModal.value = false;
            editingExternalContactId.value = null;
            router.reload({ only: ['task'] });
        },
    });
};

const deleteTask = (): void => {
    if (!window.confirm(`آیا از حذف دستورکار «${props.task.title}» مطمئن هستید؟`)) {
        return;
    }

    deleteForm.delete(route('tasks.destroy', props.task.id), {
        preserveScroll: false,
    });
};

const submitReferral = (): void => {
    referralForm.patch(route('tasks.refer', props.task.id), {
        preserveScroll: true,
        onSuccess: () => { referralForm.reset(); showReferralModal.value = false; },
    });
};
const submitForReview = (): void => {
    if (reviewForm.processing) return;

    reviewForm.patch(route('tasks.submit', props.task.id), {
        preserveScroll: true,
        onSuccess: () => reviewForm.reset(),
    });
};
const approveReview = (): void => {
    if (reviewForm.processing) return;

    reviewForm.patch(route('tasks.approve', props.task.id), {
        preserveScroll: true,
        onSuccess: () => reviewForm.reset(),
    });
};
const returnFromReview = (): void => {
    if (reviewForm.processing) return;

    reviewForm.patch(route('tasks.return', props.task.id), {
        preserveScroll: true,
        onSuccess: () => { reviewForm.reset(); showReturnReviewModal.value = false; },
    });
};
const changeStatus = (): void => {
    statusForm.patch(route('tasks.change-status', props.task.id), {
        preserveScroll: true,
    });
};
const updateReminderStatus = (reminderId: number, status: 'completed' | 'cancelled'): void => {
    if (reminderStatusForm.processing) {
        return;
    }

    reminderStatusForm.status = status;
    reminderStatusForm.patch(
        route('tasks.reminders.update-status', [props.task.id, reminderId]),
        {
            preserveScroll: true,
            onFinish: () => reminderStatusForm.reset(),
        },
    );
};
const formatDateTime = formatPersianDateTime;
const isReviewLocked = computed(() => props.task.review_status === 'pending');
const isCurrentAssignee = computed(() => Number(props.task.assignee?.id ?? props.task.assignee_id) === Number(props.currentUserId));
const canEditTaskContent = computed(() => !isReviewLocked.value && isCurrentAssignee.value);
const nextActivity = computed(() => props.task.activities?.find(activity => activity.status === 'pending' && activity.activity_type === 'planned') ?? null);
const openActivityForm = (activity?: TaskActivity): void => {
    editingActivityId.value = activity?.id ?? null;
    activityForm.title = activity?.title ?? '';
    activityForm.description = activity?.description ?? '';
    activityForm.activity_type = activity?.activity_type ?? 'planned';
    activityForm.clearErrors();
    showActivityModal.value = true;
};
const submitActivity = (): void => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            activityForm.reset();
            activityForm.activity_type = 'planned';
            editingActivityId.value = null;
            showActivityModal.value = false;
        },
    };

    if (editingActivityId.value) {
        activityForm.put(route('tasks.activities.update', [props.task.id, editingActivityId.value]), options);
        return;
    }

    activityForm.post(route('tasks.activities.store', props.task.id), options);
};
const completeActivity = (activityId: number): void => {
    activityForm.patch(route('tasks.activities.complete', [props.task.id, activityId]), { preserveScroll: true });
};
const deleteActivity = (activityId: number): void => {
    if (!window.confirm('آیا از حذف این فعالیت مطمئن هستید؟')) return;

    activityForm.delete(route('tasks.activities.destroy', [props.task.id, activityId]), { preserveScroll: true });
};
const phoneLabel = (user: User): string | null => user.mobile || user.phone || null;
const pendingActivities = computed(() => props.task.activities?.filter(activity => activity.activity_type === 'planned' && activity.status === 'pending') ?? []);
const loggedActivities = computed(() => props.task.activities?.filter(activity => activity.activity_type === 'logged' && activity.status === 'completed') ?? []);
</script>

<template>
    <Head :title="task.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="min-w-0">
                    <Link :href="route('tasks.index')" class="text-sm text-muted-foreground hover:text-gray-900">← بازگشت به دستورکارها</Link>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">دستورکار #{{ task.id }}</span>
                        <span class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusClasses[task.status] ?? 'bg-gray-100 text-gray-700 ring-gray-200'">{{ statusLabels[task.status] ?? task.status }}</span>
                        <span class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="priorityClasses[task.priority] ?? 'bg-gray-100 text-gray-700 ring-gray-200'">اولویت {{ priorityLabels[task.priority] ?? task.priority }}</span>
                    </div>
                    <h2 class="mt-2 text-xl font-semibold text-gray-900">{{ task.title }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">مدیریت، پیگیری و ثبت سوابق دستورکار</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button v-if="abilities.submit && ['todo', 'in_progress'].includes(task.status)" type="button" :disabled="reviewForm.processing" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-800 disabled:opacity-50" @click="submitForReview">{{ reviewForm.processing ? 'در حال ارسال...' : 'ارسال برای بررسی مدیر' }}</button>
                    <button v-if="abilities.review && task.status === 'submitted' && task.review_status === 'pending'" type="button" :disabled="reviewForm.processing" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-800 disabled:opacity-50" @click="approveReview">{{ reviewForm.processing ? 'در حال ثبت...' : 'تأیید و تکمیل دستورکار' }}</button>
                    <button v-if="abilities.review && task.status === 'submitted' && task.review_status === 'pending'" type="button" :disabled="reviewForm.processing" class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800 hover:bg-amber-100 disabled:opacity-50" @click="showReturnReviewModal = true">برگشت برای اصلاح</button>
                    <button v-if="abilities.update && canEditTaskContent" type="button" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-gray-800" @click="showEditModal = true">ویرایش</button>
                    <button v-if="abilities.refer && !isReviewLocked" type="button" class="rounded-lg border bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50" @click="showReferralModal = true">ارجاع دستورکار</button>
                </div>
            </div>
        </template>

        <div class="task-show mx-auto max-w-7xl space-y-6 px-4 py-5 sm:px-6 lg:px-8">
            <section class="rounded-xl border p-4 shadow-sm" :class="task.review_status === 'pending' ? 'border-amber-200 bg-amber-50/70' : task.review_status === 'approved' ? 'border-emerald-200 bg-emerald-50/60' : task.review_status === 'rejected' ? 'border-red-200 bg-red-50/60' : 'border-gray-200 bg-white'">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h3 class="font-semibold">گردش بررسی دستورکار</h3><p class="mt-1 text-sm text-gray-700">{{ task.review_status === 'pending' ? 'این دستورکار در انتظار بررسی مدیر است.' : task.review_status === 'approved' ? 'مدیر دستورکار را تأیید کرده است.' : task.review_status === 'rejected' ? 'دستورکار برای اصلاح برگشت داده شده است؛ پس از اصلاح می‌توانید دوباره ارسال کنید.' : 'پس از انجام کار، دستورکار را برای بررسی مدیر ارسال کنید.' }}</p></div>
                    <span class="rounded-full px-3 py-1 text-xs font-medium" :class="task.review_status === 'pending' ? 'bg-amber-100 text-amber-800' : task.review_status === 'approved' ? 'bg-emerald-100 text-emerald-800' : task.review_status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700'">{{ task.review_status === 'pending' ? 'در انتظار بررسی' : task.review_status === 'approved' ? 'تأیید شده' : task.review_status === 'rejected' ? 'نیازمند اصلاح' : 'آماده ارسال' }}</span>
                </div>
                <p v-if="task.review_note" class="mt-3 whitespace-pre-line rounded-lg border border-red-200 bg-white/70 p-3 text-sm text-red-800"><span class="font-semibold">توضیح مدیر برای برگشت:</span> {{ task.review_note }}</p>
                <p v-if="task.reviewed_by?.name && task.reviewed_at" class="mt-2 text-xs text-muted-foreground">بررسی توسط {{ task.reviewed_by.name }} در {{ formatDateTime(task.reviewed_at) }}</p>
            </section>
            <div class="grid gap-6 pt-2 lg:grid-cols-[minmax(0,1.55fr)_minmax(300px,0.75fr)]">
                <main class="min-w-0 space-y-6">
                    <section class="relative rounded-xl border bg-card shadow-sm">
                        <div class="flex items-start justify-between gap-3 rounded-t-xl border-b bg-blue-50/60 px-5 py-4 sm:px-6">
                            <div class="min-w-0">
                                <div class="relative flex items-center gap-2">
                                    <h3 class="font-semibold">فعالیت‌های بعدی</h3>
                                    <button type="button" class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-blue-200 bg-white text-blue-700 transition hover:bg-blue-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500" aria-label="راهنمای فعالیت‌های بعدی" title="راهنمای فعالیت‌های بعدی" @click="showActivityHelp = !showActivityHelp">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.6 9a2.5 2.5 0 0 1 4.8 1c0 1.7-2.4 2-2.4 3.5"/><path d="M12 17h.01"/></svg>
                                    </button>
                                    <div v-if="showActivityHelp" class="absolute right-0 top-8 z-[100] w-[30rem] max-w-[calc(100vw-3rem)] rounded-xl border border-blue-200 bg-white p-4 text-sm leading-6 text-gray-700 shadow-xl">
                                        <div class="mb-1 flex items-center justify-between gap-3"><span class="font-semibold text-gray-900">راهنمای فعالیت‌ها</span><button type="button" class="text-gray-500 hover:text-gray-900" aria-label="بستن راهنما" @click="showActivityHelp = false">×</button></div>
                                        «فعالیت‌های بعدی» برای کارهایی است که هنوز باید انجام شوند. بعد از انجام، «انجام شد» را بزنید تا همان فعالیت با زمان تکمیل در گزارش فعالیت نمایش داده شود. برای کاری که قبلاً انجام شده و از قبل در فهرست فعالیت‌های بعدی نبوده، «ثبت فعالیت انجام‌شده» را انتخاب کنید.
                                    </div>
                                </div>
                                
                            </div>
                            <button v-if="abilities.update && canEditTaskContent" type="button" title="برای کاری که هنوز باید انجام شود؛ پس از انجام، دکمه «انجام شد» را بزنید." class="shrink-0 rounded-lg bg-gray-900 px-3 py-2 text-sm font-medium text-white" @click="openActivityForm()">+ ثبت فعالیت بعدی</button>
                        </div>
                        <div v-if="pendingActivities.length === 0" class="p-6 text-center text-sm text-muted-foreground">هنوز فعالیتی ثبت نشده است.</div>
                        <div v-if="pendingActivities.length" class="divide-y">
                            <article v-for="activity in pendingActivities" :key="activity.id" class="flex flex-wrap items-start justify-between gap-4 p-5">
                                <div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">در انتظار انجام</span><h4 class="font-medium text-gray-900">{{ activity.title }}</h4></div><p v-if="activity.description" class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-600">{{ activity.description }}</p></div>
                                <div v-if="abilities.update && canEditTaskContent" class="flex shrink-0 flex-wrap gap-2"><button type="button" title="فعالیت را تکمیل می‌کند و همان رکورد را در گزارش فعالیت انجام‌شده نمایش می‌دهد." class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700" @click="completeActivity(activity.id)">انجام شد</button><button type="button" class="rounded-lg border px-3 py-2 text-sm" @click="openActivityForm(activity)">ویرایش</button><button type="button" class="rounded-lg border border-red-200 px-3 py-2 text-sm text-red-600" @click="deleteActivity(activity.id)">حذف</button></div>
                            </article>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
                        <div class="border-b bg-blue-50/60 px-5 py-4 sm:px-6">
                            <div class="flex items-center justify-between gap-3"><div><h3 class="font-semibold">گزارش فعالیت</h3><p class="mt-1 text-sm text-muted-foreground">کارهایی که انجام شده‌اند؛ فعالیت‌های تکمیل‌شده و ثبت‌های قبلی در این فهرست نگهداری می‌شوند.</p></div><div class="flex shrink-0 items-center gap-3"><span class="text-xs text-muted-foreground">{{ loggedActivities.length }} فعالیت</span><button v-if="abilities.log_work && canEditTaskContent" type="button" title="برای ثبت کاری که قبلاً انجام شده است" class="rounded-lg bg-gray-900 px-3 py-2 text-sm font-medium text-white" @click="showWorkLogModal = true">+ ثبت فعالیت انجام‌شده</button></div></div>
                        </div>
                        <div v-if="loggedActivities.length === 0" class="p-8 text-center text-sm text-muted-foreground">هنوز فعالیت انجام‌شده‌ای ثبت نشده است.</div>
                        <div v-if="loggedActivities.length" class="divide-y">
                            <article v-for="(item, index) in loggedActivities" :key="'work-'+item.id" class="p-5 sm:p-6">
                                <div class="flex flex-wrap items-start justify-between gap-4"><div class="flex min-w-0 flex-1 gap-3"><div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-xs font-semibold text-emerald-700">{{ index + 1 }}</div><div class="min-w-0 flex-1"><div class="flex justify-between gap-3"><h4 class="font-medium">{{ item.title }}</h4><time class="text-xs text-muted-foreground">{{ formatDateTime(item.completed_at ?? item.created_at) }}</time></div><p v-if="item.description" class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-600">{{ item.description }}</p><p v-if="item.completed_by?.name" class="mt-2 text-xs text-muted-foreground">ثبت توسط {{ item.completed_by.name }}</p></div></div><div v-if="abilities.update && canEditTaskContent" class="flex shrink-0 gap-2"><button type="button" class="rounded-lg border px-3 py-2 text-sm" @click="editWorkLog(item)">ویرایش</button><button type="button" class="rounded-lg border border-red-200 px-3 py-2 text-sm text-red-600" @click="deleteWorkLog(item.id)">حذف</button></div></div>
                            </article>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
                        <div class="flex items-start justify-between gap-3 border-b bg-blue-50/60 px-5 py-4 sm:px-6">
                            <div><h3 class="font-semibold">سوابق ارجاع دستورکار</h3><p class="mt-1 text-sm text-muted-foreground">مشخص می‌کند دستورکار از چه کسی به چه کسی ارجاع شده و چه کسی ارجاع را ثبت کرده است.</p></div>
                            <button v-if="abilities.refer && !isReviewLocked" type="button" class="shrink-0 rounded-lg bg-gray-900 px-3 py-2 text-sm font-medium text-white" @click="showReferralModal = true">ارجاع دستورکار</button>
                        </div>
                        <div v-if="task.assignments.length === 0" class="p-6 text-center text-sm text-muted-foreground">هنوز سابقه ارجاعی برای این دستورکار ثبت نشده است.</div>
                        <div v-else class="divide-y px-5 py-4 sm:px-6">
                            <article v-for="(assignment, index) in task.assignments" :key="assignment.id" class="py-4 first:pt-0 last:pb-0">
                                <div class="flex gap-3">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-violet-50 text-xs font-semibold text-violet-700">{{ index + 1 }}</div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="font-medium text-gray-900">ارجاع از <span>{{ assignment.from_user?.name ?? 'سیستم' }}</span> به <span>{{ assignment.to_user?.name ?? '—' }}</span></p>
                                            <time class="text-xs text-muted-foreground">{{ formatDateTime(assignment.started_at) }}</time>
                                        </div>
                                        <p class="mt-1 text-xs text-muted-foreground">ثبت‌کننده ارجاع: {{ assignment.assigned_by?.name ?? '—' }}</p>
                                        <p v-if="assignment.note" class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-600">{{ assignment.note }}</p>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </section>

                    <div class="grid gap-6 md:grid-cols-2">
                        <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
                            <div class="flex items-start justify-between gap-3 border-b bg-blue-50/60 px-5 py-4">
                                <div>
                                    <h3 class="font-semibold">نظرات</h3>
                                    <p class="mt-1 text-xs text-muted-foreground">{{ task.comments?.length ?? 0 }} نظر ثبت شده</p>
                                </div>
                                <button v-if="abilities.comment && canEditTaskContent" type="button" class="shrink-0 rounded-lg bg-gray-900 px-3 py-2 text-xs font-medium text-white hover:bg-gray-800" @click="showCommentModal = true">ثبت نظر</button>
                            </div>
                            <div v-if="task.comments?.length" class="space-y-3 p-4">
                                <article v-for="comment in task.comments" :key="comment.id" class="rounded-lg bg-gray-50 p-3">
                                    <p class="text-sm leading-6">{{ comment.body }}</p>
                                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t pt-2">
                                        <p class="text-xs text-muted-foreground">{{ comment.user?.name ?? '—' }}</p>
                                        <div v-if="abilities.update && canEditTaskContent" class="flex gap-2">
                                            <button type="button" class="text-xs font-medium text-gray-700 hover:underline" @click="editComment(comment)">ویرایش</button>
                                            <button type="button" class="text-xs font-medium text-red-600 hover:underline" @click="deleteComment(comment.id)">حذف</button>
                                        </div>
                                    </div>
                                </article>
                            </div>
                            <p v-else class="p-4 text-sm text-muted-foreground">نظری ثبت نشده است.</p>
                        </section>
                        <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
                            <div class="flex items-start justify-between gap-3 border-b bg-blue-50/60 px-5 py-4">
                                <div>
                                    <h3 class="font-semibold">یادآوری‌ها</h3>
                                    <p class="mt-1 text-xs text-muted-foreground">{{ task.reminders?.length ?? 0 }} یادآوری ثبت شده</p>
                                </div>
                                <button v-if="abilities.create_reminder && canEditTaskContent" type="button" class="shrink-0 rounded-lg bg-gray-900 px-3 py-2 text-xs font-medium text-white hover:bg-gray-800" @click="showReminderModal = true">ثبت یادآوری</button>
                            </div>
                            <div v-if="task.reminders.length" class="space-y-3 p-4">
                                <article v-for="reminder in task.reminders" :key="reminder.id" class="rounded-lg border p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold">{{ reminder.title }}</p>
                                            <p class="mt-1 text-xs text-muted-foreground">{{ formatDateTime(reminder.remind_at) }}</p>
                                        </div>
                                        <div v-if="abilities.update && canEditTaskContent" class="flex shrink-0 gap-3">
                                            <button type="button" class="text-xs font-medium text-gray-700 hover:underline" @click="editReminder(reminder)">ویرایش</button>
                                            <button type="button" class="text-xs font-medium text-red-600 hover:underline" @click="deleteReminder(reminder.id)">حذف</button>
                                        </div>
                                    </div>
                                    <p v-if="reminder.user?.name" class="mt-2 text-xs text-muted-foreground">برای: {{ reminder.user.name }}</p>
                                    <p v-if="reminder.description" class="mt-2 text-xs text-muted-foreground">{{ reminder.description }}</p>
                                </article>
                            </div>
                            <p v-else class="p-4 text-sm text-muted-foreground">یادآوری ثبت نشده است.</p>
                        </section>
                    </div>
                </main>

                <aside class="space-y-6">
                    <section class="rounded-xl border bg-card p-5 shadow-sm">
                        <h3 class="font-semibold">اطلاعات پروژه</h3>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">پروژه</dt><dd class="font-medium">{{ task.project?.name ?? 'بدون پروژه' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">مسئول واحد</dt><dd class="font-medium">{{ task.department?.manager?.name ?? 'بدون مسئول واحد' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">مسئول دستورکار</dt><dd class="font-medium">{{ task.assignee?.name ?? 'بدون مسئول' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">شروع</dt><dd class="font-medium">{{ formatPersianDate(task.start_date) }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">مهلت</dt><dd class="font-medium">{{ formatPersianDate(task.due_date) }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">ایجادکننده</dt><dd class="font-medium">{{ task.creator?.name ?? '—' }}</dd></div>
                        </dl>
                        <div class="mt-5 border-t pt-4">
                            <p class="text-xs text-muted-foreground">توضیحات</p>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700">{{ task.description || 'توضیحی برای این دستورکار ثبت نشده است.' }}</p>
                        </div>
                    </section>

                    <section class="rounded-xl border bg-card shadow-sm">
                        <div class="border-b px-5 py-4"><h3 class="font-semibold">پیوست‌ها</h3><p class="mt-1 text-xs text-muted-foreground">{{ task.documents.length }} فایل</p></div>
                        <div v-if="task.documents.length" class="divide-y">
                            <div v-for="link in task.documents" :key="link.id" class="p-4"><p class="truncate text-sm font-medium">{{ link.document?.title ?? 'سند' }}</p><p v-if="link.document?.latest_version" class="mt-1 text-xs text-muted-foreground">{{ link.document.latest_version.original_name }} · {{ formatFileSize(link.document.latest_version.size) }}</p><div class="mt-2 flex flex-wrap items-center gap-3"><a v-if="link.document" :href="route('documents.download', link.document.id)" class="inline-block text-xs font-medium text-gray-700 hover:underline">دریافت فایل</a><button v-if="canEditTaskContent && abilities.documents_create && link.document" type="button" class="text-xs font-medium text-red-600 hover:underline" @click="deleteTaskDocument(link.document.id)">حذف پیوست</button></div></div>
                        </div>
                        <div v-else class="p-5 text-center text-sm text-muted-foreground">پیوستی وجود ندارد.</div>
                        <form v-if="abilities.documents_create && canEditTaskContent" class="border-t p-4 space-y-3" @submit.prevent="submitDocument">
                            <input v-model="documentForm.title" required maxlength="255" class="h-10 w-full rounded-lg border bg-background px-3 text-sm" placeholder="عنوان فایل" />
                            <input ref="documentFileInput" type="file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.txt,.csv,.zip" class="block w-full rounded-lg border bg-background px-3 py-2 text-sm" @change="handleDocumentFileChange" />
                            <button type="submit" :disabled="documentForm.processing" class="w-full rounded-lg bg-gray-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50">{{ documentForm.processing ? 'در حال بارگذاری...' : 'پیوست فایل' }}</button>
                        </form>
                    </section>

                    <section class="rounded-xl border bg-card p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold">ارتباط و پیگیری بیرونی</h3>
                                <p class="mt-1 text-xs text-muted-foreground">سازمان، واحد یا شخص خارج از شرکت مرتبط با این دستورکار.</p>
                            </div>
                            <button v-if="abilities.update && canEditTaskContent" type="button" class="shrink-0 rounded-lg bg-gray-900 px-3 py-2 text-xs font-medium text-white hover:bg-gray-800" @click="openExternalModal">
                                افزودن ارتباط
                            </button>
                        </div>
                        <div v-if="task.external_contacts?.length" class="mt-4">
                            <div class="grid gap-3">
                                <article v-for="contact in task.external_contacts" :key="contact.id" class="rounded-xl border bg-gray-50/70 p-4">
                                    <div class="min-w-0">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <div class="font-medium text-gray-900">{{ contact.name }}</div>
                                                <p v-if="contact.position" class="mt-1 text-xs text-gray-600">{{ contact.position }}</p>
                                            </div>
                                        </div>
                                        <div class="mt-3 grid gap-1.5 text-xs text-muted-foreground">
                                            <p><span class="font-medium text-gray-700">سازمان:</span> {{ props.externalPartyOptions.external_organizations.find(item => item.id === contact.pivot?.external_organization_id)?.name ?? contact.organization?.name ?? '—' }}</p>
                                            <p><span class="font-medium text-gray-700">واحد:</span> {{ props.externalPartyOptions.external_departments.find(item => item.id === contact.pivot?.external_department_id)?.name ?? contact.department?.name ?? '—' }}</p>
                                            <p v-if="contact.mobile"><span class="font-medium text-gray-700">موبایل:</span> {{ contact.mobile }}</p>
                                            <p v-if="contact.phone"><span class="font-medium text-gray-700">تلفن:</span> {{ contact.phone }}<span v-if="contact.extension"> · داخلی {{ contact.extension }}</span></p>
                                            <p v-if="contact.email"><span class="font-medium text-gray-700">ایمیل:</span> {{ contact.email }}</p>
                                        </div>
                                    </div>
                                    <div v-if="abilities.update && canEditTaskContent" class="mt-4 flex gap-2 border-t pt-3">
                                        <button type="button" class="rounded-md border px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-white" @click="editExternalContact(contact.id)">ویرایش ارتباط</button>
                                        <button type="button" class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50" @click="removeExternalContact(contact.id)">حذف ارتباط</button>
                                    </div>
                                </article>
                            </div>
                        </div>
                        <div v-else class="mt-4">
                            <p class="text-sm text-muted-foreground">پیگیری خارجی برای این دستورکار ثبت نشده است.</p>
                        </div>
                        <div v-if="externalForm.errors.external_organization_id || externalForm.errors.external_department_id || externalForm.errors.external_contact_ids" class="mt-4 space-y-1 text-xs text-red-600">
                            <p v-if="externalForm.errors.external_organization_id">{{ externalForm.errors.external_organization_id }}</p>
                            <p v-if="externalForm.errors.external_department_id">{{ externalForm.errors.external_department_id }}</p>
                            <p v-if="externalForm.errors.external_contact_ids">{{ externalForm.errors.external_contact_ids }}</p>
                        </div>
</section>

                    <section v-if="abilities.change_status && task.status !== 'submitted' && task.review_status !== 'pending'" class="rounded-xl border bg-card p-5 shadow-sm">
                        <h3 class="font-semibold">وضعیت</h3>
                        <form class="mt-3 flex gap-2" @submit.prevent="changeStatus">
                            <select v-model="statusForm.status" class="h-10 min-w-0 flex-1 rounded-lg border bg-background px-3 text-sm"><option value="todo">انجام نشده</option><option value="in_progress">در حال انجام</option><option value="cancelled">لغو شده</option></select>
                            <button type="submit" :disabled="statusForm.processing" class="rounded-lg border bg-white px-3 text-sm font-medium">ذخیره</button>
                        </form>
                    </section>
                </aside>
            </div>

        </div>

        <div v-if="showReturnReviewModal" class="task-modal-overlay fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showReturnReviewModal = false">
            <div class="task-modal-panel w-full max-w-lg rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b p-5"><div><h3 class="font-semibold">برگشت دستورکار برای اصلاح</h3><p class="mt-1 text-sm text-muted-foreground">دلیل برگشت برای مسئول دستورکار نمایش داده می‌شود.</p></div><button type="button" class="text-xl" aria-label="بستن" @click="showReturnReviewModal = false">×</button></div>
                <form class="space-y-4 p-5" @submit.prevent="returnFromReview">
                    <textarea v-model="reviewForm.note" rows="4" required maxlength="5000" class="w-full rounded-lg border p-3 text-sm" placeholder="دلیل برگشت و اصلاحات موردنیاز را بنویسید"></textarea>
                    <p v-if="reviewForm.errors.note" class="text-sm text-red-600">{{ reviewForm.errors.note }}</p>
                    <div class="flex justify-end gap-2"><button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="showReturnReviewModal = false">انصراف</button><button type="submit" :disabled="reviewForm.processing || !reviewForm.note.trim()" class="rounded-lg bg-amber-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">{{ reviewForm.processing ? 'در حال برگشت...' : 'برگشت برای اصلاح' }}</button></div>
                </form>
            </div>
        </div>

        <div v-if="showReferralModal" class="task-modal-overlay fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showReferralModal = false">
            <div class="task-modal-panel w-full max-w-lg rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b p-5"><div><h3 class="font-semibold">ارجاع دستورکار</h3><p class="mt-1 text-sm text-muted-foreground">مسئول فعلی دستورکار را به شخص دیگری واگذار کنید.</p></div><button type="button" class="text-xl" @click="showReferralModal = false">×</button></div>
                <form class="space-y-4 p-5" @submit.prevent="submitReferral">
                    <select v-model="referralForm.assignee_id" required class="h-10 w-full rounded-lg border bg-background px-3 text-sm"><option value="" disabled>انتخاب مسئول جدید</option><option v-for="user in assigneeOptions" :key="user.id" :value="String(user.id)">{{ user.name }}</option></select>
                    <input v-model="referralForm.note" class="h-10 w-full rounded-lg border bg-background px-3 text-sm" placeholder="توضیح ارجاع (اختیاری)" />
                    <p v-if="referralForm.errors.assignee_id" class="text-sm text-red-600">{{ referralForm.errors.assignee_id }}</p><p v-if="referralForm.errors.note" class="text-sm text-red-600">{{ referralForm.errors.note }}</p>
                    <div class="flex justify-end gap-2"><button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="showReferralModal = false">انصراف</button><button type="submit" :disabled="referralForm.processing" class="rounded-lg bg-gray-900 px-4 py-2 text-sm text-white">{{ referralForm.processing ? 'در حال ارجاع...' : 'ارجاع دستورکار' }}</button></div>
                </form>
            </div>
        </div>

        <div v-if="showCommentModal || showWorkLogModal || showReminderModal" class="task-modal-overlay fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showCommentModal = showWorkLogModal = showReminderModal = false">
            <div class="task-modal-panel w-full max-w-xl rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b p-5"><h3 class="font-semibold">{{ showCommentModal ? 'ثبت نظر' : showWorkLogModal ? (editingWorkLogId ? 'ویرایش فعالیت انجام‌شده' : 'ثبت فعالیت انجام‌شده') : 'ایجاد یادآوری' }}</h3><button type="button" class="text-xl" @click="showCommentModal = showWorkLogModal = showReminderModal = false">×</button></div>
                <form v-if="showCommentModal" class="space-y-4 p-5" @submit.prevent="submitComment"><textarea v-model="commentForm.body" rows="5" required class="w-full rounded-lg border p-3 text-sm" placeholder="نظر خود را وارد کنید..."></textarea><button type="submit" :disabled="commentForm.processing" class="w-full rounded-lg bg-gray-900 px-4 py-2 text-sm text-white">ثبت نظر</button></form>
                <form v-else-if="showWorkLogModal" class="space-y-4 p-5" @submit.prevent="submitWorkLog"><input v-model="workLogForm.title" required class="h-10 w-full rounded-lg border px-3 text-sm" placeholder="عنوان فعالیت" /><textarea v-model="workLogForm.description" rows="4" class="w-full rounded-lg border p-3 text-sm" placeholder="شرح فعالیت"></textarea><div class="grid gap-3 sm:grid-cols-2"><JalaliDateTimePicker v-model="workLogForm.started_at" placeholder="شروع" /><JalaliDateTimePicker v-model="workLogForm.completed_at" placeholder="پایان" /></div><button type="submit" :disabled="workLogForm.processing" class="w-full rounded-lg bg-gray-900 px-4 py-2 text-sm text-white">{{ editingWorkLogId ? 'ذخیره تغییرات' : 'ثبت فعالیت انجام‌شده' }}</button></form>
                <form v-else class="space-y-4 p-5" @submit.prevent="submitReminder"><input v-model="reminderForm.title" required class="h-10 w-full rounded-lg border px-3 text-sm" placeholder="عنوان یادآوری" /><textarea v-model="reminderForm.description" rows="3" class="w-full rounded-lg border p-3 text-sm" placeholder="توضیحات"></textarea><JalaliDateTimePicker v-model="reminderForm.remind_at" placeholder="زمان یادآوری" /><button type="submit" :disabled="reminderForm.processing" class="w-full rounded-lg bg-gray-900 px-4 py-2 text-sm text-white">ایجاد یادآوری</button></form>
            </div>
        </div>

        <div v-if="showEditCommentModal" class="task-modal-overlay fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showEditCommentModal = false">
            <div class="task-modal-panel w-full max-w-xl rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b p-5"><h3 class="font-semibold">ویرایش نظر</h3><button type="button" class="text-xl" @click="showEditCommentModal = false">×</button></div>
                <form class="space-y-4 p-5" @submit.prevent="submitCommentEdit">
                    <textarea v-model="editCommentForm.body" rows="5" required class="w-full rounded-lg border p-3 text-sm" placeholder="متن نظر"></textarea>
                    <p v-if="editCommentForm.errors.body" class="text-sm text-red-600">{{ editCommentForm.errors.body }}</p>
                    <div class="flex justify-end gap-2"><button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="showEditCommentModal = false">انصراف</button><button type="submit" :disabled="editCommentForm.processing" class="rounded-lg bg-gray-900 px-4 py-2 text-sm text-white">ذخیره تغییرات</button></div>
                </form>
            </div>
        </div>

        <div v-if="showEditReminderModal" class="task-modal-overlay fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showEditReminderModal = false">
            <div class="task-modal-panel w-full max-w-xl rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b p-5"><div><h3 class="font-semibold">ویرایش یادآوری</h3><p class="mt-1 text-sm text-muted-foreground">عنوان، توضیحات و زمان یادآوری را تغییر دهید.</p></div><button type="button" class="text-xl" @click="showEditReminderModal = false">×</button></div>
                <form class="space-y-4 p-5" @submit.prevent="submitReminderEdit">
                    <input v-model="editReminderForm.title" required class="h-10 w-full rounded-lg border px-3 text-sm" placeholder="عنوان یادآوری" />
                    <textarea v-model="editReminderForm.description" rows="3" class="w-full rounded-lg border p-3 text-sm" placeholder="توضیحات"></textarea>
                    <JalaliDateTimePicker v-model="editReminderForm.remind_at" placeholder="زمان یادآوری" />
                    <p v-if="editReminderForm.errors.remind_at" class="text-sm text-red-600">{{ editReminderForm.errors.remind_at }}</p>
                    <div class="flex justify-end gap-2"><button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="showEditReminderModal = false">انصراف</button><button type="submit" :disabled="editReminderForm.processing" class="rounded-lg bg-gray-900 px-4 py-2 text-sm text-white">ذخیره تغییرات</button></div>
                </form>
            </div>
        </div>

        <div v-if="showEditModal" class="task-modal-overlay fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showEditModal = false">
            <div class="task-modal-panel max-h-[92vh] w-full max-w-4xl overflow-auto rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b p-5"><div><h3 class="font-semibold">ویرایش دستورکار</h3><p class="mt-1 text-sm text-muted-foreground">اطلاعات اصلی دستورکار را ویرایش کنید.</p></div><button type="button" class="text-xl" @click="showEditModal = false">×</button></div>
                <form class="grid gap-5 p-5 sm:grid-cols-2" @submit.prevent="submitEdit">
                    <div class="sm:col-span-2"><label class="mb-2 block text-sm font-medium">عنوان دستورکار</label><input v-model="editForm.title" required maxlength="255" class="h-10 w-full rounded-lg border px-3 text-sm" /></div>
                    <div><label class="mb-2 block text-sm font-medium">اولویت</label><select v-model="editForm.priority" class="h-10 w-full rounded-lg border px-3 text-sm"><option value="low">کم</option><option value="normal">عادی</option><option value="high">زیاد</option><option value="urgent">فوری</option></select></div>
                    <div><label class="mb-2 block text-sm font-medium">تاریخ شروع</label><JalaliDatePicker v-model="editForm.start_date" /></div>
                    <div><label class="mb-2 block text-sm font-medium">مهلت انجام</label><JalaliDatePicker v-model="editForm.due_date" /></div>
                    <div class="flex items-center gap-2 pt-7"><input id="task-active" v-model="editForm.is_active" type="checkbox" /><label for="task-active" class="text-sm font-medium">دستورکار فعال باشد</label></div>
                    <div class="sm:col-span-2"><label class="mb-2 block text-sm font-medium">توضیحات</label><textarea v-model="editForm.description" rows="5" class="w-full rounded-lg border p-3 text-sm"></textarea></div>
                    <CustomFieldsForm v-if="props.customFields.length" v-model="editForm.custom_fields" :fields="props.customFields" :errors="editForm.errors as Record<string, string>" title="اطلاعات تکمیلی دستورکار" description="اطلاعات اختصاصی این دستورکار را مدیریت کنید." />
                    <div class="sm:col-span-2 flex justify-between gap-2"><button v-if="abilities.delete" type="button" class="rounded-lg border border-red-200 px-4 py-2 text-sm text-red-600" @click="deleteTask">حذف دستورکار</button><div class="flex gap-2"><button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="showEditModal = false">انصراف</button><button type="submit" :disabled="editForm.processing" class="rounded-lg bg-gray-900 px-4 py-2 text-sm text-white">{{ editForm.processing ? 'در حال ذخیره...' : 'ذخیره تغییرات' }}</button></div></div>
                </form>
            </div>
        </div>
    
            <div v-if="showExternalModal" class="task-modal-overlay fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showExternalModal = false">
                <section class="task-modal-panel w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl sm:p-6">
                    <div class="flex items-center justify-between"><div><h3 class="font-semibold text-gray-900">{{ editingExternalContactId ? 'ویرایش ارتباط و پیگیری بیرونی' : 'افزودن ارتباط و پیگیری بیرونی' }}</h3><p class="mt-1 text-sm text-muted-foreground">{{ editingExternalContactId ? 'اطلاعات این ارتباط را ویرایش کنید.' : 'یک یا چند شخص را به‌عنوان ارتباط و پیگیری بیرونی اضافه کنید.' }}</p></div><button type="button" class="text-gray-500 hover:text-gray-900" @click="showExternalModal = false; editingExternalContactId = null">×</button></div>
                    <form class="mt-5 space-y-4" @submit.prevent="submitExternal">
                        <select v-model="externalForm.external_organization_id" @change="handleExternalOrganizationChange" class="h-10 w-full rounded-lg border px-3 text-sm"><option :value="null">بدون سازمان خارجی</option><option v-for="item in externalPartyOptions.external_organizations" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                        <select v-model="externalForm.external_department_id" :disabled="externalForm.external_organization_id === null" @change="handleExternalDepartmentChange" class="h-10 w-full rounded-lg border px-3 text-sm"><option :value="null">بدون واحد خارجی</option><option v-for="item in externalDepartments" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <label class="text-sm font-medium text-gray-700">اشخاص خارجی</label>
                                <span class="text-xs text-muted-foreground">{{ externalForm.external_contact_ids.length }} نفر انتخاب شده</span>
                            </div>
                            <div v-if="externalForm.external_organization_id === null" class="rounded-lg border border-dashed p-4 text-center text-xs text-muted-foreground">ابتدا سازمان خارجی را انتخاب کنید.</div>
                            <div v-else-if="externalContacts.length === 0" class="rounded-lg border border-dashed p-4 text-center text-xs text-muted-foreground">برای این سازمان/واحد شخصی ثبت نشده است.</div>
                            <div v-else class="max-h-72 space-y-2 overflow-y-auto rounded-lg border p-2">
                                <label v-for="item in externalContacts" :key="item.id" class="flex cursor-pointer items-start gap-3 rounded-lg p-3 transition hover:bg-gray-50" :class="externalForm.external_contact_ids.includes(item.id) ? 'bg-gray-100 ring-1 ring-gray-300' : ''">
                                    <input :checked="externalForm.external_contact_ids.includes(item.id)" type="checkbox" class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300" @change="toggleExternalContact(item.id)" />
                                    <span class="min-w-0 flex-1">
                                        <span class="flex items-center justify-between gap-2">
                                            <span class="block text-sm font-medium text-gray-900">{{ item.name }}</span>
                                            <span v-if="externalForm.external_contact_ids.includes(item.id)" class="text-xs font-medium text-gray-700">انتخاب شده</span>
                                        </span>
                                        <span v-if="item.position" class="mt-0.5 block text-xs text-muted-foreground">{{ item.position }}</span>
                                        <span class="mt-2 grid gap-1 text-xs text-muted-foreground">
                                            <span>سازمان: {{ externalPartyOptions.external_organizations.find(org => org.id === item.external_organization_id)?.name ?? '—' }}</span>
                                            <span>واحد: {{ externalPartyOptions.external_departments.find(dept => dept.id === item.external_department_id)?.name ?? '—' }}</span>
                                            <span v-if="item.mobile">موبایل: {{ item.mobile }}</span>
                                            <span v-if="item.phone">تلفن: {{ item.phone }}<span v-if="item.extension"> · داخلی {{ item.extension }}</span></span>
                                            <span v-if="item.email">ایمیل: {{ item.email }}</span>
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 border-t pt-4"><button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="showExternalModal = false; editingExternalContactId = null">انصراف</button><button type="submit" :disabled="externalForm.processing" class="rounded-lg bg-gray-900 px-4 py-2 text-sm text-white">{{ editingExternalContactId ? 'ذخیره ویرایش' : 'افزودن ارتباط' }}</button></div>
                    </form>
                </section>
            </div>

            <div v-if="showActivityModal" class="task-modal-overlay fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showActivityModal = false">
                <section class="task-modal-panel w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl sm:p-6">
                    <div class="flex items-center justify-between"><h3 class="font-semibold text-gray-900">{{ editingActivityId ? 'ویرایش فعالیت بعدی' : 'ثبت فعالیت بعدی' }}</h3><button type="button" class="text-gray-500 hover:text-gray-900" @click="showActivityModal = false">×</button></div>
                    <form class="mt-5 space-y-4" @submit.prevent="submitActivity">
                        <div><label class="mb-1.5 block text-sm font-medium text-gray-700">عنوان فعالیت</label><input v-model="activityForm.title" required class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="مثلاً دریافت تأیید مالی" /></div>
                        <div><label class="mb-1.5 block text-sm font-medium text-gray-700">توضیحات</label><textarea v-model="activityForm.description" rows="3" class="w-full rounded-lg border border-gray-300 p-3 text-sm"></textarea></div>
                        <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-600">مسئول این فعالیت همان مسئول اصلی دستورکار است: <span class="font-medium text-gray-900">{{ task.assignee?.name ?? 'بدون مسئول' }}</span></div>
                        <div class="flex items-center justify-end gap-2 border-t pt-4"><button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="showActivityModal = false">انصراف</button><button type="submit" :disabled="activityForm.processing" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">{{ editingActivityId ? 'ذخیره تغییرات' : 'ثبت فعالیت بعدی' }}</button></div>
                    </form>
                </section>
            </div>

    </AuthenticatedLayout>
</template>