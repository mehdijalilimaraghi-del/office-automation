<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import JalaliDatePicker from '@/Components/JalaliDatePicker.vue';
import { formatPersianDate, formatPersianDateTime } from '@/Utils/date';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface User { id: number; name: string; }
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
    update: boolean;
    delete: boolean;
}

const props = defineProps<{
    task: Task;
    timeline: TimelineItem[];
    currentUserId: number;
    abilities: Abilities;
    assigneeOptions: User[];
    externalPartyOptions: ExternalPartyOptions;
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
        onSuccess: () => commentForm.reset(),
    });
};
const submitWorkLog = (): void => {
    workLogForm.post(route('tasks.work-logs.store', props.task.id), {
        preserveScroll: true,
        onSuccess: () => workLogForm.reset(),
    });
};
const submitReminder = (): void => {
    reminderForm.post(route('tasks.reminders.store', props.task.id), {
        preserveScroll: true,
        onSuccess: () => reminderForm.reset('title', 'description', 'remind_at'),
    });
};
const submitEdit = (): void => {
    editForm.put(route('tasks.update', props.task.id), {
        preserveScroll: true,
    });
};

const deleteTask = (): void => {
    if (!window.confirm(`آیا از حذف تسک «${props.task.title}» مطمئن هستید؟`)) {
        return;
    }

    editForm.delete(route('tasks.destroy', props.task.id), {
        preserveScroll: false,
    });
};

const submitReferral = (): void => {
    referralForm.patch(route('tasks.refer', props.task.id), {
        preserveScroll: true,
        onSuccess: () => referralForm.reset(),
    });
};
const changeStatus = (): void => {
    statusForm.patch(route('tasks.change-status', props.task.id), {
        preserveScroll: true,
    });
};
const updateReminderStatus = (reminderId: number, status: 'completed' | 'cancelled'): void => {
    useForm({ status }).patch(
        route('tasks.reminders.update-status', [props.task.id, reminderId]),
        { preserveScroll: true },
    );
};
const formatDateTime = formatPersianDateTime;
</script>

<template>
    <Head :title="task.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                            تسک #{{ task.id }}
                        </span>
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                            :class="statusClasses[task.status] ?? 'bg-gray-100 text-gray-700 ring-gray-200'"
                        >
                            {{ statusLabels[task.status] ?? task.status }}
                        </span>
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                            :class="priorityClasses[task.priority] ?? 'bg-gray-100 text-gray-700 ring-gray-200'"
                        >
                            اولویت {{ priorityLabels[task.priority] ?? task.priority }}
                        </span>
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                            :class="task.is_active
                                ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                : 'bg-slate-100 text-slate-600 ring-slate-200'"
                        >
                            {{ task.is_active ? 'فعال' : 'غیرفعال' }}
                        </span>
                    </div>
                    <h2 class="mt-2 truncate text-xl font-semibold text-gray-900">{{ task.title }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">جزئیات، سوابق و فعالیت‌های تسک</p>
                </div>

                <Link
                    :href="route('tasks.index')"
                    class="inline-flex shrink-0 items-center rounded-lg border bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50"
                >
                    بازگشت به تسک‌ها
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-6">
            <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="border-b bg-gray-50/70 px-5 py-4 sm:px-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-gray-900">خلاصه تسک</h3>
                            <p class="mt-1 text-sm text-muted-foreground">اطلاعات اصلی و مسئولیت فعلی تسک</p>
                        </div>
                        <span v-if="task.assignee" class="text-sm text-muted-foreground">
                            مسئول فعلی: <strong class="font-medium text-gray-900">{{ task.assignee.name }}</strong>
                        </span>
                    </div>
                </div>

                <div class="grid gap-x-8 gap-y-5 p-5 sm:grid-cols-2 sm:p-6 lg:grid-cols-4">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">مسئول</p>
                        <p class="mt-1.5 font-medium text-gray-900">{{ task.assignee?.name ?? 'بدون مسئول' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">ایجادکننده</p>
                        <p class="mt-1.5 font-medium text-gray-900">{{ task.creator?.name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">پروژه</p>
                        <p class="mt-1.5 font-medium text-gray-900">{{ task.project?.name ?? 'بدون پروژه' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">واحد</p>
                        <p class="mt-1.5 font-medium text-gray-900">{{ task.department?.name ?? 'بدون واحد' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">تاریخ شروع</p>
                        <p class="mt-1.5 font-medium text-gray-900">{{ formatPersianDate(task.start_date) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">مهلت انجام</p>
                        <p class="mt-1.5 font-medium text-gray-900">{{ formatPersianDate(task.due_date) }}</p>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-2">
                        <p class="text-xs font-medium text-muted-foreground">وضعیت و اولویت</p>
                        <div class="mt-1.5 flex flex-wrap gap-2">
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                                :class="statusClasses[task.status] ?? 'bg-gray-100 text-gray-700 ring-gray-200'"
                            >
                                {{ statusLabels[task.status] ?? task.status }}
                            </span>
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                                :class="priorityClasses[task.priority] ?? 'bg-gray-100 text-gray-700 ring-gray-200'"
                            >
                                {{ priorityLabels[task.priority] ?? task.priority }}
                            </span>
                        </div>
                    </div>
                </div>

                <div v-if="task.description" class="border-t px-5 py-5 sm:px-6">
                    <p class="text-xs font-medium text-muted-foreground">توضیحات</p>
                    <p class="mt-2 whitespace-pre-line text-sm leading-7 text-gray-700">{{ task.description }}</p>
                </div>
            </section>

            <section v-if="abilities.change_status" class="rounded-xl border bg-card p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-gray-900">تغییر وضعیت</h3>
                        <p class="mt-1 text-sm text-muted-foreground">وضعیت فعلی تسک را به‌روزرسانی کنید.</p>
                    </div>
                    <span class="text-sm text-muted-foreground">
                        وضعیت فعلی: <strong class="font-medium text-gray-900">{{ statusLabels[task.status] ?? task.status }}</strong>
                    </span>
                </div>
                <form class="mt-4 flex flex-col gap-3 sm:flex-row" @submit.prevent="changeStatus">
                    <select v-model="statusForm.status" class="h-10 rounded-lg border bg-background px-3 text-sm shadow-sm outline-none transition focus:border-gray-400 focus:ring-2 focus:ring-gray-200 sm:min-w-52">
                        <option value="todo">انجام نشده</option>
                        <option value="in_progress">در حال انجام</option>
                        <option value="completed">تکمیل شده</option>
                        <option value="cancelled">لغو شده</option>
                    </select>
                    <button type="submit" :disabled="statusForm.processing" class="h-10 rounded-lg bg-gray-900 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50">
                        {{ statusForm.processing ? 'در حال ذخیره...' : 'ذخیره وضعیت' }}
                    </button>
                </form>
            </section>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1.55fr)_minmax(320px,1fr)]">
                <section class="min-w-0 overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div class="border-b bg-gray-50/70 px-5 py-4 sm:px-6">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-gray-900">خط زمانی تسک</h3>
                                <p class="mt-1 text-sm text-muted-foreground">سوابق تخصیص، وضعیت و فعالیت‌های ثبت‌شده</p>
                            </div>
                            <span class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-200">
                                {{ timeline.length }} مورد
                            </span>
                        </div>
                    </div>

                    <div v-if="timeline.length === 0" class="p-8 text-center">
                        <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-sm font-semibold text-gray-500">—</div>
                        <p class="mt-3 text-sm font-medium text-gray-700">هنوز سابقه‌ای ثبت نشده است.</p>
                        <p class="mt-1 text-xs text-muted-foreground">با انجام فعالیت روی تسک، سوابق در این بخش نمایش داده می‌شوند.</p>
                    </div>

                    <div v-else class="divide-y">
                        <article v-for="(item, index) in timeline" :key="item.type + '-' + index" class="p-5 transition hover:bg-gray-50/60 sm:p-6">
                            <div class="flex gap-3">
                                <div
                                    class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold ring-1 ring-inset"
                                    :class="timelineClasses[item.type] ?? 'bg-gray-100 text-gray-600 ring-gray-200'"
                                >
                                    {{ index + 1 }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <span class="text-xs font-medium text-muted-foreground">
                                                {{ timelineLabels[item.type] ?? item.type }}
                                            </span>
                                            <h4 class="mt-1 font-medium text-gray-900">{{ item.title }}</h4>
                                        </div>
                                        <time class="shrink-0 text-xs text-muted-foreground">{{ formatDateTime(item.occurred_at) }}</time>
                                    </div>
                                    <p v-if="item.description" class="mt-3 whitespace-pre-line text-sm leading-6 text-gray-600">
                                        {{ item.description }}
                                    </p>
                                    <p v-if="item.user" class="mt-3 text-xs text-muted-foreground">
                                        ثبت توسط {{ item.user.name }}
                                    </p>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>

                <div class="space-y-6">
                    <section v-if="abilities.refer" class="rounded-xl border bg-card p-5 shadow-sm sm:p-6">
                        <div>
                            <h3 class="font-semibold text-gray-900">ارجاع تسک</h3>
                            <p class="mt-1 text-sm text-muted-foreground">مسئول فعلی تسک را به کاربر دیگری واگذار کنید.</p>
                        </div>
                        <form class="mt-4 space-y-4" @submit.prevent="submitReferral">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">مسئول جدید</label>
                                <select v-model="referralForm.assignee_id" required class="h-10 w-full rounded-lg border bg-background px-3 text-sm shadow-sm outline-none transition focus:border-gray-400 focus:ring-2 focus:ring-gray-200">
                                    <option value="" disabled>انتخاب مسئول جدید</option>
                                    <option v-for="user in assigneeOptions" :key="user.id" :value="String(user.id)">
                                        {{ user.name }}
                                    </option>
                                </select>
                                <p v-if="assigneeOptions.length === 0" class="mt-1.5 text-xs text-muted-foreground">
                                    برای این تسک مسئول فعالی قابل انتخاب نیست.
                                </p>
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">توضیح ارجاع</label>
                                <input v-model="referralForm.note" type="text" class="h-10 w-full rounded-lg border bg-background px-3 text-sm shadow-sm outline-none transition focus:border-gray-400 focus:ring-2 focus:ring-gray-200" />
                            </div>
                            <button type="submit" :disabled="referralForm.processing || assigneeOptions.length === 0" class="h-10 w-full rounded-lg bg-gray-900 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50">
                                {{ referralForm.processing ? 'در حال ارجاع...' : 'ارجاع تسک' }}
                            </button>
                        </form>
                    </section>

                    <section v-if="abilities.comment" class="rounded-xl border bg-card p-5 shadow-sm sm:p-6">
                        <h3 class="font-semibold text-gray-900">ثبت نظر</h3>
                        <p class="mt-1 text-sm text-muted-foreground">نظر یا توضیح مرتبط با تسک را ثبت کنید.</p>
                        <form class="mt-4 space-y-3" @submit.prevent="submitComment">
                            <textarea v-model="commentForm.body" rows="4" required class="w-full rounded-lg border bg-background p-3 text-sm leading-6 shadow-sm outline-none transition focus:border-gray-400 focus:ring-2 focus:ring-gray-200" placeholder="نظر یا توضیح خود را وارد کنید..." />
                            <button type="submit" :disabled="commentForm.processing" class="h-10 rounded-lg bg-gray-900 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50">
                                {{ commentForm.processing ? 'در حال ثبت...' : 'ثبت نظر' }}
                            </button>
                        </form>
                    </section>

                    <section v-if="abilities.log_work" class="rounded-xl border bg-card p-5 shadow-sm sm:p-6">
                        <h3 class="font-semibold text-gray-900">ثبت گزارش فعالیت</h3>
                        <p class="mt-1 text-sm text-muted-foreground">فعالیت انجام‌شده روی تسک را ثبت کنید.</p>
                        <form class="mt-4 space-y-3" @submit.prevent="submitWorkLog">
                            <input v-model="workLogForm.title" required class="h-10 w-full rounded-lg border bg-background px-3 text-sm shadow-sm outline-none transition focus:border-gray-400 focus:ring-2 focus:ring-gray-200" placeholder="عنوان فعالیت" />
                            <textarea v-model="workLogForm.description" rows="3" class="w-full rounded-lg border bg-background p-3 text-sm leading-6 shadow-sm outline-none transition focus:border-gray-400 focus:ring-2 focus:ring-gray-200" placeholder="شرح فعالیت" />
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-muted-foreground">شروع</label>
                                    <input v-model="workLogForm.started_at" type="datetime-local" class="h-10 w-full rounded-lg border bg-background px-3 text-sm shadow-sm" />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-muted-foreground">پایان</label>
                                    <input v-model="workLogForm.completed_at" type="datetime-local" class="h-10 w-full rounded-lg border bg-background px-3 text-sm shadow-sm" />
                                </div>
                            </div>
                            <button type="submit" :disabled="workLogForm.processing" class="h-10 rounded-lg bg-gray-900 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50">
                                {{ workLogForm.processing ? 'در حال ثبت...' : 'ثبت گزارش فعالیت' }}
                            </button>
                        </form>
                    </section>

                    <section v-if="abilities.create_reminder" class="rounded-xl border bg-card p-5 shadow-sm sm:p-6">
                        <h3 class="font-semibold text-gray-900">ایجاد یادآور</h3>
                        <p class="mt-1 text-sm text-muted-foreground">یک یادآور برای خودتان روی این تسک ایجاد کنید.</p>
                        <form class="mt-4 space-y-3" @submit.prevent="submitReminder">
                            <input v-model="reminderForm.title" required class="h-10 w-full rounded-lg border bg-background px-3 text-sm shadow-sm outline-none transition focus:border-gray-400 focus:ring-2 focus:ring-gray-200" placeholder="عنوان یادآور" />
                            <textarea v-model="reminderForm.description" rows="3" class="w-full rounded-lg border bg-background p-3 text-sm leading-6 shadow-sm outline-none transition focus:border-gray-400 focus:ring-2 focus:ring-gray-200" placeholder="توضیحات" />
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-muted-foreground">زمان یادآور</label>
                                <input v-model="reminderForm.remind_at" type="datetime-local" required class="h-10 w-full rounded-lg border bg-background px-3 text-sm shadow-sm" />
                            </div>
                            <button type="submit" :disabled="reminderForm.processing" class="h-10 rounded-lg bg-gray-900 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50">
                                {{ reminderForm.processing ? 'در حال ایجاد...' : 'ایجاد یادآور' }}
                            </button>
                        </form>
                    </section>

                    <section v-if="task.reminders.length" class="overflow-hidden rounded-xl border bg-card shadow-sm">
                        <div class="border-b bg-gray-50/70 px-5 py-4 sm:px-6">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold text-gray-900">یادآورها</h3>
                                    <p class="mt-1 text-sm text-muted-foreground">یادآورهای ثبت‌شده برای این تسک</p>
                                </div>
                                <span class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-200">
                                    {{ task.reminders.length }}
                                </span>
                            </div>
                        </div>

                        <div class="divide-y">
                            <div v-for="reminder in task.reminders" :key="reminder.id" class="p-5 sm:p-6">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900">{{ reminder.title }}</p>
                                        <p class="mt-1 text-xs text-muted-foreground">
                                            {{ reminder.user?.name ?? '—' }} · {{ formatDateTime(reminder.remind_at) }}
                                        </p>
                                    </div>
                                    <span
                                        class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                                        :class="reminder.status === 'pending'
                                            ? 'bg-amber-50 text-amber-700 ring-amber-200'
                                            : reminder.status === 'completed'
                                                ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                                : 'bg-slate-100 text-slate-600 ring-slate-200'"
                                    >
                                        {{ reminder.status === 'pending' ? 'در انتظار' : reminder.status === 'completed' ? 'تکمیل شده' : 'لغو شده' }}
                                    </span>
                                </div>

                                <p v-if="reminder.description" class="mt-3 text-sm leading-6 text-gray-600">
                                    {{ reminder.description }}
                                </p>

                                <div v-if="reminder.status === 'pending' && reminder.user?.id === currentUserId" class="mt-4 flex flex-wrap gap-2">
                                    <button type="button" class="rounded-lg border bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-50" @click="updateReminderStatus(reminder.id, 'completed')">
                                        تکمیل یادآور
                                    </button>
                                    <button type="button" class="rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-medium text-red-600 shadow-sm transition hover:bg-red-50" @click="updateReminderStatus(reminder.id, 'cancelled')">
                                        لغو یادآور
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <section v-if="abilities.update" class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b bg-gray-50/70 px-5 py-4 sm:px-6">
                    <div>
                        <h3 class="font-semibold text-gray-900">ویرایش اطلاعات تسک</h3>
                        <p class="mt-1 text-sm text-muted-foreground">اطلاعات اصلی تسک را ویرایش کنید.</p>
                    </div>
                    <button
                        v-if="abilities.delete"
                        type="button"
                        class="rounded-lg border border-red-200 bg-white px-3.5 py-2 text-sm font-medium text-red-600 shadow-sm transition hover:bg-red-50"
                        @click="deleteTask"
                    >
                        حذف تسک
                    </button>
                </div>

                <form class="grid gap-5 p-5 sm:p-6 md:grid-cols-2" @submit.prevent="submitEdit">
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700">عنوان تسک</label>
                        <input v-model="editForm.title" type="text" required maxlength="255" class="h-10 w-full rounded-lg border bg-background px-3 text-sm shadow-sm outline-none transition focus:border-gray-400 focus:ring-2 focus:ring-gray-200" />
                        <p v-if="editForm.errors.title" class="mt-1.5 text-sm text-red-600">{{ editForm.errors.title }}</p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">اولویت</label>
                        <select v-model="editForm.priority" class="h-10 w-full rounded-lg border bg-background px-3 text-sm shadow-sm outline-none transition focus:border-gray-400 focus:ring-2 focus:ring-gray-200">
                            <option value="low">کم</option>
                            <option value="normal">عادی</option>
                            <option value="high">زیاد</option>
                            <option value="urgent">فوری</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2 pt-7">
                        <input id="task-active" v-model="editForm.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300" />
                        <label for="task-active" class="text-sm font-medium text-gray-700">تسک فعال باشد</label>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">تاریخ شروع</label>
                        <JalaliDatePicker v-model="editForm.start_date" />
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">مهلت انجام</label>
                        <JalaliDatePicker v-model="editForm.due_date" />
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-4 md:col-span-2">
                        <div class="mb-3"><h4 class="text-sm font-semibold text-gray-900">پیگیری خارجی <span class="font-normal text-gray-500">(اختیاری)</span></h4><p class="mt-1 text-xs text-gray-500">برای تسک‌هایی مثل «طراحی برد» می‌توانید این بخش را خالی بگذارید.</p></div>
                        <div class="grid gap-4 md:grid-cols-3">
                            <select v-model="editForm.external_organization_id" @change="handleExternalOrganizationChange" class="h-10 w-full rounded-lg border bg-background px-3 text-sm"><option :value="null">بدون پیگیری خارجی</option><option v-for="item in externalPartyOptions.external_organizations" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                            <select v-model="editForm.external_department_id" :disabled="editForm.external_organization_id === null" @change="handleExternalDepartmentChange" class="h-10 w-full rounded-lg border bg-background px-3 text-sm"><option :value="null">بدون واحد خارجی</option><option v-for="item in externalDepartments" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                            <select v-model="editForm.external_contact_id" :disabled="editForm.external_organization_id === null" class="h-10 w-full rounded-lg border bg-background px-3 text-sm"><option :value="null">بدون شخص مشخص</option><option v-for="item in externalContacts" :key="item.id" :value="item.id">{{ item.name }}{{ item.position ? ' — ' + item.position : '' }}</option></select>
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700">توضیحات</label>
                        <textarea v-model="editForm.description" rows="4" class="w-full rounded-lg border bg-background p-3 text-sm leading-6 shadow-sm outline-none transition focus:border-gray-400 focus:ring-2 focus:ring-gray-200" />
                    </div>

                    <div class="md:col-span-2">
                        <button type="submit" :disabled="editForm.processing" class="h-10 rounded-lg bg-gray-900 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50">
                            {{ editForm.processing ? 'در حال ذخیره...' : 'ذخیره تغییرات' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
