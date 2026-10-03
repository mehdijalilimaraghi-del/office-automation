<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
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
            <div>
                <h2 class="text-xl font-semibold">تسک‌ها</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    مشاهده و پیگیری کارهای قابل دسترسی شما
                </p>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-6">
            <section
                v-if="canCreate && formOptions"
                class="rounded-lg border bg-card p-5"
            >
                <div class="mb-5">
                    <h3 class="text-lg font-semibold">ایجاد تسک جدید</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        اطلاعات اصلی تسک را ثبت کنید. وضعیت اولیه به‌صورت «انجام نشده» ثبت می‌شود.
                    </p>
                </div>

                <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submitCreate">
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium">عنوان تسک</label>
                        <input
                            v-model="createForm.title"
                            type="text"
                            required
                            maxlength="255"
                            class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                            placeholder="عنوان کار را وارد کنید"
                        />
                        <p v-if="createForm.errors.title" class="mt-1 text-sm text-red-600">
                            {{ createForm.errors.title }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium">پروژه</label>
                        <select
                            v-model="createForm.project_id"
                            class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                            @change="handleProjectChange"
                        >
                            <option :value="null">بدون پروژه</option>
                            <option
                                v-for="project in formOptions.projects"
                                :key="project.id"
                                :value="project.id"
                            >
                                {{ project.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium">واحد مسئول</label>
                        <select
                            v-model="createForm.department_id"
                            class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                        >
                            <option :value="null">بدون واحد</option>
                            <option
                                v-for="department in formOptions.departments"
                                :key="department.id"
                                :value="department.id"
                            >
                                {{ department.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium">مسئول تسک</label>
                        <select
                            v-model="createForm.assignee_id"
                            class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                        >
                            <option :value="null">بدون مسئول</option>
                            <option
                                v-for="user in availableAssignees"
                                :key="user.id"
                                :value="user.id"
                            >
                                {{ user.name }}
                            </option>
                        </select>
                        <p v-if="createForm.project_id !== null" class="mt-1 text-xs text-muted-foreground">
                            فقط اعضای همان پروژه قابل انتخاب هستند.
                        </p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium">اولویت</label>
                        <select
                            v-model="createForm.priority"
                            class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="low">کم</option>
                            <option value="normal">عادی</option>
                            <option value="high">زیاد</option>
                            <option value="urgent">فوری</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium">تاریخ شروع</label>
                        <input
                            v-model="createForm.start_date"
                            type="date"
                            class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                        />
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium">مهلت انجام</label>
                        <input
                            v-model="createForm.due_date"
                            type="date"
                            class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium">توضیحات</label>
                        <textarea
                            v-model="createForm.description"
                            rows="4"
                            class="w-full rounded-md border bg-background p-3 text-sm"
                            placeholder="شرح کار، خروجی مورد انتظار یا اطلاعات تکمیلی"
                        />
                    </div>

                    <div v-if="createForm.assignee_id" class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium">توضیح تخصیص</label>
                        <input
                            v-model="createForm.assignment_note"
                            type="text"
                            maxlength="5000"
                            class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                            placeholder="در صورت نیاز، توضیحی برای مسئول تسک بنویسید"
                        />
                    </div>

                    <div class="flex gap-3 md:col-span-2">
                        <button
                            type="submit"
                            :disabled="createForm.processing"
                            class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
                        >
                            {{ createForm.processing ? 'در حال ذخیره...' : 'ایجاد تسک' }}
                        </button>
                        <button
                            type="button"
                            :disabled="createForm.processing"
                            class="rounded-md border px-4 py-2 text-sm font-medium hover:bg-muted disabled:opacity-50"
                            @click="resetCreateForm"
                        >
                            پاک کردن
                        </button>
                    </div>

                </form>
            </section>

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
                <div
                    v-if="filteredTasks.length === 0"
                    class="p-10 text-center text-sm text-muted-foreground"
                >
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
                            <tr
                                v-for="task in filteredTasks"
                                :key="task.id"
                                class="transition-colors hover:bg-muted/30"
                            >
                                <td class="px-4 py-3 font-medium">{{ task.title }}</td>
                                <td class="px-4 py-3">{{ task.project?.name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ task.department?.name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ task.assignee?.name ?? 'بدون مسئول' }}</td>
                                <td class="px-4 py-3">{{ statusLabels[task.status] ?? task.status }}</td>
                                <td class="px-4 py-3">{{ priorityLabels[task.priority] ?? task.priority }}</td>
                                <td class="px-4 py-3 text-left">
                                    <Link
                                        :href="route('tasks.show', task.id)"
                                        class="rounded-md border px-3 py-1.5 text-xs font-medium hover:bg-muted"
                                    >
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
