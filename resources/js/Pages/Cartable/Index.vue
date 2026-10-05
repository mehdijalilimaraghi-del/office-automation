<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

interface TaskItem {
    id: number;
    title: string;
    status: string;
    priority: string;
    due_date: string | null;
    project?: { id: number; name: string } | null;
    department?: { id: number; name: string } | null;
}

interface Referral {
    task_id: number | string;
    task?: TaskItem | null;
    fromUser?: { id: number; name: string } | null;
    note?: string | null;
    created_at?: string;
}

interface CorrespondenceReferral {
    id: number;
    instruction?: string | null;
    correspondence?: { id: number; registration_number: string; subject: string } | null;
    fromUser?: { id: number; name: string } | null;
}

interface CartableData {
    summary: { my_tasks: number; pending_approvals: number; referrals: number; correspondence_referrals?: number };
    myTasks: TaskItem[];
    pendingApprovals: TaskItem[];
    referrals: Referral[];
    correspondenceReferrals?: CorrespondenceReferral[];
}

const props = defineProps<{ cartable: CartableData }>();

const statusLabels: Record<string, string> = {
    todo: 'در انتظار انجام',
    in_progress: 'در حال انجام',
    submitted: 'ارسال‌شده برای تأیید',
    completed: 'تکمیل‌شده',
    cancelled: 'لغوشده',
};
const priorityLabels: Record<string, string> = {
    low: 'کم',
    normal: 'عادی',
    high: 'زیاد',
    urgent: 'فوری',
};

function taskMeta(task: TaskItem): string {
    return [task.project?.name, task.department?.name].filter(Boolean).join(' · ') || 'بدون پروژه یا واحد سازمانی';
}

function formatDate(value: string | null | undefined): string {
    if (!value) return 'بدون مهلت';
    const date = new Date(value);
    return new Intl.DateTimeFormat('fa-IR', { year: 'numeric', month: '2-digit', day: '2-digit' }).format(date);
}
</script>

<template>
    <Head title="کارتابل من" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-wide text-teal-600">مرکز عملیات</p>
                    <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">کارتابل من</h2>
                    <p class="mt-1 text-sm text-slate-500">کارها، ارجاعات و مواردی که نیاز به اقدام شما دارند.</p>
                </div>
                <Link :href="route('tasks.index')" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-slate-800">
                    مشاهده همه تسک‌ها
                    <span>←</span>
                </Link>
            </div>
        </template>

        <div class="min-h-[calc(100vh-8rem)] bg-slate-50 py-6 md:py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="relative overflow-hidden rounded-3xl bg-slate-900 px-6 py-7 text-white shadow-lg sm:px-8">
                    <div class="absolute -left-16 -top-24 h-64 w-64 rounded-full bg-teal-400/20 blur-3xl"></div>
                    <div class="absolute -bottom-28 right-16 h-72 w-72 rounded-full bg-blue-400/10 blur-3xl"></div>
                    <div class="relative grid gap-6 lg:grid-cols-[1fr_auto] lg:items-center">
                        <div>
                            <span class="inline-flex rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-teal-100 ring-1 ring-white/10">Workspace</span>
                            <h1 class="mt-4 text-2xl font-bold sm:text-3xl">همه اقدام‌های مهم، یکجا</h1>
                            <p class="mt-2 max-w-2xl text-sm leading-7 text-slate-300">کارتابل نقطه ورود عملیاتی شماست؛ رکوردهای اصلی همچنان در ماژول‌های مربوط به خودشان مدیریت می‌شوند.</p>
                        </div>
                        <div class="grid grid-cols-3 gap-2 rounded-2xl bg-white/5 p-2 ring-1 ring-white/10">
                            <div class="min-w-[82px] rounded-xl bg-white/10 px-4 py-3 text-center">
                                <div class="text-2xl font-bold">{{ props.cartable.summary.my_tasks }}</div>
                                <div class="mt-1 text-[11px] text-slate-300">وظایف من</div>
                            </div>
                            <div class="min-w-[82px] rounded-xl bg-white/10 px-4 py-3 text-center">
                                <div class="text-2xl font-bold">{{ props.cartable.summary.pending_approvals }}</div>
                                <div class="mt-1 text-[11px] text-slate-300">تأیید</div>
                            </div>
                            <div class="min-w-[82px] rounded-xl bg-white/10 px-4 py-3 text-center">
                                <div class="text-2xl font-bold">{{ props.cartable.summary.referrals }}</div>
                                <div class="mt-1 text-[11px] text-slate-300">ارجاع</div>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(340px,.9fr)]">
                    <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6">
                            <div>
                                <h3 class="font-bold text-slate-900">وظایف من</h3>
                                <p class="mt-1 text-xs text-slate-500">کارهای فعال و در حال پیگیری</p>
                            </div>
                            <Link :href="route('tasks.index')" class="text-sm font-semibold text-teal-700 hover:text-teal-800">همه</Link>
                        </div>
                        <div v-if="props.cartable.myTasks.length === 0" class="px-6 py-14 text-center">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">✓</div>
                            <p class="mt-4 text-sm font-semibold text-slate-700">وظیفه فعالی ندارید.</p>
                            <p class="mt-1 text-xs text-slate-400">وقتی کاری به شما اختصاص داده شود، اینجا نمایش داده می‌شود.</p>
                        </div>
                        <div v-else class="divide-y divide-slate-100">
                            <Link v-for="task in props.cartable.myTasks" :key="task.id" :href="route('tasks.show', task.id)" class="block px-5 py-4 transition hover:bg-slate-50 sm:px-6">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="h-2 w-2 shrink-0 rounded-full" :class="task.status === 'submitted' ? 'bg-amber-500' : task.status === 'in_progress' ? 'bg-blue-500' : 'bg-slate-300'"></span>
                                            <span class="truncate font-semibold text-slate-800">{{ task.title }}</span>
                                        </div>
                                        <p class="mt-1.5 pr-4 text-xs text-slate-500">{{ taskMeta(task) }}</p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ statusLabels[task.status] || task.status }}</span>
                                        <span class="text-xs font-medium" :class="task.priority === 'urgent' ? 'text-rose-600' : task.priority === 'high' ? 'text-amber-600' : 'text-slate-500'">{{ priorityLabels[task.priority] || task.priority }}</span>
                                        <span class="hidden text-xs text-slate-400 sm:inline">{{ formatDate(task.due_date) }}</span>
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </section>

                    <div class="space-y-6">
                        <section class="overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-sm">
                            <div class="border-b border-amber-100 bg-amber-50/70 px-5 py-4">
                                <h3 class="font-bold text-slate-900">در انتظار تأیید</h3>
                                <p class="mt-1 text-xs text-slate-500">مواردی که در سطح مدیریتی نیاز به تصمیم دارند</p>
                            </div>
                            <div v-if="props.cartable.pendingApprovals.length === 0" class="px-5 py-8 text-center">
                                <p class="text-sm font-semibold text-slate-700">موردی برای تأیید ندارید.</p>
                            </div>
                            <div v-else class="divide-y divide-slate-100">
                                <Link v-for="task in props.cartable.pendingApprovals" :key="task.id" :href="route('tasks.show', task.id)" class="block px-5 py-4 transition hover:bg-amber-50/40">
                                    <p class="font-semibold text-slate-800">{{ task.title }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ taskMeta(task) }}</p>
                                    <span class="mt-3 inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">منتظر اقدام شما</span>
                                </Link>
                            </div>
                        </section>

                        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="border-b border-slate-100 px-5 py-4">
                                <h3 class="font-bold text-slate-900">ارجاعات فعال</h3>
                                <p class="mt-1 text-xs text-slate-500">ارجاع‌هایی که هنوز بسته نشده‌اند</p>
                            </div>
                            <div v-if="props.cartable.referrals.length === 0" class="px-5 py-8 text-center">
                                <p class="text-sm font-semibold text-slate-700">ارجاع فعالی ندارید.</p>
                            </div>
                            <div v-else class="divide-y divide-slate-100">
                                <Link v-for="referral in props.cartable.referrals" :key="referral.task_id" :href="route('tasks.show', referral.task_id)" class="block px-5 py-4 transition hover:bg-slate-50">
                                    <p class="font-semibold text-slate-800">{{ referral.task?.title || 'تسک ارجاع‌شده' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">از طرف {{ referral.fromUser?.name || 'کاربر سازمان' }}</p>
                                    <p v-if="referral.note" class="mt-2 line-clamp-2 text-xs leading-5 text-slate-500">{{ referral.note }}</p>
                                </Link>
                            </div>
                        </section>

                        <section class="overflow-hidden rounded-2xl border border-indigo-200 bg-white shadow-sm">
                            <div class="border-b border-indigo-100 bg-indigo-50/70 px-5 py-4">
                                <h3 class="font-bold text-slate-900">ارجاعات مکاتبات</h3>
                                <p class="mt-1 text-xs text-slate-500">مکاتباتی که برای اقدام به شما ارجاع شده‌اند</p>
                            </div>
                            <div v-if="!props.cartable.correspondenceReferrals?.length" class="px-5 py-8 text-center">
                                <p class="text-sm font-semibold text-slate-700">ارجاع مکاتبه‌ای ندارید.</p>
                            </div>
                            <div v-else class="divide-y divide-slate-100">
                                <div v-for="item in props.cartable.correspondenceReferrals" :key="item.id" class="px-5 py-4">
                                    <p class="font-semibold text-slate-800">{{ item.correspondence?.registration_number }} — {{ item.correspondence?.subject }}</p>
                                    <p class="mt-1 text-xs text-slate-500">از طرف {{ item.fromUser?.name || 'کاربر سازمان' }}</p>
                                    <p v-if="item.instruction" class="mt-2 text-xs leading-5 text-slate-500">{{ item.instruction }}</p>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
