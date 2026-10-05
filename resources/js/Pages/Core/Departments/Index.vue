<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import DepartmentIcon from '@/Components/DepartmentIcon.vue';
import { departmentIconOptions } from '@/Utils/departmentIcons';

interface PositionUser {
    id: number;
    name: string;
    profile?: {
        avatar_path: string | null;
    } | null;
    pivot?: {
        is_primary: boolean;
        is_active: boolean;
    };
}

interface Position {
    id: number;
    name: string;
    code: string | null;
    description: string | null;
    is_active: boolean;
    users?: PositionUser[];
}

interface DepartmentMember {
    id: number;
    name: string;
    profile?: {
        avatar_path: string | null;
    } | null;
}

interface Department {
    id: number;
    parent_id: number | null;
    name: string;
    code: string | null;
    description: string | null;
    is_active: boolean;
    manager_id: number | null;
    manager?: { id: number; name: string } | null;
    parent?: { id: number; name: string } | null;
    members?: DepartmentMember[];
    positions?: Position[];
    children_count?: number;
    icon: string;
}

interface User {
    id: number;
    name: string;
}

const props = defineProps<{
    departments: Department[];
    users: User[];
}>();

const page = usePage();
const selectedDepartmentId = ref<number | null>(props.departments[0]?.id ?? null);
const editingDepartment = ref<Department | null>(null);
const editingPosition = ref<Position | null>(null);
const showDepartmentForm = ref(false);
const showPositionForm = ref(false);

const form = useForm({
    parent_id: null as number | null,
    manager_id: null as number | null,
    name: '',
    icon: 'building-2',
    description: '',
    is_active: true,
});

const positionForm = useForm({
    name: '',
    description: '',
    is_active: true,
});

const assignmentForm = useForm({
    user_id: null as number | null,
    is_primary: false,
});

const abilities = computed(() => page.props.auth.abilities ?? {});

const selectedDepartment = computed(
    () =>
        props.departments.find(
            (department) => department.id === selectedDepartmentId.value,
        ) ?? null,
);

const parentIdOf = (department: Department): number | null =>
    department.parent_id === null ? null : Number(department.parent_id);

const childrenCount = (department: Department): number =>
    department.children_count ??
    props.departments.filter(
        (child) => parentIdOf(child) === department.id,
    ).length;

const descendantIds = (id: number): number[] => {
    const result: number[] = [];

    const visit = (parentId: number) => {
        props.departments.forEach((department) => {
            if (parentIdOf(department) === parentId) {
                result.push(department.id);
                visit(department.id);
            }
        });
    };

    visit(id);

    return result;
};

const parentOptions = computed(() => {
    if (!editingDepartment.value) {
        return props.departments;
    }

    const excluded = new Set([
        editingDepartment.value.id,
        ...descendantIds(editingDepartment.value.id),
    ]);

    return props.departments.filter(
        (department) => !excluded.has(department.id),
    );
});

const treeRows = computed(() => {
    const rows: Array<{
        department: Department;
        depth: number;
        childCount: number;
    }> = [];

    const visit = (parentId: number | null, depth: number) => {
        props.departments
            .filter((department) => parentIdOf(department) === parentId)
            .forEach((department) => {
                rows.push({
                    department,
                    depth,
                    childCount: childrenCount(department),
                });

                visit(department.id, depth + 1);
            });
    };

    visit(null, 0);

    const listedIds = new Set(rows.map((row) => row.department.id));

    props.departments
        .filter((department) => !listedIds.has(department.id))
        .forEach((department) => {
            rows.push({
                department,
                depth: 0,
                childCount: childrenCount(department),
            });
        });

    return rows;
});

const stats = computed(() => {
    const memberIds = new Set(
        props.departments.flatMap(
            (department) => department.members?.map((member) => member.id) ?? [],
        ),
    );

    return {
        total: props.departments.length,
        active: props.departments.filter((item) => item.is_active).length,
        members: memberIds.size,
        managers: props.departments.filter((item) => item.manager_id).length,
        positions: props.departments.reduce(
            (sum, item) => sum + (item.positions?.length ?? 0),
            0,
        ),
    };
});

const resetDepartmentForm = () => {
    form.reset();
    form.is_active = true;
    form.icon = 'building-2';
    form.clearErrors();
};

const openCreate = () => {
    editingDepartment.value = null;
    resetDepartmentForm();
    form.parent_id = selectedDepartment.value?.id ?? null;
    showDepartmentForm.value = true;
};

const openEdit = (department: Department) => {
    selectedDepartmentId.value = department.id;
    editingDepartment.value = department;
    form.parent_id = department.parent_id;
    form.manager_id = department.manager_id;
    form.name = department.name;
    form.icon = department.icon ?? 'building-2';
    form.description = department.description ?? '';
    form.is_active = department.is_active;
    form.clearErrors();
    showDepartmentForm.value = true;
};

const submitDepartment = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showDepartmentForm.value = false;
            editingDepartment.value = null;
            resetDepartmentForm();
        },
    };

    if (editingDepartment.value) {
        form.put(
            route('departments.update', editingDepartment.value.id),
            options,
        );
        return;
    }

    form.post(route('departments.store'), options);
};

const deleteDepartment = (department: Department) => {
    const hasChildren = childrenCount(department) > 0;
    const warning = hasChildren
        ? '\nاین واحد دارای زیرواحد است و قوانین حذف ساختار سازمانی نیز اعمال می‌شود.'
        : '';

    if (!confirm('آیا از حذف «' + department.name + '» مطمئن هستید؟' + warning)) {
        return;
    }

    form.delete(route('departments.destroy', department.id), {
        preserveScroll: true,
        onSuccess: () => {
            if (selectedDepartmentId.value === department.id) {
                selectedDepartmentId.value =
                    props.departments.find((item) => item.id !== department.id)?.id ??
                    null;
            }
        },
    });
};

const toggleStatus = (department: Department) => {
    useForm({}).patch(
        route('departments.toggle-status', department.id),
        { preserveScroll: true },
    );
};

const resetPositionForm = () => {
    positionForm.reset();
    positionForm.is_active = true;
    positionForm.clearErrors();
};

const openPositionCreate = () => {
    editingPosition.value = null;
    resetPositionForm();
    showPositionForm.value = true;
};

const openPositionEdit = (position: Position) => {
    editingPosition.value = position;
    positionForm.name = position.name;
    positionForm.description = position.description ?? '';
    positionForm.is_active = position.is_active;
    positionForm.clearErrors();
    showPositionForm.value = true;
};

const submitPosition = () => {
    if (!selectedDepartment.value) {
        return;
    }

    const department = selectedDepartment.value;

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showPositionForm.value = false;
            editingPosition.value = null;
            resetPositionForm();
        },
    };

    if (editingPosition.value) {
        positionForm.put(
            route('departments.positions.update', {
                department: department.id,
                position: editingPosition.value.id,
            }),
            options,
        );
        return;
    }

    positionForm.post(
        route('departments.positions.store', { department: department.id }),
        options,
    );
};

const deletePosition = (position: Position) => {
    if (!selectedDepartment.value) {
        return;
    }

    if (!confirm('آیا از حذف سمت «' + position.name + '» مطمئن هستید؟')) {
        return;
    }

    positionForm.delete(
        route('departments.positions.destroy', {
            department: selectedDepartment.value.id,
            position: position.id,
        }),
        { preserveScroll: true },
    );
};

const assignPosition = (position: Position) => {
    if (!selectedDepartment.value || !assignmentForm.user_id) {
        return;
    }

    assignmentForm.post(
        route('departments.positions.members.store', {
            department: selectedDepartment.value.id,
            position: position.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                assignmentForm.reset();
                assignmentForm.is_primary = false;
            },
        },
    );
};

const unassignPosition = (position: Position, user: PositionUser) => {
    if (!selectedDepartment.value) {
        return;
    }

    if (!confirm('تخصیص «' + user.name + '» از این سمت حذف شود؟')) {
        return;
    }

    assignmentForm.delete(
        route('departments.positions.members.destroy', {
            department: selectedDepartment.value.id,
            position: position.id,
            user: user.id,
        }),
        { preserveScroll: true },
    );
};

watch(
    () => props.departments,
    (departments) => {
        if (
            selectedDepartmentId.value === null ||
            !departments.some(
                (department) => department.id === selectedDepartmentId.value,
            )
        ) {
            selectedDepartmentId.value = departments[0]?.id ?? null;
        }
    },
);
</script>

<template>
    <Head title="سازمان داخلی" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold text-teal-700">
                        <span class="h-2 w-2 rounded-full bg-teal-500"></span>
                        مدیریت هسته سازمان
                    </div>

                    <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">
                        سازمان داخلی
                    </h2>

                    <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
                        ساختار واحدها، مدیران، اعضا و سمت‌های سازمانی را از یک نمای مدیریتی یکپارچه کنترل کنید.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        v-if="abilities.users_manage_departments"
                        :href="route('users.index')"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        مدیریت اعضا
                    </Link>

                    <button
                        v-if="abilities.departments_create"
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800"
                        @click="openCreate"
                    >
                        <span class="ml-2 text-lg leading-none">+</span>
                        واحد جدید
                    </button>
                </div>
            </div>
        </template>

        <div class="min-h-[calc(100vh-8rem)] bg-slate-50 py-6 md:py-8" dir="rtl">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between">
                            <span class="text-xs font-semibold text-slate-500">کل واحدها</span>
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-700">⌘</span>
                        </div>
                        <p class="mt-4 text-3xl font-bold tracking-tight text-slate-950">{{ stats.total }}</p>
                        <p class="mt-1 text-xs text-slate-400">تمام سطوح ساختار سازمانی</p>
                    </div>

                    <div class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-5 shadow-sm">
                        <div class="flex items-start justify-between">
                            <span class="text-xs font-semibold text-emerald-700">واحدهای فعال</span>
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/70 text-emerald-700">✓</span>
                        </div>
                        <p class="mt-4 text-3xl font-bold tracking-tight text-emerald-950">{{ stats.active }}</p>
                        <p class="mt-1 text-xs text-emerald-700/70">واحدهای آماده بهره‌برداری</p>
                    </div>

                    <div class="rounded-2xl border border-blue-100 bg-blue-50/70 p-5 shadow-sm">
                        <div class="flex items-start justify-between">
                            <span class="text-xs font-semibold text-blue-700">کاربران سازمان</span>
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/70 text-blue-700">◉</span>
                        </div>
                        <p class="mt-4 text-3xl font-bold tracking-tight text-blue-950">{{ stats.members }}</p>
                        <p class="mt-1 text-xs text-blue-700/70">کاربر دارای عضویت در واحدها</p>
                    </div>

                    <div class="rounded-2xl border border-teal-100 bg-teal-50/70 p-5 shadow-sm">
                        <div class="flex items-start justify-between">
                            <span class="text-xs font-semibold text-teal-700">سمت‌های سازمانی</span>
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/70 text-teal-700">◆</span>
                        </div>
                        <p class="mt-4 text-3xl font-bold tracking-tight text-teal-950">{{ stats.positions }}</p>
                        <p class="mt-1 text-xs text-teal-700/70">سمت‌های تعریف‌شده در واحدها</p>
                    </div>
                </section>

                <div class="grid gap-6 xl:grid-cols-[minmax(0,0.8fr)_minmax(0,1.5fr)]">
                    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-100 px-5 py-5">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold text-teal-700">Organization Tree</p>
                                    <h3 class="mt-1 text-base font-bold text-slate-950">ساختار واحدها</h3>
                                </div>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-500">
                                    {{ stats.total }} واحد
                                </span>
                            </div>
                        </div>

                        <div v-if="treeRows.length === 0" class="px-6 py-16 text-center">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">⌘</div>
                            <p class="mt-4 text-sm font-bold text-slate-700">هنوز ساختار سازمانی ساخته نشده است.</p>
                            <p class="mt-1 text-xs leading-6 text-slate-400">اولین واحد را ایجاد کنید تا نقشه سازمان شکل بگیرد.</p>
                            <button
                                v-if="abilities.departments_create"
                                type="button"
                                class="mt-5 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white"
                                @click="openCreate"
                            >
                                ایجاد اولین واحد
                            </button>
                        </div>

                        <div v-else class="max-h-[680px] overflow-y-auto p-3">
                            <button
                                v-for="row in treeRows"
                                :key="row.department.id"
                                type="button"
                                class="group relative flex w-full items-center rounded-xl px-3 py-3 text-right transition"
                                :class="selectedDepartmentId === row.department.id ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-700 hover:bg-slate-50'"
                                @click="selectedDepartmentId = row.department.id"
                            >
                                <span
                                    class="absolute inset-y-2 w-px bg-slate-200"
                                    :class="selectedDepartmentId === row.department.id ? 'bg-slate-700' : ''"
                                    :style="{ right: (14 + row.depth * 20) + 'px' }"
                                ></span>

                                <span
                                    class="relative z-10 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-xs font-bold"
                                    :class="selectedDepartmentId === row.department.id ? 'bg-white/10 text-teal-200' : 'bg-teal-50 text-teal-700'"
                                    :style="{ marginRight: (row.depth * 20) + 'px' }"
                                >
                                    <DepartmentIcon :icon="row.department.icon" :size="18" />
                                </span>

                                <span class="min-w-0 flex-1 pe-3">
                                    <span
                                        class="block truncate text-sm font-semibold"
                                        :class="selectedDepartmentId === row.department.id ? 'text-white' : 'text-slate-800'"
                                    >
                                        {{ row.department.name }}
                                    </span>
                                    <span class="mt-0.5 flex items-center gap-2 text-[11px]" :class="selectedDepartmentId === row.department.id ? 'text-slate-300' : 'text-slate-400'">
                                        <span>{{ row.department.members?.length ?? 0 }} عضو</span>
                                        <span>·</span>
                                        <span>{{ row.childCount }} زیرواحد</span>
                                    </span>
                                </span>

                                <span
                                    v-if="row.department.is_active"
                                    class="h-2 w-2 rounded-full bg-emerald-400"
                                ></span>
                            </button>
                        </div>
                    </section>

                    <section v-if="selectedDepartment" class="space-y-6">
                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="bg-slate-950 px-6 py-6 text-white sm:px-7">
                                <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="flex items-start gap-4">
                                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/10 text-xl font-bold ring-1 ring-white/10">
                                            <DepartmentIcon :icon="selectedDepartment.icon" :size="26" />
                                        </div>

                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="text-xl font-bold tracking-tight">
                                                    {{ selectedDepartment.name }}
                                                </h3>

                                                <span
                                                    v-if="selectedDepartment.code"
                                                    dir="ltr"
                                                    class="rounded-full bg-white/10 px-2.5 py-1 text-[11px] text-slate-300"
                                                >
                                                    {{ selectedDepartment.code }}
                                                </span>
                                            </div>

                                            <p class="mt-2 text-sm text-slate-300">
                                                {{ selectedDepartment.description || 'برای این واحد توضیحی ثبت نشده است.' }}
                                            </p>
                                        </div>
                                    </div>

                                    <span
                                        class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="selectedDepartment.is_active ? 'bg-emerald-400/15 text-emerald-200' : 'bg-white/10 text-slate-300'"
                                    >
                                        {{ selectedDepartment.is_active ? 'فعال' : 'غیرفعال' }}
                                    </span>
                                </div>

                                <div class="mt-6 grid gap-3 sm:grid-cols-4">
                                    <div class="rounded-xl bg-white/5 p-3 ring-1 ring-white/5">
                                        <p class="text-[11px] text-slate-400">والد</p>
                                        <p class="mt-1 truncate text-sm font-semibold text-white">{{ selectedDepartment.parent?.name ?? 'ریشه سازمان' }}</p>
                                    </div>

                                    <div class="rounded-xl bg-white/5 p-3 ring-1 ring-white/5">
                                        <p class="text-[11px] text-slate-400">مدیر</p>
                                        <p class="mt-1 truncate text-sm font-semibold text-white">{{ selectedDepartment.manager?.name ?? 'تعیین نشده' }}</p>
                                    </div>

                                    <div class="rounded-xl bg-white/5 p-3 ring-1 ring-white/5">
                                        <p class="text-[11px] text-slate-400">اعضا</p>
                                        <p class="mt-1 text-sm font-semibold text-white">{{ selectedDepartment.members?.length ?? 0 }} نفر</p>
                                    </div>

                                    <div class="rounded-xl bg-white/5 p-3 ring-1 ring-white/5">
                                        <p class="text-[11px] text-slate-400">سمت‌ها</p>
                                        <p class="mt-1 text-sm font-semibold text-white">{{ selectedDepartment.positions?.length ?? 0 }} سمت</p>
                                    </div>
                                </div>

                                <div class="mt-5 flex flex-wrap gap-2">
                                    <button
                                        v-if="abilities.departments_edit"
                                        type="button"
                                        class="rounded-xl bg-white px-3.5 py-2 text-xs font-semibold text-slate-900 transition hover:bg-slate-100"
                                        @click="openEdit(selectedDepartment)"
                                    >
                                        ویرایش واحد
                                    </button>

                                    <button
                                        v-if="abilities.departments_toggle_status"
                                        type="button"
                                        class="rounded-xl bg-white/10 px-3.5 py-2 text-xs font-semibold text-white ring-1 ring-white/10 transition hover:bg-white/15"
                                        @click="toggleStatus(selectedDepartment)"
                                    >
                                        {{ selectedDepartment.is_active ? 'غیرفعال کردن' : 'فعال کردن' }}
                                    </button>

                                    <button
                                        v-if="abilities.departments_delete"
                                        type="button"
                                        class="rounded-xl bg-red-500/10 px-3.5 py-2 text-xs font-semibold text-red-200 ring-1 ring-red-300/10 transition hover:bg-red-500/15"
                                        @click="deleteDepartment(selectedDepartment)"
                                    >
                                        حذف واحد
                                    </button>
                                </div>
                            </div>

                            <div class="grid gap-5 p-5 sm:p-6 lg:grid-cols-2">
                                <div>
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <h4 class="font-bold text-slate-900">اعضای واحد</h4>
                                            <p class="mt-1 text-xs text-slate-500">عضویت افراد مستقل از سمت سازمانی مدیریت می‌شود.</p>
                                        </div>

                                        <Link
                                            v-if="abilities.users_manage_departments"
                                            :href="route('users.index')"
                                            class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                                        >
                                            مدیریت
                                        </Link>
                                    </div>

                                    <div v-if="selectedDepartment.members?.length" class="mt-4 flex flex-wrap gap-2">
                                        <span
                                            v-for="member in selectedDepartment.members"
                                            :key="member.id"
                                            class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-700"
                                        >
                                            <UserAvatar
                                                :name="member.name"
                                                :avatar-path="member.profile?.avatar_path"
                                                size="xs"
                                            />
                                            {{ member.name }}
                                        </span>
                                    </div>

                                    <div v-else class="mt-4 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-5 text-center">
                                        <p class="text-xs font-semibold text-slate-600">عضوی در این واحد ثبت نشده است.</p>
                                        <p class="mt-1 text-[11px] leading-5 text-slate-400">از بخش مدیریت اعضا افراد را به واحد اضافه کنید.</p>
                                    </div>
                                </div>

                                <div>
                                    <div>
                                        <h4 class="font-bold text-slate-900">اطلاعات مدیریتی</h4>
                                        <p class="mt-1 text-xs text-slate-500">جایگاه این واحد در ساختار داخلی سازمان</p>
                                    </div>

                                    <div class="mt-4 space-y-2">
                                        <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                                            <span class="text-xs text-slate-500">واحد والد</span>
                                            <span class="text-sm font-semibold text-slate-800">{{ selectedDepartment.parent?.name ?? 'ریشه سازمان' }}</span>
                                        </div>
                                        <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                                            <span class="text-xs text-slate-500">تعداد زیرواحد</span>
                                            <span class="text-sm font-semibold text-slate-800">{{ childrenCount(selectedDepartment) }}</span>
                                        </div>
                                        <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                                            <span class="text-xs text-slate-500">مدیر واحد</span>
                                            <span class="text-sm font-semibold text-slate-800">{{ selectedDepartment.manager?.name ?? 'تعیین نشده' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-xs font-semibold text-teal-700">Positions</p>
                                    <h4 class="mt-1 text-lg font-bold text-slate-950">سمت‌های سازمانی</h4>
                                    <p class="mt-1 text-xs text-slate-500">سمت‌ها به واحد وابسته‌اند و افراد دارای عضویت را می‌توان به آن‌ها تخصیص داد.</p>
                                </div>

                                <button
                                    v-if="abilities.departments_manage_positions"
                                    type="button"
                                    class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800"
                                    @click="openPositionCreate"
                                >
                                    + سمت جدید
                                </button>
                            </div>

                            <div v-if="!selectedDepartment.positions?.length" class="px-6 py-12 text-center">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">◆</div>
                                <p class="mt-4 text-sm font-semibold text-slate-700">هنوز سمتی برای این واحد تعریف نشده است.</p>
                                <p class="mt-1 text-xs text-slate-400">برای ساختاردهی دقیق‌تر، سمت‌های سازمانی را اضافه کنید.</p>
                            </div>

                            <div v-else class="divide-y divide-slate-100">
                                <div
                                    v-for="position in selectedDepartment.positions"
                                    :key="position.id"
                                    class="p-5 sm:p-6"
                                >
                                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h5 class="font-bold text-slate-900">{{ position.name }}</h5>

                                                <span
                                                    v-if="position.code"
                                                    dir="ltr"
                                                    class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-500"
                                                >
                                                    {{ position.code }}
                                                </span>

                                                <span
                                                    class="rounded-full px-2.5 py-1 text-[10px] font-semibold"
                                                    :class="position.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                                >
                                                    {{ position.is_active ? 'فعال' : 'غیرفعال' }}
                                                </span>
                                            </div>

                                            <p v-if="position.description" class="mt-2 text-xs leading-6 text-slate-500">
                                                {{ position.description }}
                                            </p>
                                        </div>

                                        <div v-if="abilities.departments_manage_positions" class="flex gap-2">
                                            <button
                                                type="button"
                                                class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                                                @click="openPositionEdit(position)"
                                            >
                                                ویرایش
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-lg border border-red-100 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50"
                                                @click="deletePosition(position)"
                                            >
                                                حذف
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mt-4 rounded-xl border border-slate-100 bg-slate-50/70 p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <div>
                                                <p class="text-xs font-bold text-slate-800">افراد این سمت</p>
                                                <p class="mt-1 text-[11px] text-slate-400">{{ position.users?.length ?? 0 }} نفر</p>
                                            </div>
                                        </div>

                                        <div v-if="position.users?.length" class="mt-3 flex flex-wrap gap-2">
                                            <span
                                                v-for="user in position.users"
                                                :key="user.id"
                                                class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-slate-700 ring-1 ring-slate-200"
                                            >
                                                <UserAvatar
                                                    :name="user.name"
                                                    :avatar-path="user.profile?.avatar_path"
                                                    size="xs"
                                                />
                                                {{ user.name }}
                                                <span v-if="user.pivot?.is_primary" class="text-[10px] font-semibold text-teal-700">· اصلی</span>
                                                <button
                                                    v-if="abilities.departments_manage_positions"
                                                    type="button"
                                                    class="text-slate-300 transition hover:text-red-500"
                                                    aria-label="حذف تخصیص سمت"
                                                    @click="unassignPosition(position, user)"
                                                >
                                                    ×
                                                </button>
                                            </span>
                                        </div>

                                        <div
                                            v-else
                                            class="mt-3 rounded-lg border border-dashed border-slate-200 bg-white px-3 py-3 text-xs text-slate-400"
                                        >
                                            هنوز فردی به این سمت تخصیص داده نشده است.
                                        </div>

                                        <div
                                            v-if="abilities.departments_manage_positions && selectedDepartment.members?.length"
                                            class="mt-4 flex flex-col gap-2 sm:flex-row"
                                        >
                                            <select
                                                v-model="assignmentForm.user_id"
                                                class="min-w-0 flex-1 rounded-lg border-slate-200 bg-white text-xs text-slate-700 focus:border-teal-500 focus:ring-teal-500"
                                            >
                                                <option :value="null">انتخاب عضو واحد برای تخصیص سمت...</option>
                                                <option
                                                    v-for="member in selectedDepartment.members"
                                                    :key="member.id"
                                                    :value="member.id"
                                                >
                                                    {{ member.name }}
                                                </option>
                                            </select>

                                            <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-600">
                                                <input
                                                    v-model="assignmentForm.is_primary"
                                                    type="checkbox"
                                                    class="rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                                                />
                                                سمت اصلی
                                            </label>

                                            <button
                                                type="button"
                                                :disabled="assignmentForm.processing || !assignmentForm.user_id"
                                                class="rounded-lg bg-teal-600 px-3.5 py-2 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40"
                                                @click="assignPosition(position)"
                                            >
                                                تخصیص
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </section>

                    <section
                        v-else
                        class="flex min-h-[420px] items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center"
                    >
                        <div>
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">⌘</div>
                            <h3 class="mt-4 text-base font-bold text-slate-800">واحدی انتخاب نشده است</h3>
                            <p class="mt-1 text-sm text-slate-500">از ساختار سمت مقابل یک واحد را برای مشاهده جزئیات انتخاب کنید.</p>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <div
            v-if="showDepartmentForm"
            class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm"
            @click.self="showDepartmentForm = false"
        >
            <div class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-100 px-5 py-5 sm:px-6">
                    <div>
                        <p class="text-xs font-semibold text-teal-700">Internal Organization</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-950">
                            {{ editingDepartment ? 'ویرایش واحد سازمانی' : 'ایجاد واحد سازمانی' }}
                        </h3>
                        <p class="mt-1 text-xs text-slate-500">اطلاعات ساختاری واحد را تعریف کنید.</p>
                    </div>

                    <button
                        type="button"
                        class="rounded-lg p-2 text-xl leading-none text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                        aria-label="بستن"
                        @click="showDepartmentForm = false"
                    >
                        ×
                    </button>
                </div>

                <form class="space-y-5 p-5 sm:p-6" @submit.prevent="submitDepartment">
                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">نام واحد</label>
                            <TextInput v-model="form.name" class="block min-h-11 w-full rounded-xl text-right" required maxlength="150" />
                            <InputError class="mt-1.5" :message="form.errors.name" />
                        </div>

                        <div class="md:col-span-2">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700">آیکون واحد</label>
                                    <p class="mt-1 text-xs text-slate-400">یک آیکون متناسب با نوع یا کارکرد واحد انتخاب کنید.</p>
                                </div>
                                <div class="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600">
                                    <DepartmentIcon :icon="form.icon" :size="18" />
                                    {{ departmentIconOptions.find((option) => option.key === form.icon)?.label ?? 'ساختمان' }}
                                </div>
                            </div>

                            <div class="mt-3 grid max-h-72 grid-cols-4 gap-2 overflow-y-auto rounded-xl border border-slate-100 bg-slate-50/60 p-2 pr-1 sm:grid-cols-8">
                                <button
                                    v-for="option in departmentIconOptions"
                                    :key="option.key"
                                    type="button"
                                    class="group flex min-h-16 flex-col items-center justify-center gap-1.5 rounded-xl border px-2 py-2 text-slate-500 transition"
                                    :class="form.icon === option.key
                                        ? 'border-teal-500 bg-teal-50 text-teal-700 ring-2 ring-teal-100'
                                        : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50 hover:text-slate-700'"
                                    :aria-label="option.label"
                                    :title="option.label"
                                    @click="form.icon = option.key"
                                >
                                    <component :is="option.component" :size="21" :stroke-width="1.9" aria-hidden="true" />
                                    <span class="truncate text-[10px] font-medium">{{ option.label }}</span>
                                </button>
                            </div>
                            <InputError class="mt-1.5" :message="form.errors.icon" />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">واحد والد</label>
                            <select
                                v-model="form.parent_id"
                                class="block min-h-11 w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-200"
                            >
                                <option :value="null">ریشه سازمان</option>
                                <option v-for="department in parentOptions" :key="department.id" :value="department.id">
                                    {{ department.name }}
                                </option>
                            </select>
                            <InputError class="mt-1.5" :message="form.errors.parent_id" />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">مدیر واحد</label>
                            <select
                                v-model="form.manager_id"
                                class="block min-h-11 w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-200"
                            >
                                <option :value="null">بدون مدیر</option>
                                <option v-for="user in props.users" :key="user.id" :value="user.id">
                                    {{ user.name }}
                                </option>
                            </select>
                            <InputError class="mt-1.5" :message="form.errors.manager_id" />
                        </div>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-700">توضیحات</label>
                        <textarea
                            v-model="form.description"
                            rows="4"
                            class="block w-full rounded-xl border-slate-300 text-sm text-slate-900 shadow-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-200"
                            placeholder="شرح کوتاهی از مأموریت یا مسئولیت این واحد..."
                        ></textarea>
                        <InputError class="mt-1.5" :message="form.errors.description" />
                    </div>

                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                        />
                        واحد فعال باشد
                    </label>

                    <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="showDepartmentForm = false"
                        >
                            انصراف
                        </button>

                        <PrimaryButton
                            :disabled="form.processing"
                            class="min-h-11 rounded-xl bg-slate-950 px-5 text-sm font-semibold tracking-normal hover:bg-slate-800"
                        >
                            {{ form.processing ? 'در حال ذخیره...' : editingDepartment ? 'ذخیره تغییرات' : 'ایجاد واحد' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>

        <div
            v-if="showPositionForm && selectedDepartment"
            class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm"
            @click.self="showPositionForm = false"
        >
            <div class="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-100 px-5 py-5 sm:px-6">
                    <div>
                        <p class="text-xs font-semibold text-teal-700">Organizational Position</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-950">
                            {{ editingPosition ? 'ویرایش سمت' : 'ایجاد سمت جدید' }}
                        </h3>
                        <p class="mt-1 text-xs text-slate-500">واحد: {{ selectedDepartment.name }}</p>
                    </div>

                    <button
                        type="button"
                        class="rounded-lg p-2 text-xl leading-none text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                        aria-label="بستن"
                        @click="showPositionForm = false"
                    >
                        ×
                    </button>
                </div>

                <form class="space-y-5 p-5 sm:p-6" @submit.prevent="submitPosition">
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-700">نام سمت</label>
                        <TextInput v-model="positionForm.name" class="block min-h-11 w-full rounded-xl text-right" required maxlength="150" />
                        <InputError class="mt-1.5" :message="positionForm.errors.name" />
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-700">شرح مسئولیت</label>
                        <textarea
                            v-model="positionForm.description"
                            rows="4"
                            class="block w-full rounded-xl border-slate-300 text-sm text-slate-900 shadow-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-200"
                        ></textarea>
                        <InputError class="mt-1.5" :message="positionForm.errors.description" />
                    </div>

                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input
                            v-model="positionForm.is_active"
                            type="checkbox"
                            class="rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                        />
                        سمت فعال باشد
                    </label>

                    <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="showPositionForm = false"
                        >
                            انصراف
                        </button>

                        <PrimaryButton
                            :disabled="positionForm.processing"
                            class="min-h-11 rounded-xl bg-slate-950 px-5 text-sm font-semibold tracking-normal hover:bg-slate-800"
                        >
                            {{ positionForm.processing ? 'در حال ذخیره...' : editingPosition ? 'ذخیره تغییرات' : 'ایجاد سمت' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
