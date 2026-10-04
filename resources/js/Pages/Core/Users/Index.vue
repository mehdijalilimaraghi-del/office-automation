<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Department {
    id: number;
    name: string;
    code: string | null;
    is_active: boolean;
    pivot?: {
        is_primary: boolean;
        joined_at: string | null;
    };
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
    mobile: string | null;
    phone: string | null;
    extension: string | null;
    status: 'active' | 'inactive';
    departments: Department[];
    roles: Role[];
    positions: { id: number; name: string; department?: { id: number; name: string } | null; }[];
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
    mobile: '',
    phone: '',
    extension: '',
    password: '',
    password_confirmation: '',
    department_ids: [] as number[],
    primary_department_id: null as number | null,
    status: 'active' as 'active' | 'inactive',
    role_ids: [] as number[],
});

function openCreate(): void {
    editingUser.value = null;

    form.reset();
    form.clearErrors();

    form.status = 'active';
    form.department_ids = [];
    form.primary_department_id = null;
    form.role_ids = [];

    showForm.value = true;
}

function openEdit(user: User): void {
    editingUser.value = user;

    form.clearErrors();

    form.name = user.name;
    form.email = user.email;
    form.mobile = user.mobile ?? '';
    form.phone = user.phone ?? '';
    form.extension = user.extension ?? '';
    form.password = '';
    form.password_confirmation = '';
    form.department_ids = user.departments.map(
        (department) => department.id,
    );
    form.primary_department_id =
        user.departments.find(
            (department) => department.pivot?.is_primary,
        )?.id ?? null;
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

function toggleDepartment(departmentId: number): void {
    if (form.department_ids.includes(departmentId)) {
        form.department_ids = form.department_ids.filter(
            (id) => id !== departmentId,
        );

        if (form.primary_department_id === departmentId) {
            form.primary_department_id =
                form.department_ids[0] ?? null;
        }

        return;
    }

    form.department_ids = [
        ...form.department_ids,
        departmentId,
    ];

    if (form.primary_department_id === null) {
        form.primary_department_id = departmentId;
    }
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
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="flex items-center gap-2 text-xs font-medium text-slate-500">
                        <span class="h-2 w-2 rounded-full bg-teal-500"></span>
                        مدیریت سازمان
                    </div>
                    <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">کاربران سازمان</h2>
                    <p class="mt-1 text-sm text-slate-500">اعضا، واحدها، نقش‌ها و وضعیت دسترسی کاربران را مدیریت کنید.</p>
                </div>
                <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-slate-800" @click="openCreate">
                    <span class="text-lg leading-none">+</span>
                    کاربر جدید
                </button>
            </div>
        </template>

        <div class="min-h-[calc(100vh-8rem)] bg-slate-50 py-6 md:py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-medium text-slate-500">کل کاربران</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900">{{ props.users.length }}</p>
                    </div>
                    <div class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-5 shadow-sm">
                        <p class="text-xs font-medium text-emerald-700">کاربران فعال</p>
                        <p class="mt-2 text-2xl font-bold text-emerald-900">{{ props.users.filter(user => user.status === 'active').length }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-medium text-slate-500">کاربران دارای نقش</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900">{{ props.users.filter(user => user.roles.length > 0).length }}</p>
                    </div>
                </div>

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="font-bold text-slate-900">فهرست کاربران</h3>
                            <p class="mt-1 text-xs text-slate-500">برای مشاهده جزئیات یا مدیریت وضعیت، از عملیات هر ردیف استفاده کنید.</p>
                        </div>
                        <span class="w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ props.users.length }} کاربر</span>
                    </div>

                    <div v-if="props.users.length === 0" class="px-6 py-16 text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-teal-50 text-2xl text-teal-600">◎</div>
                        <h4 class="mt-4 font-semibold text-slate-900">هنوز کاربری ثبت نشده است</h4>
                        <p class="mt-1 text-sm text-slate-500">اولین کاربر سازمان را ایجاد کنید.</p>
                        <button type="button" class="mt-5 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white" @click="openCreate">ایجاد کاربر</button>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-[980px] w-full text-right">
                            <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                                <tr>
                                    <th class="px-5 py-3.5">کاربر</th>
                                    <th class="px-5 py-3.5">تماس</th>
                                    <th class="px-5 py-3.5">واحدهای سازمانی</th>
                                    <th class="px-5 py-3.5">سمت سازمانی</th><th class="px-5 py-3.5">نقش‌ها</th>
                                    <th class="px-5 py-3.5">وضعیت</th>
                                    <th class="px-5 py-3.5">عملیات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="user in props.users" :key="user.id" class="transition hover:bg-slate-50/80">
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-50 font-bold text-teal-700">{{ user.name.charAt(0) }}</div>
                                            <div class="min-w-0">
                                                <div class="font-semibold text-slate-900">{{ user.name }}</div>
                                                <div dir="ltr" class="truncate text-xs text-slate-500 text-right">{{ user.email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-slate-600">
                                        <div dir="ltr" class="text-right">{{ user.mobile || user.phone || '—' }}</div>
                                        <div v-if="user.extension" class="mt-1 text-xs text-slate-400">داخلی {{ user.extension }}</div>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-slate-600">
                                        <div class="flex max-w-xs flex-wrap gap-1.5">
                                            <span v-for="department in user.departments" :key="department.id" class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">{{ department.name }}</span>
                                            <span v-if="user.departments.length === 0" class="text-slate-400">بدون واحد</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex max-w-xs flex-wrap gap-1.5">
                                            <span v-for="role in user.roles" :key="role.id" class="rounded-full bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-700">{{ role.name }}</span>
                                            <span v-if="user.roles.length === 0" class="text-xs text-slate-400">بدون نقش</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span :class="user.status === 'active' ? 'bg-emerald-50 text-emerald-700 ring-emerald-100' : 'bg-slate-100 text-slate-500 ring-slate-200'" class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1">
                                            {{ user.status === 'active' ? 'فعال' : 'غیرفعال' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50" @click="openEdit(user)">ویرایش</button>
                                            <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50" @click="toggleStatus(user)">{{ user.status === 'active' ? 'غیرفعال' : 'فعال' }}</button>
                                            <button type="button" class="rounded-lg border border-red-100 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-50" @click="deleteUser(user)">حذف</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm">
            <div class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white/95 px-6 py-4 backdrop-blur">
                    <div>
                        <p class="text-xs font-medium text-teal-600">مدیریت سازمان</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-900">{{ editingUser ? 'ویرایش کاربر' : 'ایجاد کاربر جدید' }}</h3>
                    </div>
                    <button type="button" class="rounded-lg p-2 text-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700" @click="closeForm">×</button>
                </div>
                <form class="space-y-5 p-6" @submit.prevent="submit">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">نام</label>
                            <input v-model="form.name" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50/50 focus:border-teal-500 focus:ring-teal-500" />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">ایمیل</label>
                            <input v-model="form.email" dir="ltr" type="email" class="w-full rounded-xl border-slate-200 bg-slate-50/50 text-left focus:border-teal-500 focus:ring-teal-500" />
                            <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">موبایل</label><input v-model="form.mobile" dir="ltr" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50/50 text-left focus:border-teal-500 focus:ring-teal-500" /></div>
                        <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">تلفن ثابت</label><input v-model="form.phone" dir="ltr" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50/50 text-left focus:border-teal-500 focus:ring-teal-500" /></div>
                        <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">داخلی</label><input v-model="form.extension" dir="ltr" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50/50 text-left focus:border-teal-500 focus:ring-teal-500" /></div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ editingUser ? 'رمز عبور جدید' : 'رمز عبور' }}</label><input v-model="form.password" dir="ltr" type="password" class="w-full rounded-xl border-slate-200 bg-slate-50/50 text-left focus:border-teal-500 focus:ring-teal-500" /><p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p></div>
                        <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">تکرار رمز عبور</label><input v-model="form.password_confirmation" dir="ltr" type="password" class="w-full rounded-xl border-slate-200 bg-slate-50/50 text-left focus:border-teal-500 focus:ring-teal-500" /></div>
                    </div>
                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 p-4">
                            <label class="mb-3 block text-sm font-semibold text-slate-700">واحدهای سازمانی</label>
                            <div class="max-h-44 space-y-2 overflow-y-auto">
                                <label v-for="department in props.departments" :key="department.id" class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-2 hover:bg-slate-50">
                                    <input type="checkbox" :checked="form.department_ids.includes(department.id)" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500" @change="toggleDepartment(department.id)" />
                                    <span class="text-sm text-slate-700">{{ department.name }}</span>
                                </label>
                            </div>
                            <p v-if="form.errors.department_ids" class="mt-1 text-xs text-red-600">{{ form.errors.department_ids }}</p>
                            <label class="mt-4 mb-1.5 block text-xs font-semibold text-slate-500">واحد اصلی</label>
                            <select v-model="form.primary_department_id" class="w-full rounded-xl border-slate-200 text-sm focus:border-teal-500 focus:ring-teal-500">
                                <option :value="null">بدون واحد اصلی</option>
                                <option v-for="department in props.departments.filter(d => form.department_ids.includes(d.id))" :key="department.id" :value="department.id">{{ department.name }}</option>
                            </select>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-4">
                            <label class="mb-3 block text-sm font-semibold text-slate-700">نقش‌های دسترسی</label>
                            <div class="max-h-44 space-y-2 overflow-y-auto">
                                <label v-for="role in props.roles" :key="role.id" class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-2 hover:bg-slate-50">
                                    <input type="checkbox" :checked="form.role_ids.includes(role.id)" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500" @change="toggleRole(role.id)" />
                                    <span class="text-sm text-slate-700">{{ role.name }}</span>
                                </label>
                            </div>
                            <p v-if="form.errors.role_ids" class="mt-1 text-xs text-red-600">{{ form.errors.role_ids }}</p>
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700"><input v-model="form.status" type="checkbox" :true-value="'active'" :false-value="'inactive'" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500" /> کاربر فعال باشد</label>
                    <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                        <button type="button" :disabled="form.processing" class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50" @click="closeForm">انصراف</button>
                        <button type="submit" :disabled="form.processing" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50">{{ form.processing ? 'در حال ذخیره...' : editingUser ? 'ذخیره تغییرات' : 'ایجاد کاربر' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>