<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Task {
    id: number;
    title: string;
    status: string;
    priority: string;
    start_date: string | null;
    due_date: string | null;
    assignee: { id: number; name: string } | null;
    department: { id: number; name: string } | null;
    project: { id: number; name: string } | null;
}

const props = defineProps<{ tasks: Task[] }>();
const search = ref('');

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

const filteredTasks = computed(() => {
    const value = search.value.trim().toLocaleLowerCase('fa');

    if (!value) {
        return props.tasks;
    }

    return props.tasks.filter((task) =>
        [
            task.title,
            task.project?.name ?? '',
            task.department?.name ?? '',
            task.assignee?.name ?? '',
        ].some((item) => item.toLocaleLowerCase('fa').includes(value)),
    );
});
</script>

<template>
    <Head title="تسک‌ها" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold">تسک‌ها</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    مشاهده و پیگیری کارهای قابل دسترسی شما
                </p>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-6">
            <section class="rounded-lg border bg-card p-5">
                <label for="task-search" class="mb-2 block text-sm font-medium">
                    جست‌وجوی تسک
                </label>
                <input
                    id="task-search"
                    v-model="search"
                    type="search"
                    placeholder="عنوان، پروژه، واحد یا مسئول..."
                    class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                />
            </section>

            <section class="overflow-hidden rounded-lg border bg-card">
                <div v-if="filteredTasks.length === 0" class="p-10 text-center text-sm text-muted-foreground">
                    {{ search ? 'تسکی با این مشخصات پیدا نشد.' : 'تسکی برای نمایش وجود ندارد.' }}
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40">
                            <tr>
                                <th class="px-4 py-3 text-right font-medium">عنوان</th>
                                <th class="px-4 py-3 text-right font-medium">پروژه</th>
                                <th class="px-4 py-3 text-right font-medium">واحد</th>
                                <th class="px-4 py-3 text-right font-medium">مسئول</th>
                                <th class="px-4 py-3 text-right font-medium">وضعیت</th>
                                <th class="px-4 py-3 text-right font-medium">اولویت</th>
                                <th class="px-4 py-3 text-left font-medium">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="task in filteredTasks" :key="task.id" class="transition-colors hover:bg-muted/30">
                                <td class="px-4 py-3 font-medium">{{ task.title }}</td>
                                <td class="px-4 py-3">{{ task.project?.name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ task.department?.name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ task.assignee?.name ?? 'بدون مسئول' }}</td>
                                <td class="px-4 py-3">{{ statusLabels[task.status] ?? task.status }}</td>
                                <td class="px-4 py-3">{{ priorityLabels[task.priority] ?? task.priority }}</td>
                                <td class="px-4 py-3 text-left">
                                    <Link :href="route('tasks.show', task.id)" class="rounded-md border px-3 py-1.5 text-xs font-medium hover:bg-muted">
                                        مشاهده
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
