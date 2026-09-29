<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';

interface Project {
    id: number;
    name: string;
    code: string;
    description: string | null;
    status: 'planning' | 'active' | 'completed' | 'archived';
    start_date: string | null;
    end_date: string | null;
    is_active: boolean;
    manager: {
        id: number;
        name: string;
    } | null;
    creator: {
        id: number;
        name: string;
    } | null;
}

defineProps<{
    projects: Project[];
}>();

const statusLabels: Record<Project['status'], string> = {
    planning: 'در حال برنامه‌ریزی',
    active: 'فعال',
    completed: 'تکمیل‌شده',
    archived: 'بایگانی‌شده',
};
</script>

<template>
    <Head title="پروژه‌ها" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold">پروژه‌ها</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        مدیریت پروژه‌های سازمان
                    </p>
                </div>

                <Button type="button" as-child>
                    <Link :href="route('projects.create')">
                        ایجاد پروژه
                    </Link>
                </Button>
            </div>
        </template>

        <div class="mx-auto max-w-7xl">
            <div class="overflow-hidden rounded-lg border bg-card">
                <div
                    v-if="projects.length === 0"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    هنوز پروژه‌ای ثبت نشده است.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40">
                            <tr>
                                <th class="px-4 py-3 text-right font-medium">
                                    نام پروژه
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    کد
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    مدیر پروژه
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    وضعیت
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    فعال
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    عملیات
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y">
                            <tr
                                v-for="project in projects"
                                :key="project.id"
                                class="transition-colors hover:bg-muted/30"
                            >
                                <td class="px-4 py-3 font-medium">
                                    {{ project.name }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ project.code }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ project.manager?.name ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ statusLabels[project.status] }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ project.is_active ? 'بله' : 'خیر' }}
                                </td>
                                <td class="px-4 py-3 text-left">
                                    <Button variant="outline" size="sm" as-child>
                                        <Link
                                            :href="
                                                route(
                                                    'projects.show',
                                                    project.id,
                                                )
                                            "
                                        >
                                            مشاهده
                                        </Link>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
