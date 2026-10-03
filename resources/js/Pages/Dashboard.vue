<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

interface DashboardStats {
    my_tasks: number;
    today_tasks: number;
    overdue_tasks: number;
    active_projects: number;
    completed_tasks: number;
}

interface DashboardTask {
    id: number;
    title: string;
    status: string;
    priority: string;
    due_date: string | null;
    project: string | null;
    assignee: string | null;
}

const props = defineProps<{
    stats: DashboardStats;
    recentTasks: DashboardTask[];
}>();

const statusLabels: Record<string, string> = {
    todo: 'در انتظار انجام',
    in_progress: 'در حال انجام',
    completed: 'تکمیل شده',
    cancelled: 'لغو شده',
};

const statusClasses: Record<string, string> = {
    todo: 'bg-slate-100 text-slate-700',
    in_progress: 'bg-blue-50 text-blue-700',
    completed: 'bg-emerald-50 text-emerald-700',
    cancelled: 'bg-rose-50 text-rose-700',
};

const priorityLabels: Record<string, string> = {
    low: 'کم',
    normal: 'عادی',
    high: 'زیاد',
    urgent: 'فوری',
};

const priorityClasses: Record<string, string> = {
    low: 'text-slate-500',
    normal: 'text-slate-600',
    high: 'text-amber-600',
    urgent: 'font-semibold text-rose-600',
};

const formatDate = (value: string | null) => {
    if (!value) return 'بدون مهلت';
    const [year, month, day] = value.split('-');
    return year + '/' + month + '/' + day;
};

const quickActions = [
    { label: 'مشاهده تسک‌ها', description: 'مدیریت و پیگیری کارها', href: '/tasks', icon: '✓' },
    { label: 'مشاهده پروژه‌ها', description: 'پروژه‌ها و اعضای تیم', href: '/projects', icon: '▦' },
    { label: 'طرف‌های خارجی', description: 'سازمان‌ها و مخاطبان', href: '/external-parties', icon: '◎' },
];
</script>

<template>
    <Head title="داشبورد" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold tracking-wide text-teal-600">مرکز عملیات</p>
                    <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">داشبورد</h2>
                    <p class="mt-1 text-sm text-slate-500">نمایی سریع از کارها، پروژه‌ها و فعالیت‌های شما</p>
                </div>
                <Link href="/tasks" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
                    <span class="text-base">＋</span>
                    مدیریت تسک‌ها
                </Link>
            </div>
        </template>

        <div class="min-h-[calc(100vh-9rem)] bg-slate-50/80 py-7">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="relative overflow-hidden rounded-2xl bg-slate-900 px-6 py-7 text-white shadow-lg sm:px-8">
                    <div class="absolute -left-16 -top-24 h-64 w-64 rounded-full bg-teal-400/20 blur-3xl"></div>
                    <div class="absolute -bottom-24 right-12 h-64 w-64 rounded-full bg-blue-400/10 blur-3xl"></div>
                    <div class="relative max-w-2xl">
                        <span class="inline-flex rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-teal-100 ring-1 ring-white/10">
                            Office Automation
                        </span>
                        <h1 class="mt-4 text-2xl font-bold tracking-tight sm:text-3xl">امروز چه چیزی را جلو می‌بریم؟</h1>
                        <p class="mt-2 max-w-xl text-sm leading-7 text-slate-300">
                            وضعیت کارهای جاری را ببینید، موارد عقب‌افتاده را پیگیری کنید و بدون جابه‌جایی بین صفحات، سریع به بخش‌های اصلی دسترسی داشته باشید.
                        </p>
                    </div>
                </section>

                <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <Link
                        v-for="item in [
                            { label: 'تسک‌های من', value: props.stats.my_tasks, href: '/tasks', tone: 'teal' },
                            { label: 'تسک‌های امروز', value: props.stats.today_tasks, href: '/tasks', tone: 'blue' },
                            { label: 'عقب‌افتاده', value: props.stats.overdue_tasks, href: '/tasks', tone: 'rose' },
                            { label: 'پروژه‌های فعال', value: props.stats.active_projects, href: '/projects', tone: 'violet' },
                            { label: 'تکمیل‌شده', value: props.stats.completed_tasks, href: '/tasks', tone: 'emerald' },
                        ]"
                        :key="item.label"
                        :href="item.href"
                        class="group rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-slate-500">{{ item.label }}</span>
                            <span class="h-2.5 w-2.5 rounded-full" :class="{
                                'bg-teal-500': item.tone === 'teal',
                                'bg-blue-500': item.tone === 'blue',
                                'bg-rose-500': item.tone === 'rose',
                                'bg-violet-500': item.tone === 'violet',
                                'bg-emerald-500': item.tone === 'emerald',
                            }"></span>
                        </div>
                        <div class="mt-3 text-3xl font-bold tracking-tight text-slate-900">{{ item.value }}</div>
                        <div class="mt-2 text-xs font-medium text-slate-400 transition group-hover:text-slate-600">مشاهده جزئیات ←</div>
                    </Link>
                </section>

                <div class="grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(300px,.8fr)]">
                    <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6">
                            <div>
                                <h3 class="font-bold text-slate-900">آخرین تسک‌ها</h3>
                                <p class="mt-1 text-xs text-slate-500">آخرین موارد قابل مشاهده شما</p>
                            </div>
                            <Link href="/tasks" class="text-sm font-semibold text-teal-700 hover:text-teal-800">مشاهده همه</Link>
                        </div>

                        <div v-if="recentTasks.length === 0" class="px-6 py-14 text-center">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-lg text-slate-400">✓</div>
                            <p class="mt-4 text-sm font-semibold text-slate-700">هنوز تسکی برای نمایش وجود ندارد.</p>
                            <p class="mt-1 text-xs text-slate-400">با ایجاد اولین تسک، فعالیت‌ها در اینجا نمایش داده می‌شوند.</p>
                        </div>

                        <div v-else class="divide-y divide-slate-100">
                            <Link v-for="task in recentTasks" :key="task.id" :href="'/tasks/' + task.id" class="block px-5 py-4 transition hover:bg-slate-50 sm:px-6">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="h-2 w-2 shrink-0 rounded-full" :class="task.status === 'completed' ? 'bg-emerald-500' : task.status === 'cancelled' ? 'bg-rose-500' : task.status === 'in_progress' ? 'bg-blue-500' : 'bg-slate-300'"></span>
                                            <div class="truncate font-semibold text-slate-800">{{ task.title }}</div>
                                        </div>
                                        <div class="mt-1.5 pr-4 text-xs text-slate-500">
                                            {{ task.project || 'بدون پروژه' }} · {{ task.assignee || 'بدون مسئول' }}
                                        </div>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-3">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClasses[task.status] || 'bg-slate-100 text-slate-600'">
                                            {{ statusLabels[task.status] || task.status }}
                                        </span>
                                        <span class="text-xs" :class="priorityClasses[task.priority] || 'text-slate-500'">{{ priorityLabels[task.priority] || task.priority }}</span>
                                        <span class="hidden text-xs text-slate-400 sm:inline">{{ formatDate(task.due_date) }}</span>
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-6">
                        <div>
                            <h3 class="font-bold text-slate-900">دسترسی سریع</h3>
                            <p class="mt-1 text-xs text-slate-500">بخش‌های پرکاربرد سیستم</p>
                        </div>
                        <div class="mt-5 space-y-3">
                            <Link v-for="action in quickActions" :key="action.href" :href="action.href" class="group flex items-center gap-3 rounded-xl border border-slate-100 p-3.5 transition hover:border-slate-200 hover:bg-slate-50">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-sm font-bold text-white">{{ action.icon }}</span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-slate-800">{{ action.label }}</span>
                                    <span class="mt-0.5 block text-xs text-slate-500">{{ action.description }}</span>
                                </span>
                                <span class="mr-auto text-slate-300 transition group-hover:text-slate-600">←</span>
                            </Link>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
