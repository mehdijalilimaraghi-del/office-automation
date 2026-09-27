<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Department {
    id: number;
    name: string;
    code: string | null;
    is_active: boolean;
}

interface Role {
    id: number;
    name: string;
    code: string | null;
    is_active: boolean;
}

interface User {
    id: number;
    name: string;
    email: string;
    status: 'active' | 'inactive';
    department: Department | null;
    roles: Role[];
}

const props = defineProps<{
    users: User[];
    departments: Department[];
    roles: Role[];
}>();

const showForm = ref(false);
const editingUser = ref<User | null>(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    department_id: '',
    status: 'active' as 'active' | 'inactive',
    role_ids: [] as number[],
});

function openCreate(): void {
    editingUser.value = null;

    form.reset();
    form.clearErrors();

    form.status = 'active';
    form.role_ids = [];

    showForm.value = true;
}

function openEdit(user: User): void {
    editingUser.value = user;

    form.clearErrors();

    form.name = user.name;
    form.email = user.email;
    form.password = '';
    form.password_confirmation = '';
    form.department_id = user.department?.id
        ? String(user.department.id)
        : '';
    form.status = user.status;
    form.role_ids = user.roles.map((role) => role.id);

    showForm.value = true;
}

function closeForm(): void {
    showForm.value = false;
    editingUser.value = null;
    form.reset();
    form.clearErrors();
}

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeForm(),
    };

    if (editingUser.value) {
        form.put(
            route('users.update', editingUser.value.id),
            options,
        );

        return;
    }

    form.post(route('users.store'), options);
}

function toggleRole(roleId: number): void {
    if (form.role_ids.includes(roleId)) {
        form.role_ids = form.role_ids.filter(
            (id) => id !== roleId,
        );

        return;
    }

    form.role_ids = [
        ...form.role_ids,
        roleId,
    ];
}

function toggleStatus(user: User): void {
    if (user.id === currentUserId()) {
        return;
    }

    if (
        !confirm(
            `آیا از تغییر وضعیت «${user.name}» مطمئن هستید؟`,
        )
    ) {
        return;
    }

    useForm({}).patch(
        route('users.toggle-status', user.id),
        {
            preserveScroll: true,
        },
    );
}

function deleteUser(user: User): void {
    if (user.id === currentUserId()) {
        return;
    }

    if (
        !confirm(
            `آیا از حذف کاربر «${user.name}» مطمئن هستید؟`,
        )
    ) {
        return;
    }

    useForm({}).delete(
        route('users.destroy', user.id),
        {
            preserveScroll: true,
        },
    );
}

function currentUserId(): number | null {
    return null;
}
</script>

<template>
    <Head title="مدیریت کاربران" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2
                    class="text-xl font-semibold leading-tight text-gray-800"
                >
                    مدیریت کاربران
                </h2>

                <button
                    type="button"
                    class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800"
                    @click="openCreate"
                >
                    کاربر جدید
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"
                >
                    <div class="overflow-x-auto">
                        <table
                            class="w-full text-right text-sm"
                        >
                            <thead
                                class="border-b bg-gray-50 text-gray-700"
                            >
                                <tr>
                                    <th class="px-4 py-3">
                                        نام
                                    </th>

                                    <th class="px-4 py-3">
                                        ایمیل
                                    </th>

                                    <th class="px-4 py-3">
                                        واحد
                                    </th>

                                    <th class="px-4 py-3">
                                        نقش‌ها
                                    </th>

                                    <th class="px-4 py-3">
                                        وضعیت
                                    </th>

                                    <th class="px-4 py-3">
                                        عملیات
                                    </th>
                                </tr>
                            </thead>

                            <tbody
                                class="divide-y divide-gray-100"
                            >
                                <tr
                                    v-for="user in props.users"
                                    :key="user.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td
                                        class="px-4 py-3 font-medium text-gray-900"
                                    >
                                        {{ user.name }}
                                    </td>

                                    <td
                                        dir="ltr"
                                        class="px-4 py-3 text-right text-gray-600"
                                    >
                                        {{ user.email }}
                                    </td>

                                    <td
                                        class="px-4 py-3 text-gray-600"
                                    >
                                        {{
                                            user.department?.name ??
                                            '—'
                                        }}
                                    </td>

                                    <td
                                        class="px-4 py-3"
                                    >
                                        <div
                                            class="flex flex-wrap gap-1"
                                        >
                                            <span
                                                v-for="role in user.roles"
                                                :key="role.id"
                                                class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-700"
                                            >
                                                {{ role.name }}
                                            </span>

                                            <span
                                                v-if="user.roles.length === 0"
                                                class="text-gray-400"
                                            >
                                                بدون نقش
                                            </span>
                                        </div>
                                    </td>

                                    <td class="px-4 py-3">
                                        <span
                                            v-if="user.status === 'active'"
                                            class="rounded-full bg-green-100 px-2 py-1 text-xs text-green-700"
                                        >
                                            فعال
                                        </span>

                                        <span
                                            v-else
                                            class="rounded-full bg-red-100 px-2 py-1 text-xs text-red-700"
                                        >
                                            غیرفعال
                                        </span>
                                    </td>

                                    <td class="px-4 py-3">
                                        <div
                                            class="flex flex-wrap gap-2"
                                        >
                                            <button
                                                type="button"
                                                class="rounded-md border px-3 py-1.5 text-xs hover:bg-gray-50"
                                                @click="openEdit(user)"
                                            >
                                                ویرایش
                                            </button>

                                            <button
                                                type="button"
                                                class="rounded-md border px-3 py-1.5 text-xs hover:bg-gray-50"
                                                @click="toggleStatus(user)"
                                            >
                                                {{
                                                    user.status === 'active'
                                                        ? 'غیرفعال'
                                                        : 'فعال'
                                                }}
                                            </button>

                                            <button
                                                type="button"
                                                class="rounded-md border border-red-200 px-3 py-1.5 text-xs text-red-600 hover:bg-red-50"
                                                @click="deleteUser(user)"
                                            >
                                                حذف
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="props.users.length === 0">
                                    <td
                                        colspan="6"
                                        class="px-4 py-10 text-center text-gray-500"
                                    >
                                        کاربری ثبت نشده است.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div
                    v-if="showForm"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                >
                    <div
                        class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white shadow-xl"
                    >
                        <div
                            class="flex items-center justify-between border-b px-6 py-4"
                        >
                            <h3
                                class="text-lg font-semibold text-gray-900"
                            >
                                {{
                                    editingUser
                                        ? 'ویرایش کاربر'
                                        : 'ایجاد کاربر'
                                }}
                            </h3>

                            <button
                                type="button"
                                class="text-gray-500 hover:text-gray-800"
                                @click="closeForm"
                            >
                                ×
                            </button>
                        </div>

                        <form
                            class="space-y-5 p-6"
                            @submit.prevent="submit"
                        >
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium"
                                >
                                    نام
                                </label>

                                <input
                                    v-model="form.name"
                                    type="text"
                                    class="w-full rounded-lg border-gray-300"
                                />

                                <p
                                    v-if="form.errors.name"
                                    class="mt-1 text-xs text-red-600"
                                >
                                    {{ form.errors.name }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium"
                                >
                                    ایمیل
                                </label>

                                <input
                                    v-model="form.email"
                                    dir="ltr"
                                    type="email"
                                    class="w-full rounded-lg border-gray-300 text-left"
                                />

                                <p
                                    v-if="form.errors.email"
                                    class="mt-1 text-xs text-red-600"
                                >
                                    {{ form.errors.email }}
                                </p>
                            </div>

                            <div
                                class="grid gap-4 md:grid-cols-2"
                            >
                                <div>
                                    <label
                                        class="mb-1 block text-sm font-medium"
                                    >
                                        {{
                                            editingUser
                                                ? 'رمز عبور جدید'
                                                : 'رمز عبور'
                                        }}
                                    </label>

                                    <input
                                        v-model="form.password"
                                        dir="ltr"
                                        type="password"
                                        class="w-full rounded-lg border-gray-300 text-left"
                                    />

                                    <p
                                        v-if="form.errors.password"
                                        class="mt-1 text-xs text-red-600"
                                    >
                                        {{ form.errors.password }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        class="mb-1 block text-sm font-medium"
                                    >
                                        تکرار رمز عبور
                                    </label>

                                    <input
                                        v-model="form.password_confirmation"
                                        dir="ltr"
                                        type="password"
                                        class="w-full rounded-lg border-gray-300 text-left"
                                    />
                                </div>
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium"
                                >
                                    واحد سازمانی
                                </label>

                                <select
                                    v-model="form.department_id"
                                    class="w-full rounded-lg border-gray-300"
                                >
                                    <option value="">
                                        انتخاب واحد
                                    </option>

                                    <option
                                        v-for="department in props.departments"
                                        :key="department.id"
                                        :value="String(department.id)"
                                        :disabled="!department.is_active"
                                    >
                                        {{ department.name }}
                                        {{
                                            !department.is_active
                                                ? ' (غیرفعال)'
                                                : ''
                                        }}
                                    </option>
                                </select>

                                <p
                                    v-if="form.errors.department_id"
                                    class="mt-1 text-xs text-red-600"
                                >
                                    {{ form.errors.department_id }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="mb-2 block text-sm font-medium"
                                >
                                    نقش‌ها
                                </label>

                                <div
                                    class="grid gap-2 rounded-lg border p-3 md:grid-cols-2"
                                >
                                    <label
                                        v-for="role in props.roles"
                                        :key="role.id"
                                        class="flex cursor-pointer items-center gap-2 rounded-md p-2 hover:bg-gray-50"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="form.role_ids.includes(role.id)"
                                            :disabled="!role.is_active"
                                            @change="toggleRole(role.id)"
                                        />

                                        <span>
                                            {{ role.name }}
                                        </span>

                                        <span
                                            v-if="!role.is_active"
                                            class="text-xs text-gray-400"
                                        >
                                            غیرفعال
                                        </span>
                                    </label>
                                </div>

                                <p
                                    v-if="form.errors.role_ids"
                                    class="mt-1 text-xs text-red-600"
                                >
                                    {{ form.errors.role_ids }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="mb-2 block text-sm font-medium"
                                >
                                    وضعیت
                                </label>

                                <div class="flex gap-6">
                                    <label
                                        class="flex items-center gap-2"
                                    >
                                        <input
                                            v-model="form.status"
                                            type="radio"
                                            value="active"
                                        />

                                        فعال
                                    </label>

                                    <label
                                        class="flex items-center gap-2"
                                    >
                                        <input
                                            v-model="form.status"
                                            type="radio"
                                            value="inactive"
                                        />

                                        غیرفعال
                                    </label>
                                </div>
                            </div>

                            <div
                                class="flex justify-end gap-3 border-t pt-5"
                            >
                                <button
                                    type="button"
                                    class="rounded-lg border px-4 py-2 text-sm hover:bg-gray-50"
                                    @click="closeForm"
                                >
                                    انصراف
                                </button>

                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="rounded-lg bg-gray-900 px-5 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50"
                                >
                                    {{
                                        form.processing
                                            ? 'در حال ذخیره...'
                                            : 'ذخیره'
                                    }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>