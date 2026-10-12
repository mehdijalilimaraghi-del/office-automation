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

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface ProjectPagination {
    data: Project[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    previous_page_url: string | null;
    next_page_url: string | null;
    links: PaginationLink[];
}

const props = defineProps<{
    projects: ProjectPagination;
    canCreate: boolean;
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

                <Button v-if="props.canCreate" type="button" as-child>
                    <Link :href="route('projects.create')">
                        ایجاد پروژه
                    </Link>
                </Button>
            </div>
        </template>

        <div class="projects-index mx-auto w-full max-w-[1440px] px-4 py-5 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                <div
                    v-if="projects.data.length === 0"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    هنوز پروژه‌ای ثبت نشده است.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-border bg-muted/50">
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

                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="project in projects.data"
                                :key="project.id"
                                class="transition-colors hover:bg-accent/30"
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
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="{
                                            'bg-violet-50 text-violet-700': project.status === 'planning',
                                            'bg-primary/10 text-primary': project.status === 'active',
                                            'bg-emerald-50 text-emerald-700': project.status === 'completed',
                                            'bg-slate-100 text-slate-600': project.status === 'archived',
                                        }">
                                        {{ statusLabels[project.status] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="project.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'">
                                        {{ project.is_active ? 'فعال' : 'غیرفعال' }}
                                    </span>
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

                <div
                    v-if="projects.last_page > 1"
                    class="flex flex-wrap items-center justify-between gap-3 border-t p-4"
                >
                    <div class="text-sm text-muted-foreground">
                        نمایش {{ projects.from }} تا {{ projects.to }}
                        از {{ projects.total }} پروژه
                    </div>

                    <div class="flex items-center gap-1">
                        <Button
                            v-if="projects.previous_page_url"
                            variant="outline"
                            size="sm"
                            as-child
                        >
                            <Link :href="projects.previous_page_url">
                                قبلی
                            </Link>
                        </Button>

                        <template v-for="link in projects.links" :key="link.label">
                            <Button
                                v-if="link.url && link.url !== projects.previous_page_url && link.url !== projects.next_page_url"
                                :variant="link.active ? 'default' : 'outline'"
                                size="sm"
                                as-child
                            >
                                <Link :href="link.url">
                                    {{ link.label }}
                                </Link>
                            </Button>
                        </template>

                        <Button
                            v-if="projects.next_page_url"
                            variant="outline"
                            size="sm"
                            as-child
                        >
                            <Link :href="projects.next_page_url">
                                بعدی
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
