<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

interface Role {
    id: number;
    name: string;
    code: string | null;
    description: string | null;
    is_active: boolean;
    is_system: boolean;
}

const props = defineProps<{
    roles: Role[];
}>();

const editingRole = ref<Role | null>(null);

const form = useForm({
    name: '',
    code: '',
    description: '',
    is_active: true,
});

const submit = () => {
    if (editingRole.value) {
        form.put(
            route('roles.update', editingRole.value.id),
            {
                preserveScroll: true,
                onSuccess: () => {
                    cancelEdit();
                },
            },
        );

        return;
    }

    form.post(route('roles.store'), {
        preserveScroll: true,
        onSuccess: () => {
            resetForm();
        },
    });
};

const startEdit = (role: Role) => {
    if (role.is_system) {
        return;
    }

    editingRole.value = role;

    form.name = role.name;
    form.code = role.code ?? '';
    form.description = role.description ?? '';
    form.is_active = role.is_active;

    form.clearErrors();
};

const cancelEdit = () => {
    editingRole.value = null;
    resetForm();
};

const resetForm = () => {
    form.reset();
    form.is_active = true;
    form.clearErrors();
};

const deleteRole = (role: Role) => {
    if (role.is_system) {
        return;
    }

    if (
        !window.confirm(
            `آیا از حذف نقش «${role.name}» مطمئن هستید؟`,
        )
    ) {
        return;
    }

    form.delete(
        route('roles.destroy', role.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                if (editingRole.value?.id === role.id) {
                    cancelEdit();
                }
            },
        },
    );
};

const toggleStatus = (role: Role) => {
    if (role.is_system) {
        return;
    }

    form.patch(
        route('roles.toggle-status', role.id),
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head title="نقش‌ها" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    مدیریت نقش‌ها
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
                                editingRole
                                    ? 'ویرایش نقش'
                                    : 'ایجاد نقش جدید'
                            }}
                        </h3>

                        <p class="mt-1 text-sm text-gray-500">
                            {{
                                editingRole
                                    ? 'اطلاعات نقش را ویرایش کنید.'
                                    : 'اطلاعات نقش جدید را وارد کنید.'
                            }}
                        </p>
                    </div>

                    <form
                        class="grid grid-cols-1 gap-5 md:grid-cols-2"
                        @submit.prevent="submit"
                    >
                        <!-- Name -->
                        <div>
                            <label
                                for="name"
                                class="mb-2 block text-sm font-medium text-gray-700"
                            >
                                نام نقش
                            </label>

                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                maxlength="100"
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
                                کد نقش
                            </label>

                            <input
                                id="code"
                                v-model="form.code"
                                type="text"
                                maxlength="50"
                                dir="ltr"
                                placeholder="PROJECT_MANAGER"
                                class="block w-full rounded-md border-gray-300 text-left uppercase shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />

                            <p class="mt-1 text-xs text-gray-500">
                                فقط حروف، اعداد، خط تیره و زیرخط.
                            </p>

                            <p
                                v-if="form.errors.code"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ form.errors.code }}
                            </p>
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

                        <!-- Status -->
                        <div class="flex items-center">
                            <label
                                class="inline-flex cursor-pointer items-center"
                            >
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />

                                <span class="mr-2 text-sm text-gray-700">
                                    نقش فعال باشد
                                </span>
                            </label>
                        </div>

                        <!-- Buttons -->
                        <div
                            class="flex items-center justify-end gap-3 md:col-span-2"
                        >
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{
                                    form.processing
                                        ? 'در حال ذخیره...'
                                        : editingRole
                                            ? 'ذخیره تغییرات'
                                            : 'ایجاد نقش'
                                }}
                            </button>

                            <button
                                v-if="editingRole"
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
                                فهرست نقش‌ها
                            </h3>

                            <p class="mt-1 text-sm text-gray-500">
                                تعداد:
                                {{ props.roles.length }}
                                نقش
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="props.roles.length === 0"
                        class="px-6 py-12 text-center text-sm text-gray-500"
                    >
                        هنوز هیچ نقشی ثبت نشده است.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-right text-xs font-medium text-gray-500"
                                    >
                                        نام نقش
                                    </th>

                                    <th
                                        class="px-6 py-3 text-right text-xs font-medium text-gray-500"
                                    >
                                        کد
                                    </th>

                                    <th
                                        class="px-6 py-3 text-right text-xs font-medium text-gray-500"
                                    >
                                        نوع
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
                                    v-for="role in props.roles"
                                    :key="role.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td
                                        class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900"
                                    >
                                        {{ role.name }}
                                    </td>

                                    <td
                                        class="whitespace-nowrap px-6 py-4 text-sm text-gray-600"
                                        dir="ltr"
                                    >
                                        {{ role.code || '---' }}
                                    </td>

                                    <td
                                        class="whitespace-nowrap px-6 py-4 text-sm"
                                    >
                                        <span
                                            v-if="role.is_system"
                                            class="inline-flex rounded-full bg-purple-100 px-2.5 py-1 text-xs font-medium text-purple-700"
                                        >
                                            سیستمی
                                        </span>

                                        <span
                                            v-else
                                            class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600"
                                        >
                                            عادی
                                        </span>
                                    </td>

                                    <td
                                        class="whitespace-nowrap px-6 py-4 text-sm"
                                    >
                                        <span
                                            v-if="role.is_active"
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
                                        {{ role.description || '---' }}
                                    </td>

                                    <td
                                        class="whitespace-nowrap px-6 py-4 text-sm"
                                    >
                                        <div
                                            v-if="role.is_system"
                                            class="text-xs text-gray-500"
                                        >
                                            نقش سیستمی
                                        </div>

                                        <div
                                            v-else
                                            class="flex items-center gap-2"
                                        >
                                            <button
                                                type="button"
                                                class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                                @click="startEdit(role)"
                                            >
                                                ویرایش
                                            </button>

                                            <button
                                                type="button"
                                                class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                                @click="toggleStatus(role)"
                                            >
                                                {{
                                                    role.is_active
                                                        ? 'غیرفعال'
                                                        : 'فعال'
                                                }}
                                            </button>

                                            <button
                                                type="button"
                                                class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
                                                @click="deleteRole(role)"
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