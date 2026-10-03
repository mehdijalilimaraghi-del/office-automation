<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

interface ProjectRole {
    id: number;
    department_id: number | null;
    name: string;
    code: string;
    description: string | null;
    is_active: boolean;
}

interface MemberOption {
    id: number;
    name: string;
}

interface ProjectMember {
    id: number;
    user: {
        id: number;
        name: string;
    } | null;
    project_role: ProjectRole | null;
}

interface Task {
    id: number;
    title: string;
    status: string;
    priority: string;
    assignee: {
        id: number;
        name: string;
    } | null;
}

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
    roles: ProjectRole[];
    members: ProjectMember[];
    tasks: Task[];
    is_system_default: boolean;
    department: { id: number; name: string } | null;
}

const props = defineProps<{
    project: Project;
    canManageMembers: boolean;
    canManageRoles: boolean;
    canUpdate: boolean;
    memberOptions: MemberOption[];
}>();

const memberRoleSelections = reactive<Record<number, string>>(
    Object.fromEntries(
        props.project.members.map((member) => [
            member.id,
            member.project_role?.id?.toString() ?? '',
        ]),
    ),
);

const memberRoleForm = useForm({
    project_role_id: '',
});

const deletingMemberId = ref<number | null>(null);

const addMemberForm = useForm({
    user_id: '',
    project_role_id: '',
});

const availableMembers = computed(() => props.memberOptions);

const activeProjectRoles = computed(() =>
    props.project.roles.filter((role) => role.is_active),
);

const roleForm = useForm({
    name: '',
    code: '',
    description: '',
    is_active: true,
});

const editingRoleId = ref<number | null>(null);
const roleActionForm = useForm({
    role: '',
});

const startEditRole = (role: ProjectRole): void => {
    editingRoleId.value = role.id;
    roleForm.name = role.name;
    roleForm.code = role.code;
    roleForm.description = role.description ?? '';
    roleForm.is_active = role.is_active;
};

const cancelEditRole = (): void => {
    editingRoleId.value = null;
    roleForm.reset();
};

const submitCreateRole = (): void => {
    roleForm.post(route('projects.roles.store', props.project.id), {
        preserveScroll: true,
        onSuccess: () => roleForm.reset(),
    });
};

const submitEditRole = (role: ProjectRole): void => {
    roleForm.put(
        route('projects.roles.update', [props.project.id, role.id]),
        {
            preserveScroll: true,
            onSuccess: () => {
                editingRoleId.value = null;
                roleForm.reset();
            },
        },
    );
};

const toggleRoleStatus = (role: ProjectRole): void => {
    roleActionForm.patch(
        route('projects.roles.toggle-status', [props.project.id, role.id]),
        {
            preserveScroll: true,
        },
    );
};

const deleteRole = (role: ProjectRole): void => {
    if (!window.confirm('آیا از حذف این نقش پروژه مطمئن هستید؟')) {
        return;
    }

    roleActionForm.delete(
        route('projects.roles.destroy', [props.project.id, role.id]),
        {
            preserveScroll: true,
        },
    );
};

const submitMemberRole = (member: ProjectMember): void => {
    memberRoleForm.project_role_id =
        memberRoleSelections[member.id] ?? '';

    memberRoleForm.put(
        route('projects.members.update-role', [
            props.project.id,
            member.id,
        ]),
        {
            preserveScroll: true,
        },
    );
};

const removeMember = (member: ProjectMember): void => {
    if (!window.confirm('آیا از حذف این عضو از پروژه مطمئن هستید؟')) {
        return;
    }

    deletingMemberId.value = member.id;

    useForm({}).delete(
        route('projects.members.destroy', [
            props.project.id,
            member.id,
        ]),
        {
            preserveScroll: true,
            onFinish: () => {
                deletingMemberId.value = null;
            },
        },
    );
};

const submitAddMember = (): void => {
    addMemberForm.post(route('projects.members.store', props.project.id), {
        preserveScroll: true,
        onSuccess: () => addMemberForm.reset(),
    });
};

const statusLabels: Record<Project['status'], string> = {
    planning: 'در حال برنامه‌ریزی',
    active: 'فعال',
    completed: 'تکمیل‌شده',
    archived: 'بایگانی‌شده',
};

const taskStatusLabels: Record<string, string> = {
    pending: 'در انتظار',
    in_progress: 'در حال انجام',
    completed: 'تکمیل‌شده',
    cancelled: 'لغوشده',
};

const priorityLabels: Record<string, string> = {
    low: 'کم',
    medium: 'متوسط',
    high: 'زیاد',
    urgent: 'فوری',
};

const formatDate = (date: string | null): string => {
    if (!date) {
        return '—';
    }

    return date;
};
</script>

<template>
    <Head :title="project.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold">{{ project.name }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        جزئیات و وضعیت پروژه
                    </p>
                </div>

                <div class="flex gap-2">
                    <Button variant="outline" as-child>
                        <Link :href="route('projects.index')">
                            بازگشت به پروژه‌ها
                        </Link>
                    </Button>

                    <Button v-if="props.canUpdate" variant="outline" as-child>
                        <Link :href="route('projects.edit', project.id)">
                            ویرایش پروژه
                        </Link>
                    </Button>

                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-6">
            <section class="rounded-lg border bg-card p-6">
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <p class="text-sm text-muted-foreground">کد پروژه</p>
                        <p class="mt-1 font-medium">{{ project.code }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-muted-foreground">واحد</p>
                        <p class="mt-1 font-medium">
                            {{ project.department?.name ?? 'بدون واحد' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-muted-foreground">مدیر پروژه</p>
                        <p class="mt-1 font-medium">
                            {{ project.manager?.name ?? '—' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-muted-foreground">وضعیت</p>
                        <p class="mt-1 font-medium">
                            {{ statusLabels[project.status] }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-muted-foreground">فعال</p>
                        <p class="mt-1 font-medium">
                            {{ project.is_active ? 'بله' : 'خیر' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-muted-foreground">تاریخ شروع</p>
                        <p class="mt-1 font-medium">
                            {{ formatDate(project.start_date) }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-muted-foreground">تاریخ پایان</p>
                        <p class="mt-1 font-medium">
                            {{ formatDate(project.end_date) }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-muted-foreground">ایجادکننده</p>
                        <p class="mt-1 font-medium">
                            {{ project.creator?.name ?? '—' }}
                        </p>
                    </div>
                </div>

                <div v-if="project.description" class="mt-6 border-t pt-6">
                    <p class="text-sm text-muted-foreground">توضیحات</p>
                    <p class="mt-2 whitespace-pre-line text-sm">
                        {{ project.description }}
                    </p>
                </div>
            </section>

            <section v-if="canManageMembers && memberOptions.length > 0" class="rounded-lg border bg-card">
                <div class="border-b p-5">
                    <h3 class="font-semibold">افزودن عضو</h3>
                </div>

                <form class="grid gap-4 p-5 md:grid-cols-[1fr_1fr_auto] md:items-end" @submit.prevent="submitAddMember">
                    <div>
                        <label for="member-user" class="mb-2 block text-sm font-medium">کاربر</label>
                        <select
                            id="member-user"
                            v-model="addMemberForm.user_id"
                            class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                            :disabled="addMemberForm.processing"
                        >
                            <option value="">انتخاب کاربر</option>
                            <option
                                v-for="member in availableMembers"
                                :key="member.id"
                                :value="member.id.toString()"
                            >
                                {{ member.name }}
                            </option>
                        </select>
                        <p v-if="addMemberForm.errors.user_id" class="mt-1 text-sm text-destructive">
                            {{ addMemberForm.errors.user_id }}
                        </p>
                    </div>

                    <div>
                        <label for="member-role" class="mb-2 block text-sm font-medium">نقش پروژه</label>
                        <select
                            id="member-role"
                            v-model="addMemberForm.project_role_id"
                            class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                            :disabled="addMemberForm.processing"
                        >
                            <option value="">بدون نقش</option>
                            <option
                                v-for="role in activeProjectRoles"
                                :key="role.id"
                                :value="role.id.toString()"
                            >
                                {{ role.name }}
                            </option>
                        </select>
                        <p v-if="addMemberForm.errors.project_role_id" class="mt-1 text-sm text-destructive">
                            {{ addMemberForm.errors.project_role_id }}
                        </p>
                    </div>

                    <Button type="submit" :disabled="addMemberForm.processing || !addMemberForm.user_id">
                        افزودن عضو
                    </Button>
                </form>
            </section>

            <section class="rounded-lg border bg-card">
                <div class="border-b p-5">
                    <h3 class="font-semibold">اعضای پروژه</h3>
                </div>

                <div v-if="project.members.length === 0" class="p-6 text-sm text-muted-foreground">
                    عضوی برای این پروژه ثبت نشده است.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40">
                            <tr>
                                <th class="px-4 py-3 text-right font-medium">کاربر</th>
                                <th class="px-4 py-3 text-right font-medium">نقش پروژه</th>
                                <th v-if="canManageMembers" class="px-4 py-3 text-right font-medium">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="member in project.members" :key="member.id">
                                <td class="px-4 py-3 font-medium">
                                    {{ member.user?.name ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <select
                                        v-if="canManageMembers"
                                        v-model="memberRoleSelections[member.id]"
                                        class="h-9 min-w-40 rounded-md border bg-background px-2 text-sm"
                                        :disabled="memberRoleForm.processing"
                                    >
                                        <option value="">بدون نقش</option>
                                        <option
                                            v-for="role in activeProjectRoles"
                                            :key="role.id"
                                            :value="role.id.toString()"
                                        >
                                            {{ role.name }}
                                        </option>
                                    </select>
                                    <span v-else>
                                        {{ member.project_role?.name ?? 'بدون نقش' }}
                                    </span>
                                </td>
                                <td v-if="canManageMembers" class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <Button
                                            type="button"
                                            size="sm"
                                            :disabled="memberRoleForm.processing"
                                            @click="submitMemberRole(member)"
                                        >
                                            ذخیره نقش
                                        </Button>
                                        <Button
                                            v-if="member.user?.id !== project.manager?.id"
                                            type="button"
                                            size="sm"
                                            variant="destructive"
                                            :disabled="deletingMemberId === member.id"
                                            @click="removeMember(member)"
                                        >
                                            حذف
                                        </Button>
                                    </div>
                                    <p v-if="memberRoleForm.errors.project_role_id" class="mt-1 text-sm text-destructive">
                                        {{ memberRoleForm.errors.project_role_id }}
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-lg border bg-card">
                <div class="border-b p-5">
                    <h3 class="font-semibold">نقش‌های پروژه</h3>
                </div>

                <div v-if="canManageRoles" class="border-b p-5">
                    <form class="grid gap-4 md:grid-cols-[1fr_1fr_1.5fr_auto] md:items-end" @submit.prevent="submitCreateRole">
                        <div>
                            <label for="role-name" class="mb-2 block text-sm font-medium">نام نقش</label>
                            <input
                                id="role-name"
                                v-model="roleForm.name"
                                type="text"
                                class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                :disabled="roleForm.processing"
                            />
                            <p v-if="roleForm.errors.name" class="mt-1 text-sm text-destructive">
                                {{ roleForm.errors.name }}
                            </p>
                        </div>

                        <div>
                            <label for="role-code" class="mb-2 block text-sm font-medium">کد نقش</label>
                            <input
                                id="role-code"
                                v-model="roleForm.code"
                                type="text"
                                class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                :disabled="roleForm.processing"
                            />
                            <p v-if="roleForm.errors.code" class="mt-1 text-sm text-destructive">
                                {{ roleForm.errors.code }}
                            </p>
                        </div>

                        <div>
                            <label for="role-description" class="mb-2 block text-sm font-medium">توضیحات</label>
                            <input
                                id="role-description"
                                v-model="roleForm.description"
                                type="text"
                                class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                :disabled="roleForm.processing"
                            />
                            <p v-if="roleForm.errors.description" class="mt-1 text-sm text-destructive">
                                {{ roleForm.errors.description }}
                            </p>
                        </div>

                        <Button type="submit" :disabled="roleForm.processing || !roleForm.name || !roleForm.code">
                            افزودن نقش
                        </Button>
                    </form>
                </div>

                <div v-if="project.roles.length === 0" class="p-6 text-sm text-muted-foreground">
                    هنوز نقشی برای این پروژه تعریف نشده است.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40">
                            <tr>
                                <th class="px-4 py-3 text-right font-medium">نام نقش</th>
                                <th class="px-4 py-3 text-right font-medium">کد</th>
                                <th class="px-4 py-3 text-right font-medium">توضیحات</th>
                                <th class="px-4 py-3 text-right font-medium">وضعیت</th>
                                <th v-if="canManageRoles" class="px-4 py-3 text-right font-medium">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="role in project.roles" :key="role.id">
                                <template v-if="editingRoleId === role.id">
                                    <td class="px-4 py-3">
                                        <input
                                            v-model="roleForm.name"
                                            type="text"
                                            class="h-9 w-full min-w-36 rounded-md border bg-background px-2 text-sm"
                                            :disabled="roleForm.processing"
                                        />
                                    </td>
                                    <td class="px-4 py-3">
                                        <input
                                            v-model="roleForm.code"
                                            type="text"
                                            class="h-9 w-full min-w-28 rounded-md border bg-background px-2 text-sm"
                                            :disabled="roleForm.processing"
                                        />
                                    </td>
                                    <td class="px-4 py-3">
                                        <input
                                            v-model="roleForm.description"
                                            type="text"
                                            class="h-9 w-full min-w-48 rounded-md border bg-background px-2 text-sm"
                                            :disabled="roleForm.processing"
                                        />
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ role.is_active ? 'فعال' : 'غیرفعال' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                size="sm"
                                                :disabled="roleForm.processing || !roleForm.name || !roleForm.code"
                                                @click="submitEditRole(role)"
                                            >
                                                ذخیره
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                :disabled="roleForm.processing"
                                                @click="cancelEditRole"
                                            >
                                                انصراف
                                            </Button>
                                        </div>
                                        <p v-if="roleForm.errors.name" class="mt-1 text-sm text-destructive">
                                            {{ roleForm.errors.name }}
                                        </p>
                                        <p v-else-if="roleForm.errors.code" class="mt-1 text-sm text-destructive">
                                            {{ roleForm.errors.code }}
                                        </p>
                                        <p v-else-if="roleForm.errors.description" class="mt-1 text-sm text-destructive">
                                            {{ roleForm.errors.description }}
                                        </p>
                                    </td>
                                </template>

                                <template v-else>
                                    <td class="px-4 py-3 font-medium">{{ role.name }}</td>
                                    <td class="px-4 py-3 text-muted-foreground">{{ role.code }}</td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ role.description || '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ role.is_active ? 'فعال' : 'غیرفعال' }}
                                    </td>
                                    <td v-if="canManageRoles" class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                :disabled="roleActionForm.processing || roleForm.processing"
                                                @click="startEditRole(role)"
                                            >
                                                ویرایش
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                :disabled="roleActionForm.processing"
                                                @click="toggleRoleStatus(role)"
                                            >
                                                {{ role.is_active ? 'غیرفعال کردن' : 'فعال کردن' }}
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="destructive"
                                                :disabled="roleActionForm.processing"
                                                @click="deleteRole(role)"
                                            >
                                                حذف
                                            </Button>
                                        </div>
                                        <p v-if="roleActionForm.errors.role" class="mt-1 text-sm text-destructive">
                                            {{ roleActionForm.errors.role }}
                                        </p>
                                    </td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-lg border bg-card">
                <div class="border-b p-5">
                    <h3 class="font-semibold">وظایف پروژه</h3>
                </div>

                <div v-if="project.tasks.length === 0" class="p-6 text-sm text-muted-foreground">
                    هنوز وظیفه‌ای برای این پروژه ثبت نشده است.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40">
                            <tr>
                                <th class="px-4 py-3 text-right font-medium">عنوان</th>
                                <th class="px-4 py-3 text-right font-medium">وضعیت</th>
                                <th class="px-4 py-3 text-right font-medium">اولویت</th>
                                <th class="px-4 py-3 text-right font-medium">مسئول</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="task in project.tasks" :key="task.id">
                                <td class="px-4 py-3 font-medium">{{ task.title }}</td>
                                <td class="px-4 py-3">
                                    {{ taskStatusLabels[task.status] ?? task.status }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ priorityLabels[task.priority] ?? task.priority }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ task.assignee?.name ?? 'بدون مسئول' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
