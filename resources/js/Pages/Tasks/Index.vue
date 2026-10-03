<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import JalaliDatePicker from '@/Components/JalaliDatePicker.vue';
import { computed, ref } from 'vue';

interface User {
    id: number;
    name: string;
}

interface ProjectOption {
    id: number;
    name: string;
    member_ids: number[];
}

interface DepartmentOption {
    id: number;
    name: string;
}

interface FormOptions {
    projects: ProjectOption[];
    departments: DepartmentOption[];
    users: User[];
}

interface Task {
    id: number;
    title: string;
    status: string;
    priority: string;
    start_date: string | null;
    due_date: string | null;
    assignee: User | null;
    department: DepartmentOption | null;
    project: { id: number; name: string } | null;
}

const props = defineProps<{
    tasks: Task[];
    canCreate: boolean;
    formOptions: FormOptions | null;
}>();

const search = ref('');
const createForm = useForm({
    project_id: null as number | null,
    department_id: null as number | null,
    title: '',
    description: '',
    priority: 'normal',
    assignee_id: null as number | null,
    assignment_note: '',
    start_date: '',
    due_date: '',
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

const availableAssignees = computed(() => {
    if (!props.formOptions) {
        return [];
    }

    if (createForm.project_id === null) {
        return props.formOptions.users;
    }

    const project = props.formOptions.projects.find(
        (item) => item.id === createForm.project_id,
    );

    if (!project) {
        return [];
    }

    return props.formOptions.users.filter((user) =>
        project.member_ids.includes(user.id),
    );
});

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

const resetCreateForm = (): void => {
    createForm.reset();
    createForm.priority = 'normal';
    createForm.clearErrors();
};

const submitCreate = (): void => {
    createForm.post(route('tasks.store'), {
        preserveScroll: true,
        onSuccess: () => resetCreateForm(),
    });
};

const handleProjectChange = (): void => {
    if (
        createForm.assignee_id !== null &&
        !availableAssignees.value.some(
            (user) => user.id === createForm.assignee_id,
        )
    ) {
        createForm.assignee_id = null;
    }
};
</script>

<template>
    <Head title="تسک‌ها" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900">تسک‌ها</h2>
                    <p class="mt-1 text-sm text-gray-500">مشاهده و پیگیری کارهای قابل دسترسی شما</p>
                </div>
                <div class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">
                    {{ filteredTasks.length }} تسک
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <section
                v-if="canCreate && formOptions"
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"
            >
                <div class="border-b border-gray-100 bg-gray-50/70 px-5 py-4 sm:px-6">
                    <h3 class="font-bold text-gray-900">ایجاد تسک جدید</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        اطلاعات اصلی تسک را ثبت کنید. وضعیت اولیه «انجام نشده» خواهد بود.
                    </p>
                </div>

                <form class="grid gap-4 p-5 sm:p-6 md:grid-cols-2" @submit.prevent="submitCreate">
                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">عنوان تسک</label>
                        <input v-model="createForm.title" type="text" required maxlength="255"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/10"
                            placeholder="عنوان کار را وارد کنید" />
                        <p v-if="createForm.errors.title" class="mt-1.5 text-sm text-red-600">{{ createForm.errors.title }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">پروژه</label>
                        <select v-model="createForm.project_id" @change="handleProjectChange"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">
                            <option :value="null">بدون پروژه</option>
                            <option v-for="project in formOptions.projects" :key="project.id" :value="project.id">{{ project.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">واحد مسئول</label>
                        <select v-model="createForm.department_id"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">
                            <option :value="null">بدون واحد</option>
                            <option v-for="department in formOptions.departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">مسئول تسک</label>
                        <select v-model="createForm.assignee_id"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">
                            <option :value="null">بدون مسئول</option>
                            <option v-for="user in availableAssignees" :key="user.id" :value="user.id">{{ user.name }}</option>
                        </select>
                        <p v-if="createForm.project_id !== null" class="mt-1 text-xs text-gray-500">فقط اعضای همان پروژه قابل انتخاب هستند.</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">اولویت</label>
                        <select v-model="createForm.priority"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">
                            <option value="low">کم</option><option value="normal">عادی</option><option value="high">زیاد</option><option value="urgent">فوری</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">تاریخ شروع</label>
                        <JalaliDatePicker v-model="createForm.start_date" />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">مهلت انجام</label>
                        <JalaliDatePicker v-model="createForm.due_date" />
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">توضیحات</label>
                        <textarea v-model="createForm.description" rows="4"
                            class="w-full rounded-lg border border-gray-300 bg-white p-3 text-sm shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/10"
                            placeholder="شرح کار، خروجی مورد انتظار یا اطلاعات تکمیلی" />
                    </div>

                    <div v-if="createForm.assignee_id" class="md:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">توضیح تخصیص</label>
                        <input v-model="createForm.assignment_note" type="text" maxlength="5000"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                            placeholder="در صورت نیاز، توضیحی برای مسئول تسک بنویسید" />
                    </div>

                    <div class="flex flex-wrap gap-2 border-t border-gray-100 pt-4 md:col-span-2">
                        <button type="submit" :disabled="createForm.processing"
                            class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50">
                            {{ createForm.processing ? 'در حال ذخیره...' : 'ایجاد تسک' }}
                        </button>
                        <button type="button" :disabled="createForm.processing"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-50"
                            @click="resetCreateForm">
                            پاک کردن
                        </button>
                    </div>
                </form>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="relative">
                    <label for="task-search" class="mb-1.5 block text-sm font-medium text-gray-700">جست‌وجوی تسک</label>
                    <input id="task-search" v-model="search" type="search"
                        placeholder="عنوان، پروژه، واحد یا مسئول..."
                        class="h-11 w-full rounded-lg border border-gray-300 bg-gray-50 px-4 text-sm outline-none transition focus:border-primary focus:bg-white focus:ring-2 focus:ring-primary/10" />
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-4 sm:px-5">
                    <div>
                        <h3 class="font-bold text-gray-900">فهرست تسک‌ها</h3>
                        <p class="mt-0.5 text-xs text-gray-500">مواردی که بر اساس سطح دسترسی شما قابل مشاهده هستند</p>
                    </div>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">{{ filteredTasks.length }}</span>
                </div>

                <div v-if="filteredTasks.length === 0" class="px-6 py-14 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-500">—</div>
                    <p class="mt-3 font-medium text-gray-800">{{ search ? 'تسکی با این مشخصات پیدا نشد.' : 'تسکی برای نمایش وجود ندارد.' }}</p>
                    <p v-if="search" class="mt-1 text-sm text-gray-500">عبارت جست‌وجو را تغییر دهید و دوباره تلاش کنید.</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="border-b border-gray-100 bg-gray-50/70">
                            <tr>
                                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500">عنوان</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">پروژه</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">واحد</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">مسئول</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">وضعیت</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">اولویت</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="task in filteredTasks" :key="task.id" class="transition-colors hover:bg-gray-50/80">
                                <td class="px-5 py-3.5 font-semibold text-gray-900">{{ task.title }}</td>
                                <td class="px-4 py-3.5 text-gray-600">{{ task.project?.name ?? '—' }}</td>
                                <td class="px-4 py-3.5 text-gray-600">{{ task.department?.name ?? '—' }}</td>
                                <td class="px-4 py-3.5 text-gray-600">{{ task.assignee?.name ?? 'بدون مسئول' }}</td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="{
                                            'bg-gray-100 text-gray-700': task.status === 'todo',
                                            'bg-blue-50 text-blue-700': task.status === 'in_progress',
                                            'bg-green-50 text-green-700': task.status === 'completed',
                                            'bg-red-50 text-red-700': task.status === 'cancelled',
                                        }">
                                        {{ statusLabels[task.status] ?? task.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="{
                                            'bg-gray-100 text-gray-700': task.priority === 'normal',
                                            'bg-slate-100 text-slate-700': task.priority === 'low',
                                            'bg-amber-50 text-amber-700': task.priority === 'high',
                                            'bg-red-50 text-red-700': task.priority === 'urgent',
                                        }">
                                        {{ priorityLabels[task.priority] ?? task.priority }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-left">
                                    <Link :href="route('tasks.show', task.id)"
                                        class="inline-flex rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50">
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
