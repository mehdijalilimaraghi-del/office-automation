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
    parent?: {
        id: number;
        name: string;
    } | null;
}

const props = defineProps<{
    departments: Department[];
}>();

const editingDepartment = ref<Department | null>(null);

const form = useForm({
    parent_id: null as number | null,
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
    <Head title="دپارتمان‌ها" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    مدیریت دپارتمان‌ها
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

                <!-- Form -->
                <section
                    class="overflow-hidden rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200"
                >
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{
                                editingDepartment
                                    ? 'ویرایش دپارتمان'
                                    : 'ایجاد دپارتمان جدید'
                            }}
                        </h3>

                        <p class="mt-1 text-sm text-gray-500">
                            {{
                                editingDepartment
                                    ? 'اطلاعات دپارتمان را ویرایش کنید.'
                                    : 'اطلاعات دپارتمان جدید را وارد کنید.'
                            }}
                        </p>
                    </div>

                    <form
                        class="grid grid-cols-1 gap-5 md:grid-cols-2"
                        @submit.prevent="submit"
                    >
                        <!-- Parent -->
                        <div>
                            <label
                                for="parent_id"
                                class="mb-2 block text-sm font-medium text-gray-700"
                            >
                                دپارتمان والد
                            </label>

                            <select
                                id="parent_id"
                                v-model="form.parent_id"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option :value="null">
                                    بدون دپارتمان والد
                                </option>

                                <option
                                    v-for="department in parentOptions"
                                    :key="department.id"
                                    :value="department.id"
                                >
                                    {{ department.name }}
                                </option>
                            </select>

                            <p
                                v-if="form.errors.parent_id"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ form.errors.parent_id }}
                            </p>
                        </div>

                        <!-- Name -->
                        <div>
                            <label
                                for="name"
                                class="mb-2 block text-sm font-medium text-gray-700"
                            >
                                نام دپارتمان
                            </label>

                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                maxlength="150"
                                required
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />

                            <p
                                v-if="form.errors.name"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <!-- Code -->
                        <div>
                            <label
                                for="code"
                                class="mb-2 block text-sm font-medium text-gray-700"
                            >
                                کد دپارتمان
                            </label>

                            <input
                                id="code"
                                v-model="form.code"
                                type="text"
                                maxlength="50"
                                dir="ltr"
                                class="block w-full rounded-md border-gray-300 text-left shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />

                            <p
                                v-if="form.errors.code"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ form.errors.code }}
                            </p>
                        </div>

                        <!-- Status -->
                        <div class="flex items-center pt-7">
                            <label
                                class="inline-flex cursor-pointer items-center"
                            >
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />

                                <span class="mr-2 text-sm text-gray-700">
                                    دپارتمان فعال باشد
                                </span>
                            </label>
                        </div>

                        <!-- Description -->
                        <div class="md:col-span-2">
                            <label
                                for="description"
                                class="mb-2 block text-sm font-medium text-gray-700"
                            >
                                توضیحات
                            </label>

                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            ></textarea>

                            <p
                                v-if="form.errors.description"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ form.errors.description }}
                            </p>
                        </div>

                        <!-- Buttons -->
                        <div
                            class="flex items-center gap-3 border-t pt-5 md:col-span-2"
                        >
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{
                                    form.processing
                                        ? 'در حال ذخیره...'
                                        : editingDepartment
                                            ? 'ذخیره تغییرات'
                                            : 'ایجاد دپارتمان'
                                }}
                            </button>

                            <button
                                v-if="editingDepartment"
                                type="button"
                                :disabled="form.processing"
                                class="rounded-md border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-50"
                                @click="cancelEdit"
                            >
                                انصراف
                            </button>

                            <button
                                v-else
                                type="button"
                                :disabled="form.processing"
                                class="rounded-md border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-50"
                                @click="resetForm"
                            >
                                پاک کردن
                            </button>
                        </div>
                    </form>
                </section>

                <!-- List -->
                <section
                    class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200"
                >
                    <div
                        class="flex items-center justify-between border-b border-gray-200 px-6 py-5"
                    >
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">
                                فهرست دپارتمان‌ها
                            </h3>

                            <p class="mt-1 text-sm text-gray-500">
                                تعداد:
                                {{ props.departments.length }}
                                دپارتمان
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="props.departments.length === 0"
                        class="px-6 py-12 text-center text-sm text-gray-500"
                    >
                        هنوز هیچ دپارتمانی ثبت نشده است.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-right text-xs font-medium text-gray-500"
                                    >
                                        نام دپارتمان
                                    </th>

                                    <th
                                        class="px-6 py-3 text-right text-xs font-medium text-gray-500"
                                    >
                                        کد
                                    </th>

                                    <th
                                        class="px-6 py-3 text-right text-xs font-medium text-gray-500"
                                    >
                                        دپارتمان والد
                                    </th>

                                    <th
                                        class="px-6 py-3 text-right text-xs font-medium text-gray-500"
                                    >
                                        وضعیت
                                    </th>

                                    <th
                                        class="px-6 py-3 text-right text-xs font-medium text-gray-500"
                                    >
                                        توضیحات
                                    </th>

                                    <th
                                        class="px-6 py-3 text-right text-xs font-medium text-gray-500"
                                    >
                                        عملیات
                                    </th>
                                </tr>
                            </thead>

                            <tbody
                                class="divide-y divide-gray-200 bg-white"
                            >
                                <tr
                                    v-for="department in props.departments"
                                    :key="department.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td
                                        class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900"
                                    >
                                        {{ department.name }}
                                    </td>

                                    <td
                                        class="whitespace-nowrap px-6 py-4 text-sm text-gray-600"
                                        dir="ltr"
                                    >
                                        {{ department.code || '---' }}
                                    </td>

                                    <td
                                        class="whitespace-nowrap px-6 py-4 text-sm text-gray-600"
                                    >
                                        {{ getParentName(department) }}
                                    </td>

                                    <td
                                        class="whitespace-nowrap px-6 py-4 text-sm"
                                    >
                                        <span
                                            v-if="department.is_active"
                                            class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700"
                                        >
                                            فعال
                                        </span>

                                        <span
                                            v-else
                                            class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600"
                                        >
                                            غیرفعال
                                        </span>
                                    </td>

                                    <td
                                        class="max-w-md px-6 py-4 text-sm text-gray-600"
                                    >
                                        {{ department.description || '---' }}
                                    </td>

                                    <td
                                        class="whitespace-nowrap px-6 py-4 text-sm"
                                    >
                                        <div class="flex items-center gap-2">
                                            <button
                                                type="button"
                                                class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                                @click="startEdit(department)"
                                            >
                                                ویرایش
                                            </button>

                                            <button
                                                type="button"
                                                class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                                @click="toggleStatus(department)"
                                            >
                                                {{
                                                    department.is_active
                                                        ? 'غیرفعال'
                                                        : 'فعال'
                                                }}
                                            </button>

                                            <button
                                                type="button"
                                                class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
                                                @click="deleteDepartment(department)"
                                            >
                                                حذف
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>