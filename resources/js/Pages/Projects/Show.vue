<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { formatPersianDate } from '@/Utils/date';
import JalaliDatePicker from '@/Components/JalaliDatePicker.vue';

interface ProjectRole {
    id: number;
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
    section_id: number | null;
    title: string;
    status: string;
    priority: string;
    assignee: {
        id: number;
        name: string;
    } | null;
}

interface ProjectSection {
    id: number;
    documents: Array<{ id: number; document: { id: number; title: string; latest_version?: { original_name: string } | null } | null }>;
    name: string;
    description: string | null;
    manager_can_create_tasks: boolean;
    department: {
        id: number;
        name: string;
        manager: { id: number; name: string } | null;
        members: Array<{ id: number; name: string }>;
    } | null;
    tasks: Task[];
}

interface Project {
    id: number;
    name: string;
    code: string;
    description: string | null;
    documents: Array<{ id: number; document: { id: number; title: string; latest_version?: { original_name: string } | null } | null }>;
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
    sections: ProjectSection[];
    is_system_default: boolean;
    department: { id: number; name: string } | null;
}

const props = defineProps<{
    project: Project;
    canManageMembers: boolean;
    canManageRoles: boolean;
    canUpdate: boolean;
    memberOptions: MemberOption[];
    canCreateTasks: boolean;
    canUploadProjectDocuments: boolean;
    managerCreatableSectionIds: number[];
    managerDocumentSectionIds: number[];
}>();

const showSectionTaskModal = ref(false);
const uploadingSectionId = ref<number | null>(null);
const selectedSection = ref<ProjectSection | null>(null);
const sectionTaskForm = useForm({
    project_id: props.project.id as number | null,
    section_id: null as number | null,
    title: '',
    description: '',
    priority: 'normal',
    start_date: null as string | null,
    due_date: null as string | null,
    delegate_to_employee: false,
    delegate_assignee_id: null as number | null,
    assignment_note: '',
});

const unsectionedTasks = computed(() => props.project.tasks.filter((task) => !task.section_id));

const sectionEmployees = computed(() => {
    const section = selectedSection.value;
    if (!section?.department) return [];
    return section.department.members.filter((member) => member.id !== section.department?.manager?.id);
});

const openSectionTaskModal = (section: ProjectSection): void => {
    selectedSection.value = section;
    sectionTaskForm.reset();
    sectionTaskForm.project_id = props.project.id;
    sectionTaskForm.section_id = section.id;
    sectionTaskForm.priority = 'normal';
    sectionTaskForm.delegate_to_employee = false;
    sectionTaskForm.delegate_assignee_id = null;
    sectionTaskForm.clearErrors();
    showSectionTaskModal.value = true;
};

const uploadProjectDocument = (event: Event): void => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    uploadingSectionId.value = 0;
    router.post(route('documents.projects.store', props.project.id), {
        title: file.name,
        document_type: 'project_reference',
        file,
    }, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            uploadingSectionId.value = null;
            input.value = '';
        },
    });
};

const uploadSectionDocument = (section: ProjectSection, event: Event): void => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    uploadingSectionId.value = section.id;
    router.post(route('documents.sections.store', section.id), {
        title: file.name,
        document_type: 'section_reference',
        file,
    }, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            uploadingSectionId.value = null;
            input.value = '';
        },
    });
};

const submitSectionTask = (): void => {
    sectionTaskForm.post(route('tasks.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showSectionTaskModal.value = false;
            selectedSection.value = null;
            sectionTaskForm.reset();
        },
    });
};

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

    return formatPersianDate(date);
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
                            required
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
                    <form class="grid gap-4 md:grid-cols-[1fr_1.5fr_auto] md:items-end" @submit.prevent="submitCreateRole">
                        <div>
                            <label for="role-name" class="mb-2 block text-sm font-medium">نام نقش</label>
                            <input
                                id="role-name"
                                v-model="roleForm.name"
                                type="text"
                                required
                                class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                :disabled="roleForm.processing"
                            />
                            <p v-if="roleForm.errors.name" class="mt-1 text-sm text-destructive">
                                {{ roleForm.errors.name }}
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

                        <Button type="submit" :disabled="roleForm.processing || !roleForm.name">
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
                                <th class="px-4 py-3 text-right font-medium">کد سیستمی</th>
                                <th class="px-4 py-3 text-right font-medium">توضیحات</th>
                                <th class="px-4 py-3 text-right font-medium">وضعیت</th>
                                <th v-if="canManageRoles" class="px-4 py-3 text-right font-medium">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="role in project.roles" :key="role.id">
                                <template v-if="editingRoleId === role.id">
                                    <td class="px-4 py-3 font-mono text-xs text-slate-500" dir="ltr">
                                        {{ role.code }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <input
                                            v-model="roleForm.name"
                                            type="text"
                                            required
                                            class="h-9 w-full min-w-36 rounded-md border bg-background px-2 text-sm"
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
                                                :disabled="roleForm.processing || !roleForm.name"
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

            <section class="rounded-xl border bg-card p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold">مدارک عمومی پروژه</h3>
                        <p class="mt-1 text-sm text-muted-foreground">فایل‌هایی که برای همه بخش‌های پروژه کاربرد دارند.</p>
                    </div>
                    <label v-if="canUploadProjectDocuments" class="inline-flex cursor-pointer items-center rounded-md border px-3 py-2 text-xs font-medium transition hover:bg-muted" :class="{ 'pointer-events-none opacity-50': uploadingSectionId === 0 }">
                        {{ uploadingSectionId === 0 ? 'در حال بارگذاری...' : 'افزودن مدرک' }}
                        <input type="file" class="sr-only" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.txt,.csv,.zip" :disabled="uploadingSectionId === 0" @change="uploadProjectDocument" />
                    </label>
                </div>
                <div v-if="!project.documents?.length" class="mt-4 rounded-lg border border-dashed p-5 text-center text-sm text-muted-foreground">
                    هنوز مدرک عمومی برای پروژه ثبت نشده است.
                </div>
                <div v-else class="mt-4 flex flex-wrap gap-2">
                    <a v-for="link in project.documents" :key="link.id" :href="link.document ? route('documents.download', link.document.id) : '#'" class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-xs transition hover:bg-muted">
                        <span aria-hidden="true">📎</span>
                        {{ link.document?.title ?? 'مدرک' }}
                    </a>
                </div>
            </section>

            <section class="space-y-4 rounded-xl border bg-card p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold">بخش‌های پروژه</h3>
                        <p class="mt-1 text-sm text-muted-foreground">تسک‌ها بر اساس واحد مسئول و بخش کاری پروژه مرتب شده‌اند.</p>
                    </div>
                    <span class="rounded-full bg-muted px-3 py-1 text-xs text-muted-foreground">{{ project.sections.length }} بخش</span>
                </div>

                <div v-if="project.sections.length === 0" class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                    برای این پروژه هنوز بخشی تعریف نشده است. بخش‌ها هنگام ایجاد پروژه ثبت می‌شوند.
                </div>

                <article v-for="section in project.sections" :key="section.id" class="overflow-hidden rounded-xl border">
                    <div class="flex flex-wrap items-start justify-between gap-3 bg-muted/30 p-4">
                        <div class="space-y-1">
                            <h4 class="font-semibold">{{ section.name }}</h4>
                            <p class="text-sm text-muted-foreground">واحد مسئول: {{ section.department?.name ?? 'واحد تعیین نشده' }}</p>
                            <p v-if="section.description" class="whitespace-pre-line text-sm">{{ section.description }}</p>
                            <p class="text-xs text-muted-foreground">
                                مدیر واحد: {{ section.department?.manager?.name ?? 'مدیر واحد تعیین نشده' }}
                                <span class="mx-1">·</span>
                                {{ section.manager_can_create_tasks ? 'تعریف تسک توسط مدیر واحد فعال است' : 'تعریف تسک توسط مدیر واحد غیرفعال است' }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <label v-if="canUploadProjectDocuments || managerDocumentSectionIds.includes(section.id)" class="inline-flex cursor-pointer items-center rounded-md border px-3 py-2 text-xs font-medium transition hover:bg-muted" :class="{ 'pointer-events-none opacity-50': uploadingSectionId === section.id }">
                                {{ uploadingSectionId === section.id ? 'در حال بارگذاری...' : 'افزودن مدرک بخش' }}
                                <input type="file" class="sr-only" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.txt,.csv,.zip" :disabled="uploadingSectionId === section.id" @change="uploadSectionDocument(section, $event)" />
                            </label>
                            <Button v-if="canCreateTasks || managerCreatableSectionIds.includes(section.id)" type="button" size="sm" @click="openSectionTaskModal(section)>
                                + ایجاد تسک
                            </Button>
                        </div>
                    </div>

                    <div v-if="section.documents?.length" class="border-t px-4 py-3">
                        <p class="mb-2 text-xs font-semibold text-muted-foreground">مدارک اختصاصی بخش</p>
                        <div class="flex flex-wrap gap-2">
                            <a v-for="link in section.documents" :key="link.id" :href="link.document ? route('documents.download', link.document.id) : '#'" class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-xs transition hover:bg-muted">
                                <span aria-hidden="true">📎</span>
                                {{ link.document?.title ?? 'مدرک' }}
                            </a>
                        </div>
                    </div>

                    <div v-if="section.tasks.length === 0" class="p-4 text-sm text-muted-foreground">
                        هنوز تسکی برای این بخش ثبت نشده است.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-y bg-background">
                                <tr>
                                    <th class="px-4 py-3 text-right font-medium">عنوان تسک</th>
                                    <th class="px-4 py-3 text-right font-medium">وضعیت</th>
                                    <th class="px-4 py-3 text-right font-medium">اولویت</th>
                                    <th class="px-4 py-3 text-right font-medium">مسئول فعلی</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="task in section.tasks" :key="task.id">
                                    <td class="px-4 py-3 font-medium">{{ task.title }}</td>
                                    <td class="px-4 py-3">{{ taskStatusLabels[task.status] ?? task.status }}</td>
                                    <td class="px-4 py-3">{{ priorityLabels[task.priority] ?? task.priority }}</td>
                                    <td class="px-4 py-3">{{ task.assignee?.name ?? section.department?.manager?.name ?? 'مدیر واحد' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>

            <section class="rounded-lg border bg-card">
                <div class="border-b p-5">
                    <h3 class="font-semibold">وظایف بدون بخش</h3>
                </div>

                <div v-if="unsectionedTasks.length === 0" class="p-6 text-sm text-muted-foreground">
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
                            <tr v-for="task in unsectionedTasks" :key="task.id">
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

        <div v-if="showSectionTaskModal && selectedSection" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:p-6" @keydown.esc="showSectionTaskModal = false">
            <div class="my-4 w-full max-w-2xl overflow-hidden rounded-xl border bg-card shadow-xl sm:my-8" role="dialog" aria-modal="true" aria-labelledby="section-task-modal-title">
                <div class="flex items-start justify-between gap-4 border-b p-5">
                    <div>
                        <h3 id="section-task-modal-title" class="text-lg font-semibold">ایجاد تسک جدید</h3>
                        <p class="mt-1 text-sm text-muted-foreground">تسک ابتدا به مدیر واحد ارجاع می‌شود.</p>
                    </div>
                    <Button type="button" variant="outline" size="sm" @click="showSectionTaskModal = false">بستن</Button>
                </div>
                <form class="space-y-4 p-5" @submit.prevent="submitSectionTask">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1">
                            <label class="text-sm font-medium">پروژه</label>
                            <div class="rounded-md border bg-muted/30 px-3 py-2 text-sm">{{ project.name }}</div>
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium">بخش</label>
                            <div class="rounded-md border bg-muted/30 px-3 py-2 text-sm">{{ selectedSection.name }}</div>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <label for="section-task-title" class="text-sm font-medium">عنوان تسک</label>
                        <input id="section-task-title" v-model="sectionTaskForm.title" required maxlength="255" class="w-full rounded-md border bg-background px-3 py-2 text-sm" />
                        <p v-if="sectionTaskForm.errors.title" class="text-xs text-destructive">{{ sectionTaskForm.errors.title }}</p>
                    </div>
                    <div class="space-y-1">
                        <label for="section-task-description" class="text-sm font-medium">شرح و خروجی مورد انتظار</label>
                        <textarea id="section-task-description" v-model="sectionTaskForm.description" rows="3" class="w-full rounded-md border bg-background px-3 py-2 text-sm" />
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1">
                            <label for="section-task-priority" class="text-sm font-medium">اولویت</label>
                            <select id="section-task-priority" v-model="sectionTaskForm.priority" class="w-full rounded-md border bg-background px-3 py-2 text-sm">
                                <option value="low">کم</option>
                                <option value="normal">عادی</option>
                                <option value="high">زیاد</option>
                                <option value="urgent">فوری</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium">مدیر اولیه</label>
                            <div class="rounded-md border bg-muted/30 px-3 py-2 text-sm">{{ selectedSection.department?.manager?.name ?? 'مدیر واحد تعیین نشده' }}</div>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1">
                            <label for="section-task-start" class="text-sm font-medium">تاریخ شروع</label>
                            <JalaliDatePicker v-model="sectionTaskForm.start_date" placeholder="انتخاب تاریخ شروع" />
                        </div>
                        <div class="space-y-1">
                            <label for="section-task-due" class="text-sm font-medium">مهلت تحویل</label>
                            <JalaliDatePicker v-model="sectionTaskForm.due_date" placeholder="انتخاب مهلت تحویل" />
                            <p v-if="sectionTaskForm.errors.due_date" class="text-xs text-destructive">{{ sectionTaskForm.errors.due_date }}</p>
                        </div>
                    </div>
                    <label class="flex items-start gap-3 rounded-lg border p-3">
                        <input v-model="sectionTaskForm.delegate_to_employee" type="checkbox" class="mt-1 h-4 w-4 rounded border-input" />
                        <span>
                            <span class="block text-sm font-medium">ارجاع هم‌زمان از مدیر به کارمند</span>
                            <span class="mt-1 block text-xs text-muted-foreground">در صورت فعال‌کردن، ابتدا ارجاع به مدیر ثبت می‌شود و سپس تسک به کارمند انتخاب‌شده واگذار می‌شود.</span>
                        </span>
                    </label>
                    <div v-if="sectionTaskForm.delegate_to_employee" class="space-y-1">
                        <label for="section-task-employee" class="text-sm font-medium">کارمند مقصد ارجاع</label>
                        <select id="section-task-employee" v-model="sectionTaskForm.delegate_assignee_id" required class="w-full rounded-md border bg-background px-3 py-2 text-sm">
                            <option :value="null">انتخاب کارمند</option>
                            <option v-for="employee in sectionEmployees" :key="employee.id" :value="employee.id">{{ employee.name }}</option>
                        </select>
                        <p v-if="sectionTaskForm.errors.delegate_assignee_id" class="text-xs text-destructive">{{ sectionTaskForm.errors.delegate_assignee_id }}</p>
                    </div>
                    <div class="space-y-1">
                        <label for="section-task-note" class="text-sm font-medium">توضیح ارجاع (اختیاری)</label>
                        <textarea id="section-task-note" v-model="sectionTaskForm.assignment_note" rows="2" class="w-full rounded-md border bg-background px-3 py-2 text-sm" />
                    </div>
                    <p v-if="sectionTaskForm.errors.task" class="text-sm text-destructive">{{ sectionTaskForm.errors.task }}</p>
                    <div class="flex justify-end gap-2 border-t pt-4">
                        <Button type="button" variant="outline" @click="showSectionTaskModal = false">انصراف</Button>
                        <Button type="submit" :disabled="sectionTaskForm.processing">{{ sectionTaskForm.processing ? 'در حال ثبت...' : 'ثبت تسک' }}</Button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
