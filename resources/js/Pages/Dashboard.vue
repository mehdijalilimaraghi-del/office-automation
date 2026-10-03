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

defineProps<{
    stats: DashboardStats;
    recentTasks: DashboardTask[];
}>();

const statusLabels: Record<string, string> = {
    todo: 'در انتظار انجام',
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

const formatDate = (value: string | null) => {
    if (!value) return '—';
    const [year, month, day] = value.split('-');
    return year + '/' + month + '/' + day;
};
</script>

<template>
    <Head title="داشبورد" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">داشبورد</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <Link v-for="item in [
                        { label: 'تسک‌های من', value: stats.my_tasks, href: '/tasks' },
                        { label: 'تسک‌های امروز', value: stats.today_tasks, href: '/tasks' },
                        { label: 'تسک‌های عقب‌افتاده', value: stats.overdue_tasks, href: '/tasks' },
                        { label: 'پروژه‌های فعال', value: stats.active_projects, href: '/projects' },
                        { label: 'تسک‌های تکمیل‌شده', value: stats.completed_tasks, href: '/tasks' },
                    ]" :key="item.label" :href="item.href"
                        class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 transition hover:ring-gray-300">
                        <div class="text-sm text-gray-500">{{ item.label }}</div>
                        <div class="mt-2 text-3xl font-bold text-gray-900">{{ item.value }}</div>
                    </Link>
                </div>

                <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                        <div>
                            <h3 class="font-semibold text-gray-900">آخرین تسک‌ها</h3>
                            <p class="mt-1 text-sm text-gray-500">آخرین فعالیت‌های قابل مشاهده شما</p>
                        </div>
                        <Link href="/tasks" class="text-sm font-medium text-gray-700 hover:text-gray-900">
                            مشاهده همه
                        </Link>
                    </div>

                    <div v-if="recentTasks.length === 0" class="px-5 py-10 text-center text-sm text-gray-500">
                        هنوز تسکی برای نمایش وجود ندارد.
                    </div>

                    <div v-else class="divide-y divide-gray-100">
                        <Link v-for="task in recentTasks" :key="task.id"
                            :href="'/tasks/' + task.id"
                            class="block px-5 py-4 transition hover:bg-gray-50">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <div class="truncate font-medium text-gray-900">{{ task.title }}</div>
                                    <div class="mt-1 text-sm text-gray-500">
                                        {{ task.project || 'بدون پروژه' }}
                                        <span class="mx-1">•</span>
                                        {{ task.assignee || 'بدون مسئول' }}
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-3 text-sm">
                                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-gray-700">
                                        {{ statusLabels[task.status] || task.status }}
                                    </span>
                                    <span class="text-gray-500">{{ priorityLabels[task.priority] || task.priority }}</span>
                                    <span class="text-gray-500">{{ formatDate(task.due_date) }}</span>
                                </div>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
