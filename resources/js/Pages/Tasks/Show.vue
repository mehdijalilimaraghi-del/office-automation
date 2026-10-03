<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

interface User { id: number; name: string; }
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
});

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
const formatDateTime = (value: string | null): string => value ? value.replace('T', ' ').slice(0, 16) : '—';
</script>

<template>
    <Head :title="task.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold">{{ task.title }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">جزئیات، سوابق و فعالیت‌های تسک</p>
                </div>
                <Link :href="route('tasks.index')" class="rounded-md border px-3 py-2 text-sm hover:bg-muted">
                    بازگشت به تسک‌ها
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-6">
            <section class="rounded-lg border bg-card p-6">
                <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                    <div><p class="text-sm text-muted-foreground">وضعیت</p><p class="mt-1 font-medium">{{ statusLabels[task.status] ?? task.status }}</p></div>
                    <div><p class="text-sm text-muted-foreground">اولویت</p><p class="mt-1 font-medium">{{ priorityLabels[task.priority] ?? task.priority }}</p></div>
                    <div><p class="text-sm text-muted-foreground">مسئول</p><p class="mt-1 font-medium">{{ task.assignee?.name ?? 'بدون مسئول' }}</p></div>
                    <div><p class="text-sm text-muted-foreground">ایجادکننده</p><p class="mt-1 font-medium">{{ task.creator?.name ?? '—' }}</p></div>
                    <div><p class="text-sm text-muted-foreground">پروژه</p><p class="mt-1 font-medium">{{ task.project?.name ?? 'بدون پروژه' }}</p></div>
                    <div><p class="text-sm text-muted-foreground">واحد</p><p class="mt-1 font-medium">{{ task.department?.name ?? 'بدون واحد' }}</p></div>
                    <div><p class="text-sm text-muted-foreground">تاریخ شروع</p><p class="mt-1 font-medium">{{ task.start_date ?? '—' }}</p></div>
                    <div><p class="text-sm text-muted-foreground">مهلت انجام</p><p class="mt-1 font-medium">{{ task.due_date ?? '—' }}</p></div>
                </div>
                <div v-if="task.description" class="mt-6 border-t pt-6">
                    <p class="text-sm text-muted-foreground">توضیحات</p>
                    <p class="mt-2 whitespace-pre-line text-sm">{{ task.description }}</p>
                </div>
            </section>

            <section v-if="abilities.update" class="rounded-lg border bg-card p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-semibold">ویرایش اطلاعات تسک</h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            اطلاعات اصلی تسک را ویرایش کنید.
                        </p>
                    </div>
                    <button
                        v-if="abilities.delete"
                        type="button"
                        class="rounded-md border border-red-200 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50"
                        @click="deleteTask"
                    >
                        حذف تسک
                    </button>
                </div>

                <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="submitEdit">
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium">عنوان تسک</label>
                        <input
                            v-model="editForm.title"
                            type="text"
                            required
                            maxlength="255"
                            class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                        />
                        <p v-if="editForm.errors.title" class="mt-1 text-sm text-red-600">
                            {{ editForm.errors.title }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium">اولویت</label>
                        <select
                            v-model="editForm.priority"
                            class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="low">کم</option>
                            <option value="normal">عادی</option>
                            <option value="high">زیاد</option>
                            <option value="urgent">فوری</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2 pt-7">
                        <input
                            id="task-active"
                            v-model="editForm.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300"
                        />
                        <label for="task-active" class="text-sm font-medium">
                            تسک فعال باشد
                        </label>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium">تاریخ شروع</label>
                        <input
                            v-model="editForm.start_date"
                            type="date"
                            class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                        />
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium">مهلت انجام</label>
                        <input
                            v-model="editForm.due_date"
                            type="date"
                            class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium">توضیحات</label>
                        <textarea
                            v-model="editForm.description"
                            rows="4"
                            class="w-full rounded-md border bg-background p-3 text-sm"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
                        >
                            {{ editForm.processing ? 'در حال ذخیره...' : 'ذخیره تغییرات' }}
                        </button>
                    </div>
                </form>
            </section>

            <section v-if="abilities.change_status" class="rounded-lg border bg-card p-5">
                <h3 class="font-semibold">تغییر وضعیت</h3>
                <form class="mt-4 flex flex-wrap gap-3" @submit.prevent="changeStatus">
                    <select v-model="statusForm.status" class="h-10 rounded-md border bg-background px-3 text-sm">
                        <option value="todo">انجام نشده</option>
                        <option value="in_progress">در حال انجام</option>
                        <option value="completed">تکمیل شده</option>
                        <option value="cancelled">لغو شده</option>
                    </select>
                    <button type="submit" :disabled="statusForm.processing" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50">ذخیره وضعیت</button>
                </form>
            </section>

            <section v-if="abilities.refer" class="rounded-lg border bg-card p-5">
                <h3 class="font-semibold">ارجاع تسک</h3>
                <form class="mt-4 grid gap-4 md:grid-cols-2" @submit.prevent="submitReferral">
                    <div>
                        <label class="mb-2 block text-sm font-medium">مسئول جدید</label>
                        <select
                            v-model="referralForm.assignee_id"
                            required
                            class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="" disabled>انتخاب مسئول جدید</option>
                            <option
                                v-for="user in assigneeOptions"
                                :key="user.id"
                                :value="String(user.id)"
                            >
                                {{ user.name }}
                            </option>
                        </select>
                        <p v-if="assigneeOptions.length === 0" class="mt-1 text-xs text-muted-foreground">
                            برای این تسک مسئول فعالی قابل انتخاب نیست.
                        </p>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium">توضیح ارجاع</label>
                        <input v-model="referralForm.note" type="text" class="h-10 w-full rounded-md border bg-background px-3 text-sm" />
                    </div>
                    <button type="submit" :disabled="referralForm.processing" class="w-fit rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50">ارجاع</button>
                </form>
            </section>

            <div class="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
                <section class="rounded-lg border bg-card">
                    <div class="border-b p-5"><h3 class="font-semibold">خط زمانی تسک</h3></div>
                    <div v-if="timeline.length === 0" class="p-6 text-sm text-muted-foreground">هنوز سابقه‌ای برای این تسک ثبت نشده است.</div>
                    <div v-else class="divide-y">
                        <article v-for="(item, index) in timeline" :key="item.type + '-' + index" class="p-5">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <span class="text-xs text-muted-foreground">{{ timelineLabels[item.type] ?? item.type }}</span>
                                    <h4 class="mt-1 font-medium">{{ item.title }}</h4>
                                </div>
                                <time class="text-xs text-muted-foreground">{{ formatDateTime(item.occurred_at) }}</time>
                            </div>
                            <p v-if="item.description" class="mt-3 whitespace-pre-line text-sm text-muted-foreground">{{ item.description }}</p>
                            <p v-if="item.user" class="mt-3 text-xs text-muted-foreground">ثبت توسط {{ item.user.name }}</p>
                        </article>
                    </div>
                </section>

                <div class="space-y-6">
                    <section v-if="abilities.comment" class="rounded-lg border bg-card p-5">
                        <h3 class="font-semibold">ثبت نظر</h3>
                        <form class="mt-4 space-y-3" @submit.prevent="submitComment">
                            <textarea v-model="commentForm.body" rows="4" required class="w-full rounded-md border bg-background p-3 text-sm" placeholder="نظر یا توضیح خود را وارد کنید..." />
                            <button type="submit" :disabled="commentForm.processing" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50">ثبت نظر</button>
                        </form>
                    </section>

                    <section v-if="abilities.log_work" class="rounded-lg border bg-card p-5">
                        <h3 class="font-semibold">ثبت گزارش فعالیت</h3>
                        <form class="mt-4 space-y-3" @submit.prevent="submitWorkLog">
                            <input v-model="workLogForm.title" required class="h-10 w-full rounded-md border bg-background px-3 text-sm" placeholder="عنوان فعالیت" />
                            <textarea v-model="workLogForm.description" rows="3" class="w-full rounded-md border bg-background p-3 text-sm" placeholder="شرح فعالیت" />
                            <div class="grid gap-3 sm:grid-cols-2">
                                <input v-model="workLogForm.started_at" type="datetime-local" class="h-10 rounded-md border bg-background px-3 text-sm" />
                                <input v-model="workLogForm.completed_at" type="datetime-local" class="h-10 rounded-md border bg-background px-3 text-sm" />
                            </div>
                            <button type="submit" :disabled="workLogForm.processing" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50">ثبت گزارش</button>
                        </form>
                    </section>

                    <section v-if="abilities.create_reminder" class="rounded-lg border bg-card p-5">
                        <h3 class="font-semibold">ایجاد یادآور</h3>
                        <form class="mt-4 space-y-3" @submit.prevent="submitReminder">
                            <input v-model="reminderForm.title" required class="h-10 w-full rounded-md border bg-background px-3 text-sm" placeholder="عنوان یادآور" />
                            <textarea v-model="reminderForm.description" rows="3" class="w-full rounded-md border bg-background p-3 text-sm" placeholder="توضیحات" />
                            <input v-model="reminderForm.remind_at" type="datetime-local" required class="h-10 w-full rounded-md border bg-background px-3 text-sm" />
                            <button type="submit" :disabled="reminderForm.processing" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50">ایجاد یادآور برای خودم</button>
                        </form>
                    </section>

                    <section v-if="task.reminders.length" class="rounded-lg border bg-card">
                        <div class="border-b p-5"><h3 class="font-semibold">یادآورها</h3></div>
                        <div class="divide-y">
                            <div v-for="reminder in task.reminders" :key="reminder.id" class="p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-medium">{{ reminder.title }}</p>
                                        <p class="mt-1 text-xs text-muted-foreground">{{ reminder.user?.name ?? '—' }} · {{ formatDateTime(reminder.remind_at) }}</p>
                                    </div>
                                    <span class="text-xs text-muted-foreground">{{ reminder.status === 'pending' ? 'در انتظار' : reminder.status === 'completed' ? 'تکمیل شده' : 'لغو شده' }}</span>
                                </div>
                                <p v-if="reminder.description" class="mt-3 text-sm text-muted-foreground">{{ reminder.description }}</p>
                                <div v-if="reminder.status === 'pending' && reminder.user?.id === currentUserId" class="mt-4 flex gap-2">
                                    <button type="button" class="rounded-md border px-3 py-1.5 text-xs hover:bg-muted" @click="updateReminderStatus(reminder.id, 'completed')">تکمیل یادآور</button>
                                    <button type="button" class="rounded-md border px-3 py-1.5 text-xs hover:bg-muted" @click="updateReminderStatus(reminder.id, 'cancelled')">لغو یادآور</button>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
