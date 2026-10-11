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
    due_date?: string | null;
    assignee: {
        id: number;
        name: string;
    } | null;
}

interface ProjectSection {
    id: number;
    documents: Array<{ id: number; document: { id: number; title: string; created_by: number; latest_version?: { original_name: string } | null } | null }>;
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
    documents: Array<{ id: number; document: { id: number; title: string; created_by: number; latest_version?: { original_name: string } | null } | null }>;
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
    canDeleteProject: boolean;
    memberOptions: MemberOption[];
    canCreateTasks: boolean;
    canUploadProjectDocuments: boolean;
    canDeleteDocuments: boolean;
    currentUserId: number;
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
const sectionProgress = (section: ProjectSection): number => section.tasks.length ? Math.round(section.tasks.filter((task) => task.status === 'completed').length * 100 / section.tasks.length) : 0;

const projectTaskStats = computed(() => { const tasks = props.project.tasks; const completed = tasks.filter((task) => task.status === 'completed').length; return { total: tasks.length, completed, inProgress: tasks.filter((task) => task.status === 'in_progress').length, todo: tasks.filter((task) => task.status === 'todo').length, sections: props.project.sections.length, progress: tasks.length ? Math.round(completed * 100 / tasks.length) : 0, overdue: tasks.filter((task) => task.due_date && new Date(task.due_date) < new Date() && !['completed', 'cancelled'].includes(task.status)).length }; });

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

const deleteDocument = (documentId: number): void => {
    if (!window.confirm('آیا از حذف این فایل مطمئن هستید؟')) return;

    router.delete(route('documents.destroy', documentId), {
        preserveScroll: true,
    });
};

const deleteProject = (): void => {
    if (!window.confirm('آیا از حذف این پروژه و اطلاعات وابسته به آن مطمئن هستید؟ این عملیات قابل بازگشت نیست.')) return;

    router.delete(route('projects.destroy', props.project.id));
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

const sectionTaskError = (key: string): string | undefined => {
    return (sectionTaskForm.errors as Record<string, string | undefined>)[key];
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
            <div dir="ltr" class="flex flex-wrap items-center justify-between gap-3">
                <Link :href="route('projects.index')" dir="rtl" class="inline-flex h-9 items-center gap-2 rounded-lg border bg-background px-3 text-sm text-muted-foreground transition hover:bg-muted hover:text-foreground" aria-label="بازگشت به پروژه‌ها">
                    <span aria-hidden="true">←</span>
                    <span>بازگشت</span>
                </Link>
                <div dir="rtl" class="flex min-w-0 flex-1 items-center justify-center gap-2 text-center sm:justify-start sm:text-right">
                    <span class="shrink-0 rounded-md bg-primary/10 px-2 py-1 text-xs font-semibold text-primary">پروژه</span>
                    <h2 class="truncate text-lg font-bold tracking-tight">{{ project.name }}</h2>
                    <span class="hidden rounded-md border px-2 py-1 font-mono text-[11px] text-muted-foreground sm:inline-flex" dir="ltr">{{ project.code }}</span>
                </div>
                <div dir="rtl" class="flex shrink-0 flex-wrap items-center gap-2">
                    <Button v-if="props.canUpdate" variant="outline" size="sm" as-child>
                        <Link :href="route('projects.edit', project.id)">ویرایش</Link>
                    </Button>
                    <Button v-if="props.canDeleteProject" type="button" size="sm" variant="destructive" @click="deleteProject">حذف پروژه</Button>
                </div>
            </div>
        </template>

        <main class="mx-auto w-full max-w-[1600px] space-y-6 px-4 pb-8 sm:px-6 lg:px-8">
            <section v-if="project.description || project.is_system_default" class="flex flex-wrap items-start justify-between gap-4 rounded-xl border bg-card px-5 py-4 shadow-sm">
                <p v-if="project.description" class="min-w-0 flex-1 whitespace-pre-line text-sm leading-7 text-muted-foreground">{{ project.description }}</p>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    <span class="rounded-full border px-3 py-1 text-xs font-semibold" :class="{
                        'border-blue-200 bg-blue-50 text-blue-700': project.status === 'planning',
                        'border-emerald-200 bg-emerald-50 text-emerald-700': project.status === 'active',
                        'border-violet-200 bg-violet-50 text-violet-700': project.status === 'completed',
                        'border-slate-200 bg-slate-100 text-slate-700': project.status === 'archived',
                    }">{{ statusLabels[project.status] }}</span>
                    <span v-if="project.is_system_default" class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">پروژه عمومی سازمان</span>
                </div>
            </section>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                <article v-for="item in [
                    { label: 'بخش‌های کاری', value: projectTaskStats.sections, note: 'واحدهای مسئول', tone: 'border-r-sky-500', icon: '▦' },
                    { label: 'کل دستورکارها', value: projectTaskStats.total, note: 'دستورکارهای ثبت‌شده', tone: 'border-r-slate-400', icon: '≡' },
                    { label: 'در حال انجام', value: projectTaskStats.inProgress, note: 'در جریان اجرا', tone: 'border-r-blue-500', icon: '↗' },
                    { label: 'تکمیل‌شده', value: projectTaskStats.completed, note: 'خروجی‌های نهایی', tone: 'border-r-emerald-500', icon: '✓' },
                    { label: 'عقب‌افتاده', value: projectTaskStats.overdue, note: 'نیازمند پیگیری', tone: 'border-r-rose-500', icon: '!' },
                    { label: 'پیشرفت پروژه', value: projectTaskStats.progress + '٪', note: projectTaskStats.completed + ' از ' + projectTaskStats.total + ' دستورکار تکمیل‌شده', tone: 'border-r-primary', icon: '◔' },
                ]" :key="item.label" class="rounded-xl border border-r-4 bg-card p-4 shadow-sm" :class="item.tone">
                    <div class="flex items-start justify-between gap-3">
                        <div><p class="text-sm text-muted-foreground">{{ item.label }}</p><p class="mt-3 text-3xl font-bold tracking-tight tabular-nums">{{ item.value }}</p><p class="mt-1 text-xs text-muted-foreground">{{ item.note }}</p></div>
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-muted text-lg font-semibold text-foreground">{{ item.icon }}</span>
                    </div>
                </article>
            </section>

            <section class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.65fr)_minmax(18rem,.85fr)]">
                <div class="min-w-0 space-y-4">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold text-primary">برنامه اجرا</p>
                            <h3 class="mt-1 text-xl font-bold">بخش‌های پروژه</h3>
                            <p class="mt-1 text-sm text-muted-foreground">مسئولیت و پیشرفت هر بخش را ببینید؛ برای جزئیات و ثبت دستورکار، بخش را باز کنید.</p>
                        </div>
                        <span class="rounded-full bg-muted px-3 py-1.5 text-xs font-semibold">{{ project.sections.length }} بخش تعریف‌شده</span>
                    </div>

                    <div v-if="project.sections.length === 0" class="rounded-2xl border border-dashed bg-card px-6 py-12 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-muted text-xl">▦</div>
                        <h4 class="mt-4 font-semibold">هنوز بخشی تعریف نشده است</h4>
                        <p class="mt-1 text-sm text-muted-foreground">برای ثبت دستورکار در این پروژه، ابتدا باید بخش و واحد مسئول مشخص شود.</p>
                        <Button v-if="props.canUpdate" class="mt-4" as-child><Link :href="route('projects.edit', project.id)">تعریف بخش‌ها</Link></Button>
                    </div>

                    <details v-for="(section, sectionIndex) in project.sections" :key="section.id" class="group overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm transition-all open:border-primary/30 open:shadow-md">
                        <summary class="cursor-pointer list-none bg-gradient-to-l from-muted/30 to-card p-4 transition-colors hover:from-primary/5 hover:to-card focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary sm:p-5 [&::-webkit-details-marker]:hidden">
                            <div class="flex flex-wrap items-center gap-4">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-primary/15 bg-primary/10 text-sm font-bold text-primary">{{ String(sectionIndex + 1).padStart(2, '0') }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="font-bold">{{ section.name }}</h4>
                                        <span class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-muted-foreground">{{ section.tasks.length }} دستورکار</span>
                                    </div>
                                    <p class="mt-1 text-sm text-muted-foreground">{{ section.department?.name ?? 'واحد مسئول تعیین نشده' }}<span v-if="section.department?.manager?.name"> · مدیر: {{ section.department.manager.name }}</span></p>
                                </div>
                                <div class="hidden w-32 shrink-0 sm:block">
                                    <div class="mb-1 flex justify-between text-[11px] text-muted-foreground"><span>پیشرفت</span><span>{{ sectionProgress(section) }}٪</span></div>
                                    <div class="h-1.5 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full bg-primary" :style="{ width: sectionProgress(section) + '%' }"></div></div>
                                </div>
                                <span class="inline-flex shrink-0 items-center gap-2 rounded-lg border bg-background px-3 py-2 text-xs font-semibold text-muted-foreground transition-colors group-hover:border-primary/30 group-hover:text-foreground group-open:bg-muted">
                                    <span class="hidden group-open:inline">بستن جزئیات</span>
                                    <span class="group-open:hidden">مشاهده جزئیات</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 transition-transform duration-200 group-open:rotate-180" aria-hidden="true">
                                        <path d="m6 9 6 6 6-6" />
                                    </svg>
                                </span>
                            </div>
                        </summary>
                        <div class="border-t bg-muted/10">
                        <div class="border-b bg-muted/20 p-4 sm:p-5">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="flex min-w-0 items-start gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border bg-background text-lg font-bold text-primary">{{ section.name.slice(0, 1) }}</div>
                                    <div class="min-w-0">
                                        <h4 class="font-bold">{{ section.name }}</h4>
                                        <p class="mt-1 text-sm text-muted-foreground">{{ section.department?.name ?? 'واحد مسئول تعیین نشده' }}</p>
                                        <p v-if="section.description" class="mt-2 whitespace-pre-line text-sm leading-6 text-muted-foreground">{{ section.description }}</p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <label v-if="canUploadProjectDocuments || managerDocumentSectionIds.includes(section.id)" class="inline-flex cursor-pointer items-center rounded-lg border bg-background px-3 py-2 text-xs font-semibold transition hover:bg-muted" :class="{ 'pointer-events-none opacity-50': uploadingSectionId === section.id }">
                                        {{ uploadingSectionId === section.id ? 'در حال بارگذاری...' : '＋ مدرک' }}
                                        <input type="file" class="sr-only" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.txt,.csv,.zip" :disabled="uploadingSectionId === section.id" @change="uploadSectionDocument(section, $event)" />
                                    </label>
                                    <Button v-if="canCreateTasks || managerCreatableSectionIds.includes(section.id)" type="button" size="sm" @click="openSectionTaskModal(section)">＋ ایجاد دستورکار</Button>
                                </div>
                            </div>
                            <div class="mt-5 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                                <div>
                                    <div class="mb-2 flex items-center justify-between gap-3 text-xs"><span class="text-muted-foreground">پیشرفت بخش</span><span class="font-bold tabular-nums">{{ sectionProgress(section) }}٪</span></div>
                                    <div class="h-2 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full transition-all" :class="sectionProgress(section) === 100 ? 'bg-emerald-500' : 'bg-primary'" :style="{ width: sectionProgress(section) + '%' }"></div></div>
                                </div>
                                <div class="flex gap-4 text-xs text-muted-foreground">
                                    <span><strong class="text-foreground">{{ section.tasks.filter((task) => task.status === 'completed').length }}</strong> تکمیل</span>
                                    <span><strong class="text-foreground">{{ section.tasks.filter((task) => task.status === 'in_progress').length }}</strong> در جریان</span>
                                    <span><strong class="text-foreground">{{ section.tasks.length }}</strong> کل</span>
                                </div>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-muted-foreground">
                                <span>مدیر واحد: <strong class="text-foreground">{{ section.department?.manager?.name ?? 'تعیین نشده' }}</strong></span>
                                <span>{{ section.manager_can_create_tasks ? 'ایجاد دستورکار توسط مدیر واحد فعال است' : 'ایجاد دستورکار توسط مدیر واحد غیرفعال است' }}</span>
                            </div>
                        </div>

                        <div v-if="section.documents?.length" class="border-b px-4 py-3 sm:px-5">
                            <p class="mb-2 text-xs font-semibold text-muted-foreground">مدارک بخش</p>
                            <div class="flex flex-wrap gap-2">
                                <div v-for="link in section.documents" :key="link.id" class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-xs">
                                    <a :href="link.document ? route('documents.download', link.document.id) : '#'" class="hover:text-primary">{{ link.document?.title ?? 'مدرک' }}</a>
                                    <button v-if="canDeleteDocuments && link.document?.created_by === currentUserId" type="button" class="text-destructive hover:opacity-70" @click="link.document && deleteDocument(link.document.id)">حذف</button>
                                </div>
                            </div>
                        </div>

                        <div v-if="section.tasks.length === 0" class="p-5 text-sm text-muted-foreground">برای این بخش هنوز دستورکاری ثبت نشده است.</div>
                        <div v-else class="divide-y">
                            <Link v-for="task in section.tasks" :key="task.id" :href="route('tasks.show', task.id)" class="flex flex-col gap-3 px-4 py-4 transition hover:bg-muted/30 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold">{{ task.title }}</p>
                                    <p class="mt-1 text-xs text-muted-foreground">مسئول: {{ task.assignee?.name ?? section.department?.manager?.name ?? 'مدیر واحد' }}<span v-if="task.due_date"> · مهلت: {{ formatDate(task.due_date) }}</span></p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="{
                                        'bg-slate-100 text-slate-700': task.status === 'todo' || task.status === 'pending',
                                        'bg-blue-50 text-blue-700': task.status === 'in_progress',
                                        'bg-emerald-50 text-emerald-700': task.status === 'completed',
                                        'bg-rose-50 text-rose-700': task.status === 'cancelled',
                                    }">{{ taskStatusLabels[task.status] ?? task.status }}</span>
                                    <span class="text-xs font-semibold" :class="priorityLabels[task.priority] === 'فوری' ? 'text-rose-600' : task.priority === 'high' ? 'text-amber-600' : 'text-muted-foreground'">{{ priorityLabels[task.priority] ?? task.priority }}</span>
                                    <span aria-hidden="true" class="text-muted-foreground">←</span>
                                </div>
                            </Link>
                        </div>
                        </div>
                    </details>
                </div>

                <aside class="space-y-6">
                    <section class="rounded-2xl border bg-card p-5 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <div><p class="text-xs font-semibold text-primary">اطلاعات پایه</p><h3 class="mt-1 font-bold">شناسنامه پروژه</h3></div>
                            <span class="text-lg text-muted-foreground">◈</span>
                        </div>
                        <dl class="mt-5 space-y-4">
                            <div class="flex items-start justify-between gap-3"><dt class="text-sm text-muted-foreground">کد پروژه</dt><dd class="font-mono text-sm font-semibold" dir="ltr">{{ project.code }}</dd></div>
                            <div class="flex items-start justify-between gap-3"><dt class="text-sm text-muted-foreground">مدیر پروژه</dt><dd class="text-left text-sm font-medium">{{ project.manager?.name ?? 'تعیین نشده' }}</dd></div>
                            <div class="flex items-start justify-between gap-3"><dt class="text-sm text-muted-foreground">واحد اصلی</dt><dd class="text-left text-sm font-medium">{{ project.department?.name ?? 'چندواحدی / عمومی' }}</dd></div>
                            <div class="flex items-start justify-between gap-3"><dt class="text-sm text-muted-foreground">ایجادکننده</dt><dd class="text-left text-sm font-medium">{{ project.creator?.name ?? '—' }}</dd></div>
                            <div class="flex items-start justify-between gap-3"><dt class="text-sm text-muted-foreground">تاریخ شروع</dt><dd class="text-sm font-medium">{{ formatDate(project.start_date) }}</dd></div>
                            <div class="flex items-start justify-between gap-3"><dt class="text-sm text-muted-foreground">تاریخ پایان</dt><dd class="text-sm font-medium">{{ formatDate(project.end_date) }}</dd></div>
                            <div class="flex items-start justify-between gap-3"><dt class="text-sm text-muted-foreground">وضعیت رکورد</dt><dd class="text-sm font-medium">{{ project.is_active ? 'فعال' : 'غیرفعال' }}</dd></div>
                        </dl>
                    </section>

                    <section class="rounded-2xl border bg-card p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div><p class="text-xs font-semibold text-primary">مستندات</p><h3 class="mt-1 font-bold">مدارک عمومی پروژه</h3><p class="mt-1 text-xs leading-5 text-muted-foreground">اسناد مشترک مورد استفاده همه بخش‌ها</p></div>
                            <label v-if="canUploadProjectDocuments" class="inline-flex cursor-pointer items-center rounded-lg border px-3 py-2 text-xs font-semibold hover:bg-muted" :class="{ 'pointer-events-none opacity-50': uploadingSectionId === 0 }">
                                {{ uploadingSectionId === 0 ? 'در حال بارگذاری' : '＋ افزودن' }}
                                <input type="file" class="sr-only" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.txt,.csv,.zip" :disabled="uploadingSectionId === 0" @change="uploadProjectDocument" />
                            </label>
                        </div>
                        <div v-if="!project.documents?.length" class="mt-4 rounded-xl border border-dashed p-5 text-center text-xs leading-5 text-muted-foreground">هنوز مدرک عمومی ثبت نشده است.</div>
                        <div v-else class="mt-4 space-y-2">
                            <div v-for="link in project.documents" :key="link.id" class="flex items-center justify-between gap-2 rounded-xl border p-3">
                                <a :href="link.document ? route('documents.download', link.document.id) : '#'" class="min-w-0 truncate text-sm font-medium hover:text-primary">{{ link.document?.title ?? 'مدرک' }}</a>
                                <button v-if="canDeleteDocuments && link.document?.created_by === currentUserId" type="button" class="shrink-0 text-xs text-destructive hover:underline" @click="link.document && deleteDocument(link.document.id)">حذف</button>
                            </div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-2xl border bg-card shadow-sm">
                        <div class="border-b p-5">
                            <div class="flex items-center justify-between gap-3"><div><p class="text-xs font-semibold text-primary">همکاری</p><h3 class="mt-1 font-bold">اعضای پروژه</h3></div><span class="rounded-full bg-muted px-2.5 py-1 text-xs font-semibold">{{ project.members.length }} نفر</span></div>
                        </div>
                        <form v-if="canManageMembers && memberOptions.length > 0" class="space-y-3 border-b p-5" @submit.prevent="submitAddMember">
                            <label for="member-user" class="block text-sm font-medium">افزودن عضو جدید</label>
                            <div class="flex gap-2">
                                <select id="member-user" v-model="addMemberForm.user_id" required class="h-10 min-w-0 flex-1 rounded-lg border bg-background px-3 text-sm" :disabled="addMemberForm.processing">
                                    <option value="">انتخاب کاربر</option>
                                    <option v-for="member in availableMembers" :key="member.id" :value="member.id.toString()">{{ member.name }}</option>
                                </select>
                                <Button type="submit" :disabled="addMemberForm.processing || !addMemberForm.user_id">افزودن</Button>
                            </div>
                            <p v-if="addMemberForm.errors.user_id" class="text-xs text-destructive">{{ addMemberForm.errors.user_id }}</p>
                        </form>
                        <div v-if="project.members.length === 0" class="p-5 text-sm text-muted-foreground">عضوی برای این پروژه ثبت نشده است.</div>
                        <div v-else class="divide-y">
                            <div v-for="member in project.members" :key="member.id" class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-bold">{{ (member.user?.name ?? '؟').slice(0, 1) }}</div>
                                    <div class="min-w-0"><p class="truncate text-sm font-semibold">{{ member.user?.name ?? 'کاربر حذف‌شده' }}</p><p class="mt-0.5 text-xs text-muted-foreground">{{ member.user?.id === project.manager?.id ? 'مدیر پروژه' : 'عضو پروژه' }}</p></div>
                                </div>
                                <Button v-if="canManageMembers && member.user?.id !== project.manager?.id" type="button" size="sm" variant="ghost" class="text-destructive hover:text-destructive" :disabled="deletingMemberId === member.id" @click="removeMember(member)">حذف</Button>
                            </div>
                        </div>
                    </section>

                </aside>
            </section>
        </main>

        <div v-if="showSectionTaskModal && selectedSection" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:p-6" @keydown.esc="showSectionTaskModal = false">
            <div class="my-4 w-full max-w-2xl overflow-hidden rounded-xl border bg-card shadow-xl sm:my-8" role="dialog" aria-modal="true" aria-labelledby="section-task-modal-title">
                <div class="flex items-start justify-between gap-4 border-b p-5">
                    <div>
                        <h3 id="section-task-modal-title" class="text-lg font-semibold">ایجاد دستورکار جدید</h3>
                        <p class="mt-1 text-sm text-muted-foreground">دستورکار ابتدا به مدیر واحد ارجاع می‌شود.</p>
                    </div>
                    <Button type="button" variant="outline" size="sm" @click="showSectionTaskModal = false">بستن</Button>
                </div>
                <form class="space-y-4 p-5" @submit.prevent="submitSectionTask">
                    <div class="space-y-1">
                        <label class="text-sm font-medium">بخش پروژه</label>
                        <div class="rounded-md border bg-muted/30 px-3 py-2 text-sm">{{ selectedSection.name }}</div>
                    </div>
                    <div class="space-y-1">
                        <label for="section-task-title" class="text-sm font-medium">عنوان دستورکار</label>
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
                            <span class="mt-1 block text-xs text-muted-foreground">در صورت فعال‌کردن، ابتدا ارجاع به مدیر ثبت می‌شود و سپس دستورکار به کارمند انتخاب‌شده واگذار می‌شود.</span>
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
                    <p v-if="sectionTaskError('task')" class="text-sm text-destructive">{{ sectionTaskError('task') }}</p>
                    <div class="flex justify-end gap-2 border-t pt-4">
                        <Button type="button" variant="outline" @click="showSectionTaskModal = false">انصراف</Button>
                        <Button type="submit" :disabled="sectionTaskForm.processing">{{ sectionTaskForm.processing ? 'در حال ثبت...' : 'ثبت دستورکار' }}</Button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
