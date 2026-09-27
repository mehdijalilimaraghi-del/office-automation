<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Permission {
    id: number;
    name: string;
    code: string;
    description: string | null;
    is_active: boolean;
    is_system: boolean;
}

const props = defineProps<{
    permissions: Permission[];
}>();

const editingPermission = ref<Permission | null>(null);

const form = useForm({
    name: '',
    code: '',
    description: '',
    is_active: true,
});

const isEditing = computed(() => editingPermission.value !== null);

function resetForm(): void {
    editingPermission.value = null;

    form.reset();
    form.clearErrors();

    form.name = '';
    form.code = '';
    form.description = '';
    form.is_active = true;
}

function startEdit(permission: Permission): void {
    if (permission.is_system) {
        return;
    }

    editingPermission.value = permission;

    form.clearErrors();

    form.name = permission.name;
    form.code = permission.code;
    form.description = permission.description ?? '';
    form.is_active = permission.is_active;
}

function submit(): void {
    if (editingPermission.value) {
        form.put(
            route(
                'permissions.update',
                editingPermission.value.id
            ),
            {
                preserveScroll: true,
                onSuccess: () => {
                    resetForm();
                },
            }
        );

        return;
    }

    form.post(route('permissions.store'), {
        preserveScroll: true,
        onSuccess: () => {
            resetForm();
        },
    });
}

function remove(permission: Permission): void {
    if (permission.is_system) {
        return;
    }

    if (
        !window.confirm(
            `آیا از حذف مجوز «${permission.name}» مطمئن هستید؟`
        )
    ) {
        return;
    }

    form.delete(
        route('permissions.destroy', permission.id),
        {
            preserveScroll: true,
        }
    );
}

function toggleStatus(permission: Permission): void {
    if (permission.is_system) {
        return;
    }

    form.patch(
        route(
            'permissions.toggle-status',
            permission.id
        ),
        {
            preserveScroll: true,
        }
    );
}
</script>

<template>
    <Head title="مجوزها" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    مدیریت مجوزها
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-6 lg:grid-cols-3">
                    <div
                        class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200 lg:col-span-1"
                    >
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">
                                {{ isEditing ? 'ویرایش مجوز' : 'ایجاد مجوز' }}
                            </h3>

                            <p class="mt-1 text-sm text-gray-500">
                                مشخصات مجوز را وارد کنید.
                            </p>
                        </div>

                        <form
                            class="space-y-5"
                            @submit.prevent="submit"
                        >
                            <div>
                                <label
                                    for="name"
                                    class="mb-1 block text-sm font-medium text-gray-700"
                                >
                                    نام مجوز
                                </label>

                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />

                                <p
                                    v-if="form.errors.name"
                                    class="mt-1 text-sm text-red-600"
                                >
                                    {{ form.errors.name }}
                                </p>
                            </div>

                            <div>
                                <label
                                    for="code"
                                    class="mb-1 block text-sm font-medium text-gray-700"
                                >
                                    کد مجوز
                                </label>

                                <input
                                    id="code"
                                    v-model="form.code"
                                    type="text"
                                    dir="ltr"
                                    class="block w-full rounded-md border-gray-300 text-left shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="projects.view"
                                />

                                <p class="mt-1 text-xs text-gray-500">
                                    فقط حروف انگلیسی کوچک، عدد و . _ : -
                                </p>

                                <p
                                    v-if="form.errors.code"
                                    class="mt-1 text-sm text-red-600"
                                >
                                    {{ form.errors.code }}
                                </p>
                            </div>

                            <div>
                                <label
                                    for="description"
                                    class="mb-1 block text-sm font-medium text-gray-700"
                                >
                                    توضیحات
                                </label>

                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="4"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                ></textarea>

                                <p
                                    v-if="form.errors.description"
                                    class="mt-1 text-sm text-red-600"
                                >
                                    {{ form.errors.description }}
                                </p>
                            </div>

                            <label
                                class="flex cursor-pointer items-center gap-2"
                            >
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                />

                                <span class="text-sm text-gray-700">
                                    مجوز فعال باشد
                                </span>
                            </label>

                            <div class="flex gap-2">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {{
                                        form.processing
                                            ? 'در حال ذخیره...'
                                            : isEditing
                                              ? 'ذخیره تغییرات'
                                              : 'ایجاد مجوز'
                                    }}
                                </button>

                                <button
                                    v-if="isEditing"
                                    type="button"
                                    class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                    @click="resetForm"
                                >
                                    انصراف
                                </button>
                            </div>
                        </form>
                    </div>

                    <div
                        class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200 lg:col-span-2"
                    >
                        <div
                            class="border-b border-gray-200 px-6 py-5"
                        >
                            <h3 class="text-lg font-semibold text-gray-900">
                                فهرست مجوزها
                            </h3>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-right text-xs font-medium text-gray-500"
                                        >
                                            نام
                                        </th>

                                        <th
                                            class="px-6 py-3 text-right text-xs font-medium text-gray-500"
                                        >
                                            کد
                                        </th>

                                        <th
                                            class="px-6 py-3 text-right text-xs font-medium text-gray-500"
                                        >
                                            وضعیت
                                        </th>

                                        <th
                                            class="px-6 py-3 text-right text-xs font-medium text-gray-500"
                                        >
                                            نوع
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
                                        v-for="permission in props.permissions"
                                        :key="permission.id"
                                    >
                                        <td
                                            class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900"
                                        >
                                            {{ permission.name }}
                                        </td>

                                        <td
                                            class="whitespace-nowrap px-6 py-4 text-left text-sm text-gray-600"
                                            dir="ltr"
                                        >
                                            {{ permission.code }}
                                        </td>

                                        <td class="whitespace-nowrap px-6 py-4">
                                            <span
                                                class="rounded-full px-2 py-1 text-xs font-medium"
                                                :class="
                                                    permission.is_active
                                                        ? 'bg-green-100 text-green-700'
                                                        : 'bg-gray-100 text-gray-600'
                                                "
                                            >
                                                {{
                                                    permission.is_active
                                                        ? 'فعال'
                                                        : 'غیرفعال'
                                                }}
                                            </span>
                                        </td>

                                        <td class="whitespace-nowrap px-6 py-4">
                                            <span
                                                v-if="permission.is_system"
                                                class="rounded-full bg-purple-100 px-2 py-1 text-xs font-medium text-purple-700"
                                            >
                                                سیستمی
                                            </span>

                                            <span
                                                v-else
                                                class="text-sm text-gray-500"
                                            >
                                                عادی
                                            </span>
                                        </td>

                                        <td class="whitespace-nowrap px-6 py-4">
                                            <div class="flex gap-2">
                                                <template
                                                    v-if="
                                                        !permission.is_system
                                                    "
                                                >
                                                    <button
                                                        type="button"
                                                        class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                        @click="
                                                            startEdit(
                                                                permission
                                                            )
                                                        "
                                                    >
                                                        ویرایش
                                                    </button>

                                                    <button
                                                        type="button"
                                                        class="text-sm font-medium text-amber-600 hover:text-amber-800"
                                                        @click="
                                                            toggleStatus(
                                                                permission
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            permission.is_active
                                                                ? 'غیرفعال'
                                                                : 'فعال'
                                                        }}
                                                    </button>

                                                    <button
                                                        type="button"
                                                        class="text-sm font-medium text-red-600 hover:text-red-800"
                                                        @click="
                                                            remove(permission)
                                                        "
                                                    >
                                                        حذف
                                                    </button>
                                                </template>

                                                <span
                                                    v-else
                                                    class="text-sm text-gray-400"
                                                >
                                                    محافظت‌شده
                                                </span>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr
                                        v-if="
                                            props.permissions.length === 0
                                        "
                                    >
                                        <td
                                            colspan="5"
                                            class="px-6 py-10 text-center text-sm text-gray-500"
                                        >
                                            هنوز مجوزی ثبت نشده است.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>