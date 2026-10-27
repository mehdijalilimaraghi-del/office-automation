<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import JalaliDatePicker from '@/Components/JalaliDatePicker.vue';
import DepartmentTreeSelect from '@/Components/DepartmentTreeSelect.vue';
import CustomFieldsForm from '@/Components/CustomFieldsForm.vue';
import { computed, ref } from 'vue';

const showCreateModal = ref(false);

const isFiltering = ref(false);

interface User {
    id: number;
    name: string;
}

interface SectionOption {
    id: number;
    name: string;
    department_id: number | null;
    manager_id: number | null;
    manager_name: string | null;
    manager_can_create_tasks: boolean;
    member_ids: number[];
}

interface ProjectOption {
    id: number;
    name: string;
    member_ids: number[];
    sections: SectionOption[];
}

interface DepartmentOption {
    id: number;
    name: string;
    parent_id?: number | null;
    icon?: string | null;
}
interface ExternalOrganizationOption { id: number; name: string; }
interface ExternalDepartmentOption { id: number; external_organization_id: number; parent_id?: number | null; name: string; }
interface ExternalContactOption { id: number; external_organization_id: number; external_department_id: number | null; name: string; position: string | null; mobile: string | null; phone: string | null; extension: string | null; }

interface FormOptions {
    projects: ProjectOption[];
    departments: DepartmentOption[];
    users: User[];
    external_organizations: ExternalOrganizationOption[];
    external_departments: ExternalDepartmentOption[];
    external_contacts: ExternalContactOption[];
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface TaskPaginator {
    data: Task[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface TaskFilters {
    search?: string;
    status?: string;
    status_group?: 'pending';
    priority?: string;
    project_id?: number | string;
    department_id?: number | string;
    scope?: 'personal' | 'unit';
    due?: 'today' | 'overdue';
    review?: '1';
    completed_period?: 'month';
    assignee_id?: number | string;
    due_from?: string;
    due_to?: string;
}

interface Task {
    id: number;
    title: string;
    status: string;
    sub_status: string | null;
    priority: string;
    start_date: string | null;
    due_date: string | null;
    assignee: User | null;
    department: DepartmentOption | null;
    project: { id: number; name: string } | null;
}

const props = defineProps<{
    tasks: TaskPaginator;
    filters: TaskFilters;
    canCreate: boolean;
    canFilterUnit: boolean;
    formOptions: FormOptions | null;
    filterOptions: { projects: Array<{ id: number; name: string }>; departments: DepartmentOption[]; users: User[] };
    customFields: Array<{
        id: number; key: string; label: string;
        type: 'text' | 'textarea' | 'number' | 'date' | 'boolean' | 'select';
        is_required: boolean; options: string[] | null;
    }>;
}>();

const search = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status_group === 'pending' ? '__pending' : (props.filters.status ?? ''));
const priorityFilter = ref(props.filters.priority ?? '');
const projectFilter = ref(props.filters.project_id?.toString() ?? '');
const departmentFilter = ref<string | number | null>(props.filters.department_id?.toString() ?? '');
const scopeFilter = ref(props.filters.scope ?? '');
const dueFilter = ref(props.filters.due ?? '');
const reviewFilter = ref(props.filters.review ?? '');
const completedPeriodFilter = ref(props.filters.completed_period ?? '');
const assigneeFilter = ref(props.filters.assignee_id?.toString() ?? '');
const dueFromFilter = ref(props.filters.due_from ?? '');
const dueToFilter = ref(props.filters.due_to ?? '');
const createForm = useForm({
    project_id: null as number | null,
    section_id: null as number | null,
    department_id: null as number | null,
    external_organization_id: null as number | null,
    external_department_id: null as number | null,
    external_contact_id: null as number | null,
    title: '',
    description: '',
    priority: 'normal',
    assignee_id: null as number | null,
    delegate_to_employee: false,
    delegate_assignee_id: null as number | null,
    assignment_note: '',
    next_activity_title: '',
    next_activity_description: '',
    start_date: '',
    due_date: '',
    custom_fields: {} as Record<string, string | number | boolean | null>,
});

const subStatusLabels: Record<string, string> = {
    referred: 'ارجاع شده', referral_seen: 'ارجاع مشاهده شده',
    manager_review: 'در حال بررسی مدیر', returned_for_completion: 'برگشت برای تکمیل',
};
const statusLabels: Record<string, string> = {
    todo: 'انجام نشده', observed: 'مشاهده شده', in_progress: 'در حال انجام',
    completed: 'تکمیل شده', cancelled: 'لغو شده', cannot_continue: 'امکان ادامه وجود ندارد',
};

const priorityLabels: Record<string, string> = {
    low: 'کم',
    normal: 'عادی',
    high: 'زیاد',
    urgent: 'فوری',
};

const externalDepartments = computed(() => props.formOptions?.external_departments.filter(item => item.external_organization_id === createForm.external_organization_id) ?? []);
const externalContacts = computed(() => props.formOptions?.external_contacts.filter(item => item.external_organization_id === createForm.external_organization_id && (createForm.external_department_id === null || item.external_department_id === createForm.external_department_id)) ?? []);

const selectedProject = computed(() =>
    props.formOptions?.projects.find((item) => item.id === createForm.project_id) ?? null,
);

const selectedSection = computed(() =>
    selectedProject.value?.sections.find((item) => item.id === createForm.section_id) ?? null,
);

const availableAssignees = computed(() => {
    if (!props.formOptions) return [];
    if (selectedSection.value) {
        return props.formOptions.users.filter((user) =>
            selectedSection.value?.member_ids.includes(user.id)
            && user.id !== selectedSection.value?.manager_id,
        );
    }

    if (createForm.project_id === null) return props.formOptions.users;
    if (!selectedProject.value) return [];

    return props.formOptions.users.filter((user) =>
        selectedProject.value?.member_ids.includes(user.id),
    );
});

const applyFilters = (): void => {
    isFiltering.value = true;
    const combinedPending = statusFilter.value === '__pending';

    router.get(route('tasks.index'), {
        search: search.value.trim() || undefined,
        status: combinedPending ? undefined : (statusFilter.value || undefined),
        status_group: combinedPending ? 'pending' : undefined,
        priority: priorityFilter.value || undefined,
        project_id: projectFilter.value || undefined,
        department_id: departmentFilter.value || undefined,
        scope: scopeFilter.value || undefined,
        due: dueFilter.value || undefined,
        review: reviewFilter.value || undefined,
        completed_period: completedPeriodFilter.value || undefined,
        assignee_id: assigneeFilter.value || undefined,
        due_from: dueFromFilter.value || undefined,
        due_to: dueToFilter.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            isFiltering.value = false;
        },
    });
};

const clearFilters = (): void => {
    search.value = '';
    statusFilter.value = '';
    priorityFilter.value = '';
    projectFilter.value = '';
    departmentFilter.value = '';
    scopeFilter.value = '';
    dueFilter.value = '';
    reviewFilter.value = '';
    completedPeriodFilter.value = '';
    assigneeFilter.value = '';
    dueFromFilter.value = '';
    dueToFilter.value = '';
    applyFilters();
};

const paginationLinks = computed(() =>
    props.tasks.links.filter((link) => link.url !== null),
);


const resetCreateForm = (): void => {
    createForm.reset();
    createForm.priority = 'normal';
    createForm.custom_fields = {};
    createForm.clearErrors();
};

const submitCreate = (): void => {
    createForm.post(route('tasks.store'), {
        preserveScroll: true,
        onSuccess: () => { resetCreateForm(); showCreateModal.value = false; },
    });
};

const handleExternalOrganizationChange = (): void => {
    createForm.external_department_id = null;
    createForm.external_contact_id = null;
};
const handleExternalDepartmentChange = (): void => {
    createForm.external_contact_id = null;
};

const handleProjectChange = (): void => {
    createForm.section_id = null;
    createForm.department_id = null;
    createForm.assignee_id = null;
    createForm.delegate_assignee_id = null;
    createForm.delegate_to_employee = false;
};

const handleSectionChange = (): void => {
    createForm.department_id = selectedSection.value?.department_id ?? null;
    createForm.assignee_id = null;
    createForm.delegate_assignee_id = null;
    createForm.delegate_to_employee = false;
};
</script>

<template>
    <Head title="دستورکارها" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div>
                        <h2 class="text-xl font-bold tracking-tight text-gray-900">دستورکارها</h2>
                        <p class="mt-1 text-sm text-gray-500">مشاهده و پیگیری دستورکارهایی که به آن‌ها دسترسی دارید</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <div class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">
                        {{ props.tasks.total }} دستورکار
                    </div>
                    <button v-if="canCreate && formOptions" type="button"
                        class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gray-800"
                        @click="showCreateModal = true">
                        ایجاد دستورکار
                    </button>
                </div>
            </div>
        </template>

        <div class="task-index mx-auto w-full max-w-[1440px] space-y-5 px-4 py-5 sm:px-6 lg:px-8">
            <section
                v-if="showCreateModal && canCreate && formOptions"
                class="task-create-overlay fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-950/45 p-4 backdrop-blur-[2px] sm:p-6"
                role="presentation"
                @click.self="showCreateModal = false"
                @keydown.esc="showCreateModal = false"
            >
                <div class="task-create-panel my-4 w-full max-w-5xl overflow-hidden rounded-2xl border border-white/70 bg-white shadow-2xl ring-1 ring-black/5 sm:my-8"
                    role="dialog" aria-modal="true" aria-labelledby="task-create-title">
                <div class="flex items-start justify-between gap-4 border-b border-gray-100 bg-white px-5 py-4 sm:px-6">
                    <div>
                        <h3 id="task-create-title" class="text-base font-bold tracking-tight text-gray-900">ایجاد دستورکار جدید</h3>
                        <p class="mt-1 text-sm text-gray-500">اطلاعات اصلی دستورکار را ثبت کنید. وضعیت اولیه «انجام نشده» خواهد بود.</p>
                    </div>
                    <button type="button" class="rounded-lg p-2 text-gray-500 hover:bg-gray-200 hover:text-gray-800" aria-label="بستن" @click="showCreateModal = false">×</button>
                </div>

                <form class="grid gap-4 p-5 sm:p-6 md:grid-cols-2" @submit.prevent="submitCreate">
                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">عنوان دستورکار</label>
                        <input v-model="createForm.title" type="text" required maxlength="255"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/10"
                            placeholder="عنوان دستورکار را وارد کنید" />
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

                    <div v-if="selectedProject && selectedProject.sections.length">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">بخش پروژه</label>
                        <select v-model="createForm.section_id" required @change="handleSectionChange"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">
                            <option :value="null">انتخاب بخش</option>
                            <option v-for="section in selectedProject.sections" :key="section.id" :value="section.id">{{ section.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">واحد مسئول</label>
                        <DepartmentTreeSelect v-model="createForm.department_id" :departments="formOptions.departments" :disabled="createForm.section_id !== null" placeholder="بدون واحد" />
                    </div>

                    <div v-if="createForm.section_id === null">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">مسئول دستورکار</label>
                        <select v-model="createForm.assignee_id"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">
                            <option :value="null">بدون مسئول</option>
                            <option v-for="user in availableAssignees" :key="user.id" :value="user.id">{{ user.name }}</option>
                        </select>
                        <p v-if="createForm.project_id !== null" class="mt-1 text-xs text-gray-500">برای دستورکارهای عمومی، مسئول را مستقیماً انتخاب کنید؛ دستورکارهای بخش ابتدا به مدیر واحد ارجاع می‌شوند.</p>
                    </div>

                    <div v-else class="space-y-3 rounded-lg border border-gray-200 bg-gray-50/60 p-4">
                        <div>
                            <p class="text-xs text-gray-500">مسئول اولیه</p>
                            <p class="mt-1 text-sm font-medium">{{ selectedSection?.manager_name ?? 'مدیر واحد تعیین نشده' }}</p>
                        </div>
                        <label class="flex items-start gap-2 text-sm">
                            <input v-model="createForm.delegate_to_employee" type="checkbox" class="mt-0.5 h-4 w-4" />
                            <span>ارجاع هم‌زمان از مدیر به کارمند</span>
                        </label>
                        <div v-if="createForm.delegate_to_employee">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">کارمند مقصد ارجاع</label>
                            <select v-model="createForm.delegate_assignee_id" required
                                class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">
                                <option :value="null">انتخاب کارمند</option>
                                <option v-for="user in availableAssignees" :key="user.id" :value="user.id">{{ user.name }}</option>
                            </select>
                        </div>
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

                    <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-4 md:col-span-2">
                        <div class="mb-3">
                            <h4 class="text-sm font-semibold text-gray-900">پیگیری خارجی <span class="font-normal text-gray-500">(اختیاری)</span></h4>
                            <p class="mt-1 text-xs text-gray-500">اگر این دستورکار نیاز به پیگیری با سازمان یا شخص خارج از شرکت دارد، اطلاعات آن را انتخاب کنید.</p>
                        </div>
                        <div class="grid gap-4 md:grid-cols-3">
                            <select v-model="createForm.external_organization_id" @change="handleExternalOrganizationChange" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm"><option :value="null">بدون پیگیری خارجی</option><option v-for="item in formOptions.external_organizations" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                            <DepartmentTreeSelect v-model="createForm.external_department_id" :departments="externalDepartments" :disabled="createForm.external_organization_id === null" placeholder="بدون واحد خارجی" @update:model-value="handleExternalDepartmentChange" />
                            <select v-model="createForm.external_contact_id" :disabled="createForm.external_organization_id === null" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm"><option :value="null">بدون شخص مشخص</option><option v-for="item in externalContacts" :key="item.id" :value="item.id">{{ item.name }}{{ item.position ? ' — ' + item.position : '' }}</option></select>
                        </div>
                    </div>

                    <div class="rounded-xl border border-blue-200 bg-blue-50/50 p-4 md:col-span-2">
                        <div class="mb-3">
                            <h4 class="text-sm font-semibold text-gray-900">فعالیت بعدی <span class="font-normal text-gray-500">(اختیاری)</span></h4>
                            <p class="mt-1 text-xs text-gray-500">اولین اقدام عملی که باید بعد از ایجاد دستورکار انجام شود را مشخص کنید.</p>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700">عنوان فعالیت بعدی</label>
                                <input v-model="createForm.next_activity_title" type="text" maxlength="255" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm" placeholder="مثلاً دریافت تأیید مالی" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-1.5 block text-sm font-medium text-gray-700">توضیحات فعالیت</label>
                                <textarea v-model="createForm.next_activity_description" rows="2" class="w-full rounded-lg border border-gray-300 bg-white p-3 text-sm" placeholder="در صورت نیاز، نتیجه مورد انتظار یا توضیح اقدام را بنویسید"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">توضیحات</label>
                        <textarea v-model="createForm.description" rows="4"
                            class="w-full rounded-lg border border-gray-300 bg-white p-3 text-sm shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/10"
                            placeholder="شرح دستورکار، خروجی مورد انتظار یا اطلاعات تکمیلی"></textarea>
                    </div>

                    <CustomFieldsForm
                        v-if="props.customFields.length"
                        v-model="createForm.custom_fields"
                        :fields="props.customFields"
                        :errors="createForm.errors as Record<string, string>"
                        title="اطلاعات تکمیلی دستورکار"
                        description="اطلاعات اختصاصی تعریف‌شده برای دستورکار را در این بخش ثبت کنید."
                    />

                    <div v-if="createForm.assignee_id" class="md:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">توضیح تخصیص دستورکار</label>
                        <input v-model="createForm.assignment_note" type="text" maxlength="5000"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                            placeholder="در صورت نیاز، توضیحی برای مسئول دستورکار بنویسید" />
                    </div>

                    <div class="flex flex-wrap gap-2 border-t border-gray-100 pt-4 md:col-span-2">
                        <button type="submit" :disabled="createForm.processing"
                            class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50">
                            {{ createForm.processing ? 'در حال ذخیره...' : 'ایجاد دستورکار' }}
                        </button>
                        <button type="button" :disabled="createForm.processing"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-50"
                            @click="resetCreateForm">
                            پاک کردن
                        </button>
                    </div>
                </form>
                </div>
            </section>

            <section class="task-filter-panel rounded-2xl border border-border bg-card p-4 shadow-sm sm:p-5">
                <div class="mb-4 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <div v-if="props.canFilterUnit">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">محدوده دستورکار</label>
                        <select v-model="scopeFilter" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm">
                            <option value="">همه موارد مجاز</option>
                            <option value="personal">دستورکارهای شخصی من</option>
                            <option value="unit">دستورکارهای واحد</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">مهلت</label>
                        <select v-model="dueFilter" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm">
                            <option value="">همه مهلت‌ها</option>
                            <option value="today">سررسید امروز</option>
                            <option value="overdue">عقب‌افتاده</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">بررسی</label>
                        <select v-model="reviewFilter" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm">
                            <option value="">همه وضعیت‌های بررسی</option>
                            <option value="1">در انتظار بررسی</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">بازه تکمیل</label>
                        <select v-model="completedPeriodFilter" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm">
                            <option value="">همه زمان‌ها</option>
                            <option value="month">تکمیل‌شده در ماه جاری</option>
                        </select>
                    </div>
                </div>
                <div class="mb-4 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">مسئول دستورکار</label>
                        <select v-model="assigneeFilter" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm">
                            <option value="">همه مسئولان</option>
                            <option v-for="user in filterOptions.users" :key="user.id" :value="user.id">{{ user.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">مهلت از</label>
                        <JalaliDatePicker v-model="dueFromFilter" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">مهلت تا</label>
                        <JalaliDatePicker v-model="dueToFilter" />
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">وضعیت</label>
                        <select v-model="statusFilter" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm">
                            <option value="">همه وضعیت‌ها</option>
                            <option value="__pending">در انتظار انجام یا دیده‌شده</option>
                            <option value="todo">انجام نشده</option>
                            <option value="observed">مشاهده شده</option>
                            <option value="in_progress">در حال انجام</option>
                            <option value="completed">تکمیل شده</option>
                            <option value="cancelled">لغو شده</option>
                            <option value="cannot_continue">امکان ادامه وجود ندارد</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">اولویت</label>
                        <select v-model="priorityFilter" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm">
                            <option value="">همه اولویت‌ها</option>
                            <option value="low">کم</option>
                            <option value="normal">عادی</option>
                            <option value="high">زیاد</option>
                            <option value="urgent">فوری</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">پروژه</label>
                        <select v-model="projectFilter" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm">
                            <option value="">همه پروژه‌ها</option>
                            <option v-for="project in filterOptions.projects" :key="project.id" :value="project.id">{{ project.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">واحد</label>
                        <DepartmentTreeSelect v-model="departmentFilter" :departments="filterOptions.departments" placeholder="همه واحدها" />
                    </div>
                </div>
                <div class="mt-4 border-t border-gray-100 pt-4">
                    <label for="task-search" class="mb-1.5 block text-sm font-medium text-gray-700">جست‌وجو</label>
                    <input id="task-search" v-model="search" type="search"
                        placeholder="عنوان، توضیحات، پروژه، واحد یا مسئول..."
                        class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/10" />
                </div>
                <div class="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
                    <button type="button" :disabled="isFiltering" @click="applyFilters" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50">
                        {{ isFiltering ? 'در حال اعمال...' : 'اعمال فیلتر' }}
                    </button>
                    <button type="button" :disabled="isFiltering" @click="clearFilters" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50">
                        پاک کردن
                    </button>
                </div>
            </section>

            <section class="task-list-panel relative overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-4 sm:px-5">
                    <div>
                        <h3 class="font-bold text-gray-900">فهرست دستورکارها</h3>
                        <p class="mt-0.5 text-xs text-gray-500">مواردی که بر اساس سطح دسترسی شما قابل مشاهده هستند</p>
                    </div>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">{{ props.tasks.total }}</span>
                </div>

                <div v-if="isFiltering" class="absolute inset-0 z-10 flex items-center justify-center bg-white/70 backdrop-blur-[1px]">
                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-sm">
                        در حال بارگذاری...
                    </div>
                </div>

                <div v-if="props.tasks.data.length === 0" class="px-6 py-14 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-500">—</div>
                    <p class="mt-3 font-medium text-gray-800">{{ props.tasks.total === 0 ? 'دستورکاری با این مشخصات پیدا نشد.' : 'دستورکاری برای نمایش وجود ندارد.' }}</p>
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
                            <tr v-for="task in props.tasks.data" :key="task.id" class="transition-colors hover:bg-gray-50/80">
                                <td class="px-5 py-3.5 font-semibold text-gray-900">{{ task.title }}</td>
                                <td class="px-4 py-3.5 text-gray-600">{{ task.project?.name ?? '—' }}</td>
                                <td class="px-4 py-3.5 text-gray-600">{{ task.department?.name ?? '—' }}</td>
                                <td class="px-4 py-3.5 text-gray-600">{{ task.assignee?.name ?? 'بدون مسئول' }}</td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="{
                                            'bg-gray-100 text-gray-700': task.status === 'todo',
                                            'bg-sky-50 text-sky-700': task.status === 'observed',
                                            'bg-blue-50 text-blue-700': task.status === 'in_progress',
                                            'bg-green-50 text-green-700': task.status === 'completed',
                                            'bg-red-50 text-red-700': ['cancelled', 'cannot_continue'].includes(task.status),
                                        }">
                                        {{ statusLabels[task.status] ? (task.sub_status && !['completed', 'cancelled', 'cannot_continue'].includes(task.status) ? `${statusLabels[task.status]} (${subStatusLabels[task.sub_status] ?? task.sub_status})` : statusLabels[task.status]) : task.status }}
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
                                        class="inline-flex rounded-lg border border-border bg-white px-3 py-1.5 text-xs font-semibold text-foreground transition hover:border-primary/40 hover:bg-accent/40 hover:text-primary">
                                        مشاهده
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <div v-if="props.tasks.last_page > 1" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border bg-card px-4 py-3 shadow-sm">
                <p class="text-xs text-gray-500">
                    نمایش {{ props.tasks.from ?? 0 }} تا {{ props.tasks.to ?? 0 }} از {{ props.tasks.total }} دستورکار
                </p>
                <div class="flex flex-wrap items-center gap-1">
                    <Link v-for="link in paginationLinks" :key="link.label" :href="link.url!" preserve-scroll preserve-state
                        @click="isFiltering = true"
                        class="rounded-lg px-3 py-1.5 text-xs font-medium transition"
                        :class="link.active ? 'bg-primary text-primary-foreground shadow-sm' : 'border border-border bg-white text-foreground hover:bg-accent/40 hover:text-primary'"
                        v-html="link.label" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
