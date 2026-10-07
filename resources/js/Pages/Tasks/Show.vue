<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import JalaliDatePicker from '@/Components/JalaliDatePicker.vue';
import CustomFieldsForm from '@/Components/CustomFieldsForm.vue';
import JalaliDateTimePicker from '@/Components/JalaliDateTimePicker.vue';
import { formatPersianDate, formatPersianDateTime } from '@/Utils/date';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface User { id: number; name: string; email?: string | null; mobile?: string | null; phone?: string | null; extension?: string | null; }
interface ExternalOrganizationOption { id: number; name: string; }
interface ExternalDepartmentOption { id: number; external_organization_id: number; name: string; }
interface ExternalContactOption { id: number; external_organization_id: number; external_department_id: number | null; name: string; position: string | null; mobile: string | null; phone: string | null; extension: string | null; }
interface ExternalPartyOptions { external_organizations: ExternalOrganizationOption[]; external_departments: ExternalDepartmentOption[]; external_contacts: ExternalContactOption[]; }

interface Reminder {
    id: number;
    title: string;
    description: string | null;
    remind_at: string;
    status: string;
    user: User | null;
}
interface Task {
    id: number;
    title: string;
    description: string | null;
    status: string;
    priority: string;
    is_active: boolean;
    start_date: string | null;
    due_date: string | null;
    assignee: User | null;
    creator: User | null;
    department: { id: number; name: string } | null;
    externalOrganization: ExternalOrganizationOption | null;
    externalDepartment: ExternalDepartmentOption | null;
    externalContact: ExternalContactOption | null;
    project: { id: number; name: string } | null;
    reminders: Reminder[];
    documents: TaskDocument[];
    participants: Array<{ id: number; user: User }>;
    comments: Array<{ id: number; body: string; user: User | null }>;
}
interface TimelineItem {
    type: string;
    title: string;
    description: string | null;
    occurred_at: string | null;
    user: User | null;
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
    participantOptions: User[];
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
const workLogForm = useForm({ title: '', description: '', started_at: '', completed_at: '' });
const reminderForm = useForm({
    user_id: props.currentUserId,
    title: '',
    description: '',
    remind_at: '',
});
const referralForm = useForm({ assignee_id: '', note: '' });
const statusForm = useForm({ status: props.task.status });
const reminderStatusForm = useForm({ status: '' as 'completed' | 'cancelled' | '' });
const participantForm = useForm<{ user_ids: number[] }>({ user_ids: props.task.participants?.map(item => item.user.id) ?? [] });
const showEditModal = ref(false);
const showReferralModal = ref(false);
const showCommentModal = ref(false);
const showWorkLogModal = ref(false);
const showReminderModal = ref(false);
const showParticipantModal = ref(false);
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
    external_organization_id: props.task.externalOrganization?.id ?? null,
    external_department_id: props.task.externalDepartment?.id ?? null,
    external_contact_id: props.task.externalContact?.id ?? null,
    custom_fields: props.customFieldValues ?? {},
});

const externalDepartments = computed(() => props.externalPartyOptions.external_departments.filter(item => item.external_organization_id === editForm.external_organization_id));
const externalContacts = computed(() => props.externalPartyOptions.external_contacts.filter(item => item.external_organization_id === editForm.external_organization_id && (editForm.external_department_id === null || item.external_department_id === editForm.external_department_id)));
const handleExternalOrganizationChange = (): void => { editForm.external_department_id = null; editForm.external_contact_id = null; };
const handleExternalDepartmentChange = (): void => { editForm.external_contact_id = null; };

const statusLabels: Record<string, string> = {
    todo: 'انجام نشده',
    in_progress: 'در حال انجام',
    completed: 'تکمیل شده',
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
const submitWorkLog = (): void => {
    workLogForm.post(route('tasks.work-logs.store', props.task.id), {
        preserveScroll: true,
        onSuccess: () => { workLogForm.reset(); showWorkLogModal.value = false; },
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

const deleteTask = (): void => {
    if (!window.confirm(`آیا از حذف وظیفه «${props.task.title}» مطمئن هستید؟`)) {
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
const submitParticipants = (): void => {
    participantForm.put(route('tasks.participants.update', props.task.id), { preserveScroll: true, onSuccess: () => { showParticipantModal.value = false; } });
};
const participantUsers = computed(() => props.task.participants?.map(item => item.user) ?? []);
const phoneLabel = (user: User): string | null => user.mobile || user.phone || null;
</script>

<template>
    <Head :title="task.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="min-w-0">
                    <Link :href="route('tasks.index')" class="text-sm text-muted-foreground hover:text-gray-900">← بازگشت به وظایف</Link>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">وظیفه #{{ task.id }}</span>
                        <span class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusClasses[task.status] ?? 'bg-gray-100 text-gray-700 ring-gray-200'">{{ statusLabels[task.status] ?? task.status }}</span>
                        <span class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="priorityClasses[task.priority] ?? 'bg-gray-100 text-gray-700 ring-gray-200'">اولویت {{ priorityLabels[task.priority] ?? task.priority }}</span>
                    </div>
                    <h2 class="mt-2 text-xl font-semibold text-gray-900">{{ task.title }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">مدیریت، پیگیری و ثبت سوابق وظیفه</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button v-if="abilities.update" type="button" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-gray-800" @click="showEditModal = true">ویرایش</button>
                    <button v-if="abilities.refer" type="button" class="rounded-lg border bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50" @click="showReferralModal = true">ارجاع وظیفه</button>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-6">
            <section class="rounded-xl border bg-card p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">اقدام بعدی</p>
                        <p class="mt-1 font-medium text-gray-900">{{ task.description ? 'ادامه پیگیری و ثبت آخرین فعالیت وظیفه' : 'برای این وظیفه اقدام بعدی را مشخص کنید.' }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button v-if="abilities.log_work" type="button" class="rounded-lg border bg-white px-3.5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="showWorkLogModal = true">ثبت فعالیت</button>
                        <button v-if="abilities.comment" type="button" class="rounded-lg border bg-white px-3.5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="showCommentModal = true">ثبت نظر</button>
                        <button v-if="abilities.create_reminder" type="button" class="rounded-lg border bg-white px-3.5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="showReminderModal = true">یادآوری</button>
                    </div>
                </div>
                <div class="mt-5 grid gap-4 border-t pt-5 sm:grid-cols-4">
                    <div><p class="text-xs text-muted-foreground">مسئول</p><p class="mt-1 font-medium">{{ task.assignee?.name ?? 'بدون مسئول' }}</p></div>
                    <div><p class="text-xs text-muted-foreground">واحد</p><p class="mt-1 font-medium">{{ task.department?.name ?? 'بدون واحد' }}</p></div>
                    <div><p class="text-xs text-muted-foreground">شروع</p><p class="mt-1 font-medium">{{ formatPersianDate(task.start_date) }}</p></div>
                    <div><p class="text-xs text-muted-foreground">مهلت</p><p class="mt-1 font-medium">{{ formatPersianDate(task.due_date) }}</p></div>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1.55fr)_minmax(300px,0.75fr)]">
                <main class="min-w-0 space-y-6">
                    <section class="rounded-xl border bg-card p-5 shadow-sm sm:p-6">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div><h3 class="font-semibold">مسئول و افراد درگیر</h3><p class="mt-1 text-sm text-muted-foreground">افرادی که در انجام یا پیگیری این وظیفه نقش دارند.</p></div>
                            <button v-if="abilities.update" type="button" class="rounded-lg border bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50" @click="showParticipantModal = true">+ افزودن / مدیریت</button>
                        </div>
                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-lg border bg-gray-50/60 p-4">
                                <p class="text-xs text-muted-foreground">مسئول وظیفه</p>
                                <p class="mt-1 font-medium">{{ task.assignee?.name ?? 'بدون مسئول' }}</p>
                                <div v-if="task.assignee && phoneLabel(task.assignee)" class="mt-2 text-xs text-muted-foreground">☎ {{ phoneLabel(task.assignee) }}</div>
                                <div v-if="task.assignee?.email" class="mt-1 text-xs text-muted-foreground">✉ {{ task.assignee.email }}</div>
                            </div>
                            <div v-for="user in participantUsers" :key="user.id" class="rounded-lg border p-4">
                                <p class="text-xs text-muted-foreground">فرد درگیر</p>
                                <p class="mt-1 font-medium">{{ user.name }}</p>
                                <div v-if="phoneLabel(user)" class="mt-2 text-xs text-muted-foreground">☎ {{ phoneLabel(user) }}</div>
                                <div v-if="user.extension" class="mt-1 text-xs text-muted-foreground">داخلی {{ user.extension }}</div>
                                <div v-if="user.email" class="mt-1 truncate text-xs text-muted-foreground">✉ {{ user.email }}</div>
                            </div>
                        </div>
                        <div v-if="participantUsers.length === 0" class="mt-4 rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">هنوز فرد دیگری به‌عنوان فرد درگیر ثبت نشده است.</div>
                    </section>

                    <section class="rounded-xl border bg-card p-5 shadow-sm sm:p-6">
                        <div><h3 class="font-semibold">شرح وظیفه</h3><p class="mt-1 text-sm text-muted-foreground">اطلاعات و توضیحات اصلی</p></div>
                        <p class="mt-4 whitespace-pre-line text-sm leading-7 text-gray-700">{{ task.description || 'توضیحی برای این وظیفه ثبت نشده است.' }}</p>
                    </section>

                    <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
                        <div class="border-b bg-gray-50/70 px-5 py-4 sm:px-6">
                            <div class="flex items-center justify-between"><div><h3 class="font-semibold">گزارش فعالیت</h3><p class="mt-1 text-sm text-muted-foreground">اقداماتی که روی وظیفه انجام شده است.</p></div><span class="text-xs text-muted-foreground">{{ timeline.filter(i => i.type === 'work_log').length }} فعالیت</span></div>
                        </div>
                        <div v-if="timeline.filter(i => i.type === 'work_log').length === 0" class="p-8 text-center text-sm text-muted-foreground">هنوز گزارشی ثبت نشده است.</div>
                        <div v-else class="divide-y">
                            <article v-for="(item, index) in timeline.filter(i => i.type === 'work_log')" :key="'work-'+index" class="p-5 sm:p-6">
                                <div class="flex gap-3"><div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-xs font-semibold text-emerald-700">{{ index + 1 }}</div><div class="min-w-0 flex-1"><div class="flex justify-between gap-3"><h4 class="font-medium">{{ item.title }}</h4><time class="text-xs text-muted-foreground">{{ formatDateTime(item.occurred_at) }}</time></div><p v-if="item.description" class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-600">{{ item.description }}</p><p v-if="item.user" class="mt-2 text-xs text-muted-foreground">ثبت توسط {{ item.user.name }}</p></div></div>
                            </article>
                        </div>
                    </section>

                    <section class="rounded-xl border bg-card p-5 shadow-sm sm:p-6">
                        <div class="flex items-center justify-between"><div><h3 class="font-semibold">خط زمانی</h3><p class="mt-1 text-sm text-muted-foreground">تمام تغییرات مهم وظیفه به ترتیب زمانی.</p></div><span class="text-xs text-muted-foreground">{{ timeline.length }} مورد</span></div>
                        <div class="mt-5 space-y-4">
                            <article v-for="(item, index) in timeline" :key="item.type + '-' + index" class="relative flex gap-3">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold ring-1 ring-inset" :class="timelineClasses[item.type] ?? 'bg-gray-100 text-gray-600 ring-gray-200'">{{ index + 1 }}</div>
                                <div class="min-w-0 flex-1 border-b pb-4 last:border-0"><div class="flex flex-wrap justify-between gap-2"><div><span class="text-xs text-muted-foreground">{{ timelineLabels[item.type] ?? item.type }}</span><h4 class="mt-1 text-sm font-medium">{{ item.title }}</h4></div><time class="text-xs text-muted-foreground">{{ formatDateTime(item.occurred_at) }}</time></div><p v-if="item.description" class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-600">{{ item.description }}</p><p v-if="item.user" class="mt-2 text-xs text-muted-foreground">ثبت توسط {{ item.user.name }}</p></div>
                            </article>
                            <p v-if="timeline.length === 0" class="text-center text-sm text-muted-foreground">هنوز سابقه‌ای ثبت نشده است.</p>
                        </div>
                    </section>

                    <div class="grid gap-6 md:grid-cols-2">
                        <section class="rounded-xl border bg-card p-5 shadow-sm">
                            <h3 class="font-semibold">نظرات</h3>
                            <div v-if="task.comments?.length" class="mt-4 space-y-3">
                                <div v-for="comment in task.comments" :key="comment.id" class="rounded-lg bg-gray-50 p-3"><p class="text-sm leading-6">{{ comment.body }}</p><p class="mt-2 text-xs text-muted-foreground">{{ comment.user?.name ?? '—' }}</p></div>
                            </div>
                            <p v-else class="mt-4 text-sm text-muted-foreground">نظری ثبت نشده است.</p>
                        </section>
                        <section class="rounded-xl border bg-card p-5 shadow-sm">
                            <h3 class="font-semibold">یادآوری‌ها</h3>
                            <div v-if="task.reminders.length" class="mt-4 space-y-3">
                                <div v-for="reminder in task.reminders" :key="reminder.id" class="rounded-lg border p-3"><div class="flex justify-between gap-2"><p class="text-sm font-medium">{{ reminder.title }}</p><span class="text-xs text-muted-foreground">{{ formatDateTime(reminder.remind_at) }}</span></div><p v-if="reminder.description" class="mt-1 text-xs text-muted-foreground">{{ reminder.description }}</p></div>
                            </div>
                            <p v-else class="mt-4 text-sm text-muted-foreground">یادآوری ثبت نشده است.</p>
                        </section>
                    </div>
                </main>

                <aside class="space-y-6">
                    <section class="rounded-xl border bg-card p-5 shadow-sm">
                        <h3 class="font-semibold">اطلاعات پروژه</h3>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">پروژه</dt><dd class="font-medium">{{ task.project?.name ?? 'بدون پروژه' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">واحد</dt><dd class="font-medium">{{ task.department?.name ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">ایجادکننده</dt><dd class="font-medium">{{ task.creator?.name ?? '—' }}</dd></div>
                        </dl>
                    </section>

                    <section class="rounded-xl border bg-card shadow-sm">
                        <div class="border-b px-5 py-4"><h3 class="font-semibold">پیوست‌ها</h3><p class="mt-1 text-xs text-muted-foreground">{{ task.documents.length }} فایل</p></div>
                        <div v-if="task.documents.length" class="divide-y">
                            <div v-for="link in task.documents" :key="link.id" class="p-4"><p class="truncate text-sm font-medium">{{ link.document?.title ?? 'سند' }}</p><p v-if="link.document?.latest_version" class="mt-1 text-xs text-muted-foreground">{{ link.document.latest_version.original_name }} · {{ formatFileSize(link.document.latest_version.size) }}</p><a v-if="link.document" :href="route('documents.download', link.document.id)" class="mt-2 inline-block text-xs font-medium text-gray-700 hover:underline">دریافت فایل</a></div>
                        </div>
                        <div v-else class="p-5 text-center text-sm text-muted-foreground">پیوستی وجود ندارد.</div>
                        <form v-if="abilities.documents_create" class="border-t p-4 space-y-3" @submit.prevent="submitDocument">
                            <input v-model="documentForm.title" required maxlength="255" class="h-10 w-full rounded-lg border bg-background px-3 text-sm" placeholder="عنوان فایل" />
                            <input ref="documentFileInput" type="file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.txt,.csv,.zip" class="block w-full rounded-lg border bg-background px-3 py-2 text-sm" @change="handleDocumentFileChange" />
                            <button type="submit" :disabled="documentForm.processing" class="w-full rounded-lg bg-gray-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50">{{ documentForm.processing ? 'در حال بارگذاری...' : 'پیوست فایل' }}</button>
                        </form>
                    </section>

                    <section class="rounded-xl border bg-card p-5 shadow-sm">
                        <h3 class="font-semibold">پیگیری خارجی</h3>
                        <div v-if="task.externalOrganization || task.externalContact || task.externalDepartment" class="mt-4 space-y-3 text-sm">
                            <div v-if="task.externalOrganization"><p class="text-xs text-muted-foreground">سازمان</p><p class="mt-1 font-medium">{{ task.externalOrganization.name }}</p></div>
                            <div v-if="task.externalDepartment"><p class="text-xs text-muted-foreground">واحد</p><p class="mt-1 font-medium">{{ task.externalDepartment.name }}</p></div>
                            <div v-if="task.externalContact"><p class="text-xs text-muted-foreground">شخص</p><p class="mt-1 font-medium">{{ task.externalContact.name }}</p><p v-if="task.externalContact.mobile" class="mt-1 text-xs text-muted-foreground">موبایل: {{ task.externalContact.mobile }}</p></div>
                        </div>
                        <p v-else class="mt-4 text-sm text-muted-foreground">پیگیری خارجی برای این وظیفه ثبت نشده است.</p>
                    </section>

                    <section v-if="abilities.change_status" class="rounded-xl border bg-card p-5 shadow-sm">
                        <h3 class="font-semibold">وضعیت</h3>
                        <form class="mt-3 flex gap-2" @submit.prevent="changeStatus">
                            <select v-model="statusForm.status" class="h-10 min-w-0 flex-1 rounded-lg border bg-background px-3 text-sm"><option value="todo">انجام نشده</option><option value="in_progress">در حال انجام</option><option value="completed">تکمیل شده</option><option value="cancelled">لغو شده</option></select>
                            <button type="submit" :disabled="statusForm.processing" class="rounded-lg border bg-white px-3 text-sm font-medium">ذخیره</button>
                        </form>
                    </section>
                </aside>
            </div>
        </div>

        <div v-if="showParticipantModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showParticipantModal = false">
            <div class="max-h-[90vh] w-full max-w-xl overflow-auto rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b p-5"><div><h3 class="font-semibold">مدیریت افراد درگیر</h3><p class="mt-1 text-sm text-muted-foreground">چند نفر را که در انجام این وظیفه نقش دارند انتخاب کنید.</p></div><button type="button" class="text-xl" @click="showParticipantModal = false">×</button></div>
                <form class="space-y-4 p-5" @submit.prevent="submitParticipants">
                    <div class="max-h-80 space-y-2 overflow-y-auto rounded-lg border p-3">
                        <label v-for="user in participantOptions" :key="user.id" class="flex cursor-pointer items-center gap-3 rounded-lg p-3 hover:bg-gray-50">
                            <input v-model="participantForm.user_ids" type="checkbox" :value="user.id" class="h-4 w-4 rounded" />
                            <span class="flex-1"><span class="block text-sm font-medium">{{ user.name }}</span><span v-if="phoneLabel(user)" class="block text-xs text-muted-foreground">☎ {{ phoneLabel(user) }}</span></span>
                        </label>
                    </div>
                    <div class="flex justify-end gap-2"><button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="showParticipantModal = false">انصراف</button><button type="submit" :disabled="participantForm.processing" class="rounded-lg bg-gray-900 px-4 py-2 text-sm text-white">{{ participantForm.processing ? 'در حال ذخیره...' : 'ذخیره افراد' }}</button></div>
                </form>
            </div>
        </div>

        <div v-if="showReferralModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showReferralModal = false">
            <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b p-5"><div><h3 class="font-semibold">ارجاع وظیفه</h3><p class="mt-1 text-sm text-muted-foreground">مسئول فعلی وظیفه را به شخص دیگری واگذار کنید.</p></div><button type="button" class="text-xl" @click="showReferralModal = false">×</button></div>
                <form class="space-y-4 p-5" @submit.prevent="submitReferral">
                    <select v-model="referralForm.assignee_id" required class="h-10 w-full rounded-lg border bg-background px-3 text-sm"><option value="" disabled>انتخاب مسئول جدید</option><option v-for="user in assigneeOptions" :key="user.id" :value="String(user.id)">{{ user.name }}</option></select>
                    <input v-model="referralForm.note" class="h-10 w-full rounded-lg border bg-background px-3 text-sm" placeholder="توضیح ارجاع (اختیاری)" />
                    <p v-if="referralForm.errors.note" class="text-sm text-red-600">{{ referralForm.errors.note }}</p>
                    <div class="flex justify-end gap-2"><button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="showReferralModal = false">انصراف</button><button type="submit" :disabled="referralForm.processing" class="rounded-lg bg-gray-900 px-4 py-2 text-sm text-white">{{ referralForm.processing ? 'در حال ارجاع...' : 'ارجاع وظیفه' }}</button></div>
                </form>
            </div>
        </div>

        <div v-if="showCommentModal || showWorkLogModal || showReminderModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showCommentModal = showWorkLogModal = showReminderModal = false">
            <div class="w-full max-w-xl rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b p-5"><h3 class="font-semibold">{{ showCommentModal ? 'ثبت نظر' : showWorkLogModal ? 'ثبت گزارش فعالیت' : 'ایجاد یادآوری' }}</h3><button type="button" class="text-xl" @click="showCommentModal = showWorkLogModal = showReminderModal = false">×</button></div>
                <form v-if="showCommentModal" class="space-y-4 p-5" @submit.prevent="submitComment"><textarea v-model="commentForm.body" rows="5" required class="w-full rounded-lg border p-3 text-sm" placeholder="نظر خود را وارد کنید..."></textarea><button type="submit" :disabled="commentForm.processing" class="w-full rounded-lg bg-gray-900 px-4 py-2 text-sm text-white">ثبت نظر</button></form>
                <form v-else-if="showWorkLogModal" class="space-y-4 p-5" @submit.prevent="submitWorkLog"><input v-model="workLogForm.title" required class="h-10 w-full rounded-lg border px-3 text-sm" placeholder="عنوان فعالیت" /><textarea v-model="workLogForm.description" rows="4" class="w-full rounded-lg border p-3 text-sm" placeholder="شرح فعالیت"></textarea><div class="grid gap-3 sm:grid-cols-2"><JalaliDateTimePicker v-model="workLogForm.started_at" placeholder="شروع" /><JalaliDateTimePicker v-model="workLogForm.completed_at" placeholder="پایان" /></div><button type="submit" :disabled="workLogForm.processing" class="w-full rounded-lg bg-gray-900 px-4 py-2 text-sm text-white">ثبت گزارش فعالیت</button></form>
                <form v-else class="space-y-4 p-5" @submit.prevent="submitReminder"><input v-model="reminderForm.title" required class="h-10 w-full rounded-lg border px-3 text-sm" placeholder="عنوان یادآوری" /><textarea v-model="reminderForm.description" rows="3" class="w-full rounded-lg border p-3 text-sm" placeholder="توضیحات"></textarea><JalaliDateTimePicker v-model="reminderForm.remind_at" placeholder="زمان یادآوری" /><button type="submit" :disabled="reminderForm.processing" class="w-full rounded-lg bg-gray-900 px-4 py-2 text-sm text-white">ایجاد یادآوری</button></form>
            </div>
        </div>

        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showEditModal = false">
            <div class="max-h-[92vh] w-full max-w-4xl overflow-auto rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b p-5"><div><h3 class="font-semibold">ویرایش وظیفه</h3><p class="mt-1 text-sm text-muted-foreground">اطلاعات اصلی وظیفه را ویرایش کنید.</p></div><button type="button" class="text-xl" @click="showEditModal = false">×</button></div>
                <form class="grid gap-5 p-5 sm:grid-cols-2" @submit.prevent="submitEdit">
                    <div class="sm:col-span-2"><label class="mb-2 block text-sm font-medium">عنوان وظیفه</label><input v-model="editForm.title" required maxlength="255" class="h-10 w-full rounded-lg border px-3 text-sm" /></div>
                    <div><label class="mb-2 block text-sm font-medium">اولویت</label><select v-model="editForm.priority" class="h-10 w-full rounded-lg border px-3 text-sm"><option value="low">کم</option><option value="normal">عادی</option><option value="high">زیاد</option><option value="urgent">فوری</option></select></div>
                    <div><label class="mb-2 block text-sm font-medium">تاریخ شروع</label><JalaliDatePicker v-model="editForm.start_date" /></div>
                    <div><label class="mb-2 block text-sm font-medium">مهلت انجام</label><JalaliDatePicker v-model="editForm.due_date" /></div>
                    <div class="flex items-center gap-2 pt-7"><input id="task-active" v-model="editForm.is_active" type="checkbox" /><label for="task-active" class="text-sm font-medium">وظیفه فعال باشد</label></div>
                    <div class="sm:col-span-2 rounded-xl border bg-gray-50 p-4"><h4 class="text-sm font-semibold">پیگیری خارجی</h4><p class="mt-1 text-xs text-muted-foreground">سازمان، واحد و شخص خارجی مرتبط را ثبت کنید.</p><div class="mt-3 grid gap-3 md:grid-cols-3"><select v-model="editForm.external_organization_id" @change="handleExternalOrganizationChange" class="h-10 rounded-lg border bg-white px-3 text-sm"><option :value="null">بدون سازمان خارجی</option><option v-for="item in externalPartyOptions.external_organizations" :key="item.id" :value="item.id">{{ item.name }}</option></select><select v-model="editForm.external_department_id" :disabled="editForm.external_organization_id === null" @change="handleExternalDepartmentChange" class="h-10 rounded-lg border bg-white px-3 text-sm"><option :value="null">بدون واحد خارجی</option><option v-for="item in externalDepartments" :key="item.id" :value="item.id">{{ item.name }}</option></select><select v-model="editForm.external_contact_id" :disabled="editForm.external_organization_id === null" class="h-10 rounded-lg border bg-white px-3 text-sm"><option :value="null">بدون شخص مشخص</option><option v-for="item in externalContacts" :key="item.id" :value="item.id">{{ item.name }}</option></select></div></div>
                    <div class="sm:col-span-2"><label class="mb-2 block text-sm font-medium">توضیحات</label><textarea v-model="editForm.description" rows="5" class="w-full rounded-lg border p-3 text-sm"></textarea></div>
                    <CustomFieldsForm v-if="props.customFields.length" v-model="editForm.custom_fields" :fields="props.customFields" :errors="editForm.errors as Record<string, string>" title="اطلاعات تکمیلی وظیفه" description="اطلاعات اختصاصی این وظیفه را مدیریت کنید." />
                    <div class="sm:col-span-2 flex justify-between gap-2"><button v-if="abilities.delete" type="button" class="rounded-lg border border-red-200 px-4 py-2 text-sm text-red-600" @click="deleteTask">حذف وظیفه</button><div class="flex gap-2"><button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="showEditModal = false">انصراف</button><button type="submit" :disabled="editForm.processing" class="rounded-lg bg-gray-900 px-4 py-2 text-sm text-white">{{ editForm.processing ? 'در حال ذخیره...' : 'ذخیره تغییرات' }}</button></div></div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>