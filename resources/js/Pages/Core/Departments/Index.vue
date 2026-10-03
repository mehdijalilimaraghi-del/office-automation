<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

interface Department {
    id: number;
    parent_id: number | null;
    name: string;
    code: string | null;
    description: string | null;
    is_active: boolean;
    manager_id: number | null;
    manager?: {
        id: number;
        name: string;
    } | null;
    parent?: {
        id: number;
        name: string;
    } | null;
}

interface User {
    id: number;
    name: string;
}

const props = defineProps<{
    departments: Department[];
    users: User[];
}>();

const editingDepartment = ref<Department | null>(null);

const form = useForm({
    parent_id: null as number | null,
    manager_id: null as number | null,
    name: '',
    code: '',
    description: '',
    is_active: true,
});

const parentOptions = computed(() => {
    if (!editingDepartment.value) {
        return props.departments;
    }

    const excludedIds = new Set([
        editingDepartment.value.id,
        ...getDescendantIds(editingDepartment.value.id),
    ]);

    return props.departments.filter(
        (department) => !excludedIds.has(department.id),
    );
});

const getDescendantIds = (departmentId: number): number[] => {
    const result: number[] = [];

    const collect = (parentId: number) => {
        for (const department of props.departments) {
            if (department.parent_id === parentId) {
                result.push(department.id);
                collect(department.id);
            }
        }
    };

    collect(departmentId);

    return result;
};

const submit = () => {
    if (editingDepartment.value) {
        form.put(
            route('departments.update', editingDepartment.value.id),
            {
                preserveScroll: true,
                onSuccess: () => {
                    cancelEdit();
                },
            },
        );

        return;
    }

    form.post(route('departments.store'), {
        preserveScroll: true,
        onSuccess: () => {
            resetForm();
        },
    });
};

const startEdit = (department: Department) => {
    editingDepartment.value = department;

    form.parent_id = department.parent_id;
    form.manager_id = department.manager_id;
    form.name = department.name;
    form.code = department.code ?? '';
    form.description = department.description ?? '';
    form.is_active = department.is_active;

    form.clearErrors();
};

const cancelEdit = () => {
    editingDepartment.value = null;
    resetForm();
};

const resetForm = () => {
    form.reset();
    form.is_active = true;
    form.clearErrors();
};

const deleteDepartment = (department: Department) => {
    if (!window.confirm(
        `آیا از حذف دپارتمان «${department.name}» مطمئن هستید؟`,
    )) {
        return;
    }

    form.delete(
        route('departments.destroy', department.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                if (
                    editingDepartment.value?.id === department.id
                ) {
                    cancelEdit();
                }
            },
        },
    );
};

const toggleStatus = (department: Department) => {
    form.patch(
        route('departments.toggle-status', department.id),
        {
            preserveScroll: true,
        },
    );
};

const getParentName = (department: Department): string => {
    return department.parent?.name ?? '---';
};
</script>

<template>
    <Head title="ساختار سازمانی" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="flex items-center gap-2 text-xs font-medium text-slate-500"><span class="h-2 w-2 rounded-full bg-teal-500"></span>مدیریت سازمان</div>
                    <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">ساختار سازمانی</h2>
                    <p class="mt-1 text-sm text-slate-500">دپارتمان‌ها، سلسله‌مراتب و مدیران واحدها را در یک نمای یکپارچه مدیریت کنید.</p>
                </div>
                <span class="w-fit rounded-full bg-teal-50 px-3 py-1.5 text-xs font-semibold text-teal-700">{{ props.departments.length }} واحد سازمانی</span>
            </div>
        </template>

        <div class="min-h-[calc(100vh-8rem)] bg-slate-50 py-6 md:py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-5">
                        <h3 class="font-bold text-slate-900">{{ editingDepartment ? 'ویرایش دپارتمان' : 'ایجاد دپارتمان جدید' }}</h3>
                        <p class="mt-1 text-xs text-slate-500">{{ editingDepartment ? 'اطلاعات واحد انتخاب‌شده را به‌روزرسانی کنید.' : 'واحد جدید را به ساختار سازمانی اضافه کنید.' }}</p>
                    </div>
                    <form class="grid gap-5 p-5 md:grid-cols-2" @submit.prevent="submit">
                        <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">نام دپارتمان</label><input v-model="form.name" type="text" maxlength="150" required class="w-full rounded-xl border-slate-200 bg-slate-50/50 focus:border-teal-500 focus:ring-teal-500" /><p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p></div>
                        <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">کد دپارتمان</label><input v-model="form.code" dir="ltr" type="text" maxlength="50" class="w-full rounded-xl border-slate-200 bg-slate-50/50 text-left focus:border-teal-500 focus:ring-teal-500" /><p v-if="form.errors.code" class="mt-1 text-xs text-red-600">{{ form.errors.code }}</p></div>
                        <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">دپارتمان والد</label><select v-model="form.parent_id" class="w-full rounded-xl border-slate-200 bg-white focus:border-teal-500 focus:ring-teal-500"><option :value="null">بدون دپارتمان والد</option><option v-for="department in parentOptions" :key="department.id" :value="department.id">{{ department.name }}</option></select><p v-if="form.errors.parent_id" class="mt-1 text-xs text-red-600">{{ form.errors.parent_id }}</p></div>
                        <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">مدیر دپارتمان</label><select v-model="form.manager_id" class="w-full rounded-xl border-slate-200 bg-white focus:border-teal-500 focus:ring-teal-500"><option :value="null">بدون مدیر</option><option v-for="user in props.users" :key="user.id" :value="user.id">{{ user.name }}</option></select><p v-if="form.errors.manager_id" class="mt-1 text-xs text-red-600">{{ form.errors.manager_id }}</p></div>
                        <div class="md:col-span-2"><label class="mb-1.5 block text-sm font-semibold text-slate-700">توضیحات</label><textarea v-model="form.description" rows="3" class="w-full rounded-xl border-slate-200 bg-slate-50/50 focus:border-teal-500 focus:ring-teal-500"></textarea><p v-if="form.errors.description" class="mt-1 text-xs text-red-600">{{ form.errors.description }}</p></div>
                        <div class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50/50 p-4 md:col-span-2">
                            <div><p class="text-sm font-semibold text-slate-800">وضعیت دپارتمان</p><p class="mt-1 text-xs text-slate-500">واحدهای غیرفعال در فرآیندهای جدید استفاده نشوند.</p></div>
                            <label class="relative inline-flex cursor-pointer items-center"><input v-model="form.is_active" type="checkbox" class="peer sr-only" /><span class="h-6 w-11 rounded-full bg-slate-300 after:absolute after:start-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all peer-checked:bg-teal-600 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full"></span></label>
                        </div>
                        <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end md:col-span-2">
                            <button v-if="editingDepartment" type="button" :disabled="form.processing" class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50" @click="cancelEdit">انصراف</button>
                            <button v-else type="button" :disabled="form.processing" class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50" @click="resetForm">پاک کردن</button>
                            <button type="submit" :disabled="form.processing" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50">{{ form.processing ? 'در حال ذخیره...' : editingDepartment ? 'ذخیره تغییرات' : 'ایجاد دپارتمان' }}</button>
                        </div>
                    </form>
                </section>

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
                        <div><h3 class="font-bold text-slate-900">فهرست واحدها</h3><p class="mt-1 text-xs text-slate-500">سلسله‌مراتب والد، مدیر و وضعیت هر واحد را بررسی کنید.</p></div>
                        <span class="w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ props.departments.length }} واحد</span>
                    </div>
                    <div v-if="props.departments.length === 0" class="px-6 py-16 text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-teal-50 text-2xl text-teal-600">⌘</div>
                        <h4 class="mt-4 font-semibold text-slate-900">ساختار سازمانی خالی است</h4>
                        <p class="mt-1 text-sm text-slate-500">اولین دپارتمان را از فرم بالا ایجاد کنید.</p>
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-[900px] w-full text-right">
                            <thead class="bg-slate-50 text-xs font-semibold text-slate-500"><tr><th class="px-5 py-3.5">واحد</th><th class="px-5 py-3.5">والد</th><th class="px-5 py-3.5">مدیر</th><th class="px-5 py-3.5">وضعیت</th><th class="px-5 py-3.5">توضیحات</th><th class="px-5 py-3.5">عملیات</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="department in props.departments" :key="department.id" class="transition hover:bg-slate-50/80">
                                    <td class="px-5 py-4"><div class="flex items-center gap-3"><div class="h-9 w-1 rounded-full bg-teal-400"></div><div><div class="font-semibold text-slate-900">{{ department.name }}</div><div dir="ltr" class="mt-0.5 text-xs text-slate-400 text-right">{{ department.code || 'بدون کد' }}</div></div></div></td>
                                    <td class="px-5 py-4 text-sm text-slate-600">{{ getParentName(department) }}</td>
                                    <td class="px-5 py-4 text-sm text-slate-600">{{ department.manager?.name || 'بدون مدیر' }}</td>
                                    <td class="px-5 py-4"><span :class="department.is_active ? 'bg-emerald-50 text-emerald-700 ring-emerald-100' : 'bg-slate-100 text-slate-500 ring-slate-200'" class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1">{{ department.is_active ? 'فعال' : 'غیرفعال' }}</span></td>
                                    <td class="max-w-sm px-5 py-4 text-sm text-slate-500">{{ department.description || '—' }}</td>
                                    <td class="px-5 py-4"><div class="flex flex-wrap gap-2"><button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="startEdit(department)">ویرایش</button><button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="toggleStatus(department)">{{ department.is_active ? 'غیرفعال' : 'فعال' }}</button><button type="button" class="rounded-lg border border-red-100 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50" @click="deleteDepartment(department)">حذف</button></div></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>