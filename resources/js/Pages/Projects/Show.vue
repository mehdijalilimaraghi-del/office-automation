<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatPersianDate } from '@/Utils/date';
import JalaliDatePicker from '@/Components/JalaliDatePicker.vue';

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

interface SectionDepartmentOption { id: number; name: string; manager_id: number; manager: { id: number; name: string; status: string } | null; }

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
    tasks: Task[];
    sections: ProjectSection[];
    is_system_default: boolean;
    department: { id: number; name: string } | null;
}

const props = defineProps<{
    project: Project;
    sectionDepartmentOptions: SectionDepartmentOption[];
    canUpdate: boolean;
    canDeleteProject: boolean;
    canCreateTasks: boolean;
    canUploadProjectDocuments: boolean;
    canDeleteDocuments: boolean;
    currentUserId: number;
    managerCreatableSectionIds: number[];
    managerDocumentSectionIds: number[];
}>();


const showSectionEditor = ref(false);
const editingSectionId = ref<number | null>(null);
const sectionForm = useForm({ name: '', description: '', department_id: '', manager_can_create_tasks: true });
const openCreateSection = (): void => {
    editingSectionId.value = null;
    sectionForm.reset();
    sectionForm.manager_can_create_tasks = true;
    sectionForm.clearErrors();
    showSectionEditor.value = true;
};
const openEditSection = (section: ProjectSection): void => {
    editingSectionId.value = section.id;
    sectionForm.name = section.name;
    sectionForm.description = section.description ?? '';
    sectionForm.department_id = section.department?.id.toString() ?? '';
    sectionForm.manager_can_create_tasks = section.manager_can_create_tasks;
    sectionForm.clearErrors();
    showSectionEditor.value = true;
};
const submitSection = (): void => {
    const options = { preserveScroll: true, onSuccess: () => { showSectionEditor.value = false; sectionForm.reset(); } };
    if (editingSectionId.value !== null) sectionForm.put(route('projects.sections.update', [props.project.id, editingSectionId.value]), options);
    else sectionForm.post(route('projects.sections.store', props.project.id), options);
};
const deleteSection = (section: ProjectSection): void => {
    if (!window.confirm('آیا از حذف این بخش مطمئن هستید؟ بخش دارای دستورکار یا سند قابل حذف نیست.')) return;
    router.delete(route('projects.sections.destroy', [props.project.id, section.id]), { preserveScroll: true });
};

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
            <div dir="ltr" class="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 pb-4">
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
                        <Link :href="route('projects.edit', project.id)">ویرایش پروژه</Link>
                    </Button>
                    <Button v-if="props.canDeleteProject" type="button" size="sm" variant="destructive" @click="deleteProject">حذف پروژه</Button>
                </div>
            </div>
        </template>

        <main class="mx-auto w-full max-w-[1600px] space-y-6 px-4 pb-8 pt-2 sm:px-6 lg:px-8">
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

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5">
                <article v-for="item in [
                    { label: 'بخش‌های کاری', value: projectTaskStats.sections, note: 'واحدهای مسئول', tone: 'border-r-sky-500', icon: 'sections' },
                    { label: 'کل دستورکارها', value: projectTaskStats.total, note: 'ثبت‌شده در پروژه', tone: 'border-r-slate-400', icon: 'tasks' },
                    { label: 'در حال انجام', value: projectTaskStats.inProgress, note: 'در جریان اجرا', tone: 'border-r-blue-500', icon: 'progress' },
                    { label: 'تکمیل‌شده', value: projectTaskStats.completed, note: 'به پایان رسیده', tone: 'border-r-emerald-500', icon: 'completed' },
                    { label: 'عقب‌افتاده', value: projectTaskStats.overdue, note: 'نیازمند پیگیری', tone: 'border-r-rose-500', icon: 'overdue' },
                ]" :key="item.label" class="rounded-xl border border-r-4 bg-card p-4 shadow-sm transition-shadow hover:shadow-md" :class="item.tone">
                    <div class="flex items-start justify-between gap-3">
                        <div><p class="text-sm text-muted-foreground">{{ item.label }}</p><p class="mt-3 text-3xl font-bold tracking-tight tabular-nums">{{ item.value }}</p><p class="mt-1 text-xs text-muted-foreground">{{ item.note }}</p></div>
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-muted text-foreground" aria-hidden="true">
                            <svg v-if="item.icon === 'sections'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                            <svg v-else-if="item.icon === 'tasks'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><path d="M9 6h11M9 12h11M9 18h11"/><path d="m3 6 1 1 2-2M3 12l1 1 2-2M3 18l1 1 2-2"/></svg>
                            <svg v-else-if="item.icon === 'progress'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><path d="M3 17l6-6 4 4 8-9"/><path d="M15 6h6v6"/></svg>
                            <svg v-else-if="item.icon === 'completed'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16.5 9"/></svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16.5v.5"/></svg>
                        </span>
                    </div>
                </article>
            </section>

            <section class="rounded-2xl border bg-card px-5 py-4 shadow-sm">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <div><p class="text-xs font-semibold text-primary">نمای کلی</p><h3 class="mt-1 font-bold">پیشرفت کل پروژه</h3></div>
                    <div class="text-left"><p class="text-2xl font-bold tabular-nums">{{ projectTaskStats.progress }}٪</p><p class="text-xs text-muted-foreground">{{ projectTaskStats.completed }} از {{ projectTaskStats.total }} دستورکار تکمیل شده</p></div>
                </div>
                <div class="h-2.5 overflow-hidden rounded-full bg-muted" role="progressbar" :aria-valuenow="projectTaskStats.progress" aria-valuemin="0" aria-valuemax="100" :aria-label="'پیشرفت کل پروژه ' + projectTaskStats.progress + ' درصد'">
                    <div class="h-full rounded-full transition-all" :class="projectTaskStats.progress === 100 ? 'bg-emerald-500' : 'bg-primary'" :style="{ width: projectTaskStats.progress + '%' }"></div>
                </div>
            </section>

            <section class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.65fr)_minmax(18rem,.85fr)]">
                <div class="min-w-0 space-y-4">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold text-primary">برنامه اجرا</p>
                            <h3 class="mt-1 text-xl font-bold">بخش‌های پروژه</h3>
                            <p class="mt-1 text-sm text-muted-foreground">مسئولیت و پیشرفت هر بخش را ببینید؛ برای جزئیات و ثبت دستورکار، بخش را باز کنید.</p>
                        </div>
                        <div class="flex items-center gap-2"><span class="rounded-full bg-muted px-3 py-1.5 text-xs font-semibold">{{ project.sections.length }} بخش تعریف‌شده</span><Button v-if="canUpdate" type="button" size="sm" @click="openCreateSection">＋ افزودن بخش</Button></div>
                    </div>

                    <div v-if="project.sections.length === 0" class="rounded-2xl border border-dashed bg-card px-6 py-12 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-muted text-muted-foreground" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><path d="M17.5 14v7M14 17.5h7"/></svg></div>
                        <h4 class="mt-4 font-semibold">هنوز بخشی تعریف نشده است</h4>
                        <p class="mt-1 text-sm text-muted-foreground">برای ثبت دستورکار در این پروژه، ابتدا باید بخش و واحد مسئول مشخص شود.</p>
                        <Button v-if="props.canUpdate" class="mt-4" @click="openCreateSection">＋ افزودن بخش</Button>
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
                        <div v-if="canUpdate" class="flex justify-end gap-2 border-b px-4 py-3"><Button type="button" size="sm" variant="outline" @click="openEditSection(section)">ویرایش بخش</Button><Button type="button" size="sm" variant="destructive" @click="deleteSection(section)">حذف بخش</Button></div>
                        <div class="border-b bg-muted/20 p-4 sm:p-5">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="flex min-w-0 items-start gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border bg-background text-lg font-bold text-primary">{{ section.name.slice(0, 1) }}</div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold text-muted-foreground">شرح بخش</p>
                                        <p v-if="section.description" class="mt-1 whitespace-pre-line text-sm leading-6 text-foreground">{{ section.description }}</p>
                                        <p v-else class="mt-1 text-sm text-muted-foreground">برای این بخش توضیحی ثبت نشده است.</p>
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

                        <section class="border-t">
                            <div class="flex flex-wrap items-center justify-between gap-3 bg-background px-4 py-3 sm:px-5">
                                <div><h5 class="font-semibold">دستورکارهای این بخش</h5><p class="mt-1 text-xs text-muted-foreground">وضعیت، مسئول و مهلت هر دستورکار</p></div>
                                <span class="rounded-full border bg-card px-3 py-1 text-xs font-semibold tabular-nums">{{ section.tasks.length }} مورد</span>
                            </div>
                            <div v-if="section.tasks.length === 0" class="m-4 rounded-xl border border-dashed px-4 py-6 text-center text-sm text-muted-foreground">برای این بخش هنوز دستورکاری ثبت نشده است.</div>
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
                        </section>
                        </div>
                    </details>
                </div>

                <aside class="space-y-6">
                    <section class="rounded-2xl border bg-card p-5 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <div><p class="text-xs font-semibold text-primary">اطلاعات پایه</p><h3 class="mt-1 font-bold">شناسنامه پروژه</h3></div>
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/><path d="M8 7h8M8 11h6"/></svg></span>
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

                    
                </aside>
            </section>
        </main>


        <div v-if="showSectionEditor" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:p-6" @keydown.esc="showSectionEditor = false">
            <div class="my-4 w-full max-w-xl rounded-xl border bg-card shadow-xl sm:my-8" role="dialog" aria-modal="true" aria-labelledby="section-editor-title">
                <div class="flex items-start justify-between gap-4 border-b p-5"><div><h3 id="section-editor-title" class="text-lg font-semibold">{{ editingSectionId ? 'ویرایش بخش پروژه' : 'افزودن بخش پروژه' }}</h3><p class="mt-1 text-sm text-muted-foreground">واحد مسئول، مالک اجرایی این بخش و دستورکارهای آن است.</p></div><Button type="button" variant="outline" size="sm" @click="showSectionEditor = false">بستن</Button></div>
                <form class="space-y-4 p-5" @submit.prevent="submitSection">
                    <div class="space-y-1"><label for="section-name" class="text-sm font-medium">عنوان بخش</label><input id="section-name" v-model="sectionForm.name" required maxlength="150" class="w-full rounded-md border bg-background px-3 py-2 text-sm" /><p v-if="sectionForm.errors.name" class="text-xs text-destructive">{{ sectionForm.errors.name }}</p></div>
                    <div class="space-y-1"><label for="section-department" class="text-sm font-medium">واحد مسئول</label><select id="section-department" v-model="sectionForm.department_id" required class="w-full rounded-md border bg-background px-3 py-2 text-sm"><option value="">انتخاب واحد</option><option v-for="department in sectionDepartmentOptions" :key="department.id" :value="department.id.toString()">{{ department.name }} — {{ department.manager?.name ?? 'بدون مدیر' }}</option></select><p v-if="sectionForm.errors.department_id" class="text-xs text-destructive">{{ sectionForm.errors.department_id }}</p></div>
                    <div class="space-y-1"><label for="section-description" class="text-sm font-medium">توضیحات</label><textarea id="section-description" v-model="sectionForm.description" rows="3" class="w-full rounded-md border bg-background px-3 py-2 text-sm" /><p v-if="sectionForm.errors.description" class="text-xs text-destructive">{{ sectionForm.errors.description }}</p></div>
                    <label class="flex items-center gap-2 text-sm"><input v-model="sectionForm.manager_can_create_tasks" type="checkbox" class="rounded border-input" /> مدیر واحد بتواند در این بخش دستورکار ایجاد کند</label>
                    <div class="flex justify-end gap-2 border-t pt-4"><Button type="button" variant="outline" @click="showSectionEditor = false">انصراف</Button><Button type="submit" :disabled="sectionForm.processing">{{ sectionForm.processing ? 'در حال ذخیره...' : 'ذخیره بخش' }}</Button></div>
                </form>
            </div>
        </div>

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
