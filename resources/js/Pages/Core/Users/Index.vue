<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import JalaliDatePicker from '@/Components/JalaliDatePicker.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Eye, EyeOff } from '@lucide/vue';

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

interface UserProfile {
    first_name: string | null;
    last_name: string | null;
    employee_code: string | null;
    national_id: string | null;
    birth_date: string | null;
    avatar_path: string | null;
}

interface CustomField {
    id: number; key: string; label: string; type: 'text'|'textarea'|'number'|'date'|'boolean'|'select'; is_required: boolean; options: string[]|null;
}

interface User {
    id: number;
    name: string;
    username: string;
    email: string;
    profile: UserProfile | null;
    mobile: string | null;
    phone: string | null;
    extension: string | null;
    status: 'active' | 'inactive';
    departments: Department[];
    roles: Role[];
    positions: { id: number; name: string; department?: { id: number; name: string } | null; }[];
}

type CustomFieldValue = string | number | boolean | null;
type CustomFieldValues = Record<number, Record<string, CustomFieldValue>>;
type CustomFieldError = string | undefined;

const page = usePage();

const avatarPreview = ref('/images/default-avatar.svg');
const avatarInput = ref<HTMLInputElement | null>(null);
const showPassword = ref(false);
const showPasswordConfirmation = ref(false);
let avatarPreviewObjectUrl: string | null = null;

const props = defineProps<{
    users: User[];
    departments: Department[];
    roles: Role[];
    customFields: CustomField[];
    customFieldValues: CustomFieldValues;
}>();

const showForm = ref(false);
const editingUser = ref<User | null>(null);
const membershipUser = ref<User | null>(null);

const membershipForm = useForm({
    department_ids: [] as number[],
    primary_department_id: null as number | null,
});

const form = useForm({
    username: '',
    email: '',
    mobile: '',
    phone: '',
    extension: '',
    password: '',
    password_confirmation: '',
    status: 'inactive' as 'active' | 'inactive',
    role_ids: [] as number[],
    first_name: '',
    last_name: '',
    employee_code: '',
    national_id: '',
    birth_date: '',
    custom_fields: {} as Record<string, string|number|boolean|null>,
    avatar: null as File | null,
});

function openCreate(): void {
    editingUser.value = null;

    form.reset();
    form.clearErrors();

    form.status = 'inactive';
    form.role_ids = [];
    form.first_name = '';
    form.last_name = '';
    form.employee_code = '';
    form.national_id = '';
    form.birth_date = '';
    form.custom_fields = {};
    form.avatar = null;
    form.transform((data) => data);
    setAvatarPreview(null);

    showForm.value = true;
}

function openEdit(user: User): void {
    editingUser.value = user;

    form.clearErrors();

    form.username = user.username;
    form.email = user.email;
    form.first_name = user.profile?.first_name ?? '';
    form.last_name = user.profile?.last_name ?? '';
    form.employee_code = user.profile?.employee_code ?? '';
    form.national_id = user.profile?.national_id ?? '';
    form.birth_date = user.profile?.birth_date ?? '';
    form.mobile = user.mobile ?? '';
    form.phone = user.phone ?? '';
    form.extension = user.extension ?? '';
    form.password = '';
    form.password_confirmation = '';
    showPassword.value = false;
    showPasswordConfirmation.value = false;
    form.status = user.status;
    form.role_ids = canManageUserRoles()
        ? user.roles.map((role) => role.id)
        : [];
    form.custom_fields = { ...(props.customFieldValues[user.id] ?? {}) };
    form.avatar = null;
    form.transform((data) => data);
    setAvatarPreview(user);

    showForm.value = true;
}

function openMembership(user: User): void {
    membershipUser.value = user;
    membershipForm.clearErrors();
    membershipForm.department_ids = user.departments.map((department) => department.id);
    membershipForm.primary_department_id = user.departments.find((department) => department.pivot?.is_primary)?.id ?? null;
}

function closeMembership(): void {
    membershipUser.value = null;
    membershipForm.reset();
    membershipForm.clearErrors();
}

function toggleMembershipDepartment(departmentId: number): void {
    if (membershipForm.department_ids.includes(departmentId)) {
        membershipForm.department_ids = membershipForm.department_ids.filter((id) => id !== departmentId);
        if (membershipForm.primary_department_id === departmentId) {
            membershipForm.primary_department_id = membershipForm.department_ids[0] ?? null;
        }
        return;
    }
    membershipForm.department_ids = [...membershipForm.department_ids, departmentId];
    if (membershipForm.primary_department_id === null) membershipForm.primary_department_id = departmentId;
}

function submitMembership(): void {
    if (!membershipUser.value) return;
    membershipForm.put(route('users.departments.update', membershipUser.value.id), {
        preserveScroll: true,
        onSuccess: () => closeMembership(),
    });
}

function revokeAvatarPreviewObjectUrl(): void {
    if (avatarPreviewObjectUrl) {
        URL.revokeObjectURL(avatarPreviewObjectUrl);
        avatarPreviewObjectUrl = null;
    }
}

function setAvatarPreview(user: User | null): void {
    revokeAvatarPreviewObjectUrl();

    avatarPreview.value = user?.profile?.avatar_path
        ? `/storage/${user.profile.avatar_path}`
        : '/images/default-avatar.svg';
}

function handleAvatarChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    form.avatar = file;
    revokeAvatarPreviewObjectUrl();

    if (file) {
        avatarPreviewObjectUrl = URL.createObjectURL(file);
        avatarPreview.value = avatarPreviewObjectUrl;
        return;
    }

    setAvatarPreview(editingUser.value);
}

function clearAvatarSelection(input: HTMLInputElement | null): void {
    if (input) {
        input.value = '';
    }

    form.avatar = null;
    setAvatarPreview(editingUser.value);
}

function canManageUserRoles(): boolean {
    return page.props.auth.abilities?.users_manage_roles === true;
}

onBeforeUnmount(() => {
    revokeAvatarPreviewObjectUrl();
});

function closeForm(): void {
    showForm.value = false;
    editingUser.value = null;
    form.transform((data) => data);
    form.reset();
    form.clearErrors();
    setAvatarPreview(null);
}

function submit(): void {
    const options = {
        preserveScroll: true,
        forceFormData: form.avatar instanceof File || !!editingUser.value,
        onSuccess: () => closeForm(),
    };

    form.transform((data) => {
        const payload = {
            ...data,
            ...(editingUser.value ? { _method: 'put' } : {}),
        };

        if (!canManageUserRoles()) {
            const { role_ids: _roleIds, ...payloadWithoutRoles } = payload;

            return payloadWithoutRoles;
        }

        return payload;
    });

    if (editingUser.value) {
        form.post(
            route('users.update', editingUser.value.id),
            options,
        );

        return;
    }

    form.post(route('users.store'), options);
}

const activeRoles = computed(() =>
    props.roles.filter((role) => role.is_active),
);

const selectedRoleId = computed<number | null>({
    get: () => form.role_ids[0] ?? null,
    set: (value) => {
        form.role_ids = value === null ? [] : [value];
    },
});

function inviteUser(user: User): void {
    if (!confirm(`دعوت‌نامه برای «${user.name}» ارسال شود؟`)) return;
    useForm({}).post(route('users.invite', user.id), { preserveScroll: true });
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
    return page.props.auth.user?.id ?? null;
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
                    <p class="mt-1 text-sm text-slate-500">اعضا، واحدها، نقش‌های کاربری و وضعیت دسترسی کاربران را مدیریت کنید.</p>
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
                        <p class="text-xs font-medium text-slate-500">کاربران دارای نقش کاربری</p>
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
                        <table class="min-w-[1120px] w-full text-right">
                            <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                                <tr>
                                    <th class="px-5 py-3.5">کاربر</th>
                                    <th class="px-5 py-3.5">تماس</th>
                                    <th class="px-5 py-3.5">واحدهای سازمانی</th>
                                    <th class="px-5 py-3.5">سمت سازمانی</th>
                                    <th class="px-5 py-3.5">نقش‌های کاربری</th>
                                    <th class="px-5 py-3.5">وضعیت</th>
                                    <th class="px-5 py-3.5">عملیات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="user in props.users" :key="user.id" class="transition hover:bg-slate-50/80">
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <UserAvatar
                                                :name="user.name"
                                                :avatar-path="user.profile?.avatar_path"
                                                size="md"
                                            />
                                            <div class="min-w-0">
                                                <div class="font-semibold text-slate-900">{{ user.name }}</div>
                                                <div dir="ltr" class="truncate text-xs text-slate-500 text-right">@{{ user.username }}</div>
                                                <div dir="ltr" class="truncate text-xs text-slate-400 text-right">{{ user.email }}</div>
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
                                            <span v-for="position in user.positions" :key="position.id" class="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-medium text-violet-700">
                                                {{ position.name }}
                                            </span>
                                            <span v-if="user.positions.length === 0" class="text-xs text-slate-400">بدون سمت</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex max-w-xs flex-wrap gap-1.5">
                                            <span v-for="role in user.roles" :key="role.id" class="rounded-full bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-700">
                                                {{ role.name }}
                                            </span>
                                            <span v-if="user.roles.length === 0" class="text-xs text-slate-400">بدون نقش کاربری</span>
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
                                            <button type="button" class="rounded-lg border border-teal-100 px-3 py-1.5 text-xs font-semibold text-teal-700 transition hover:bg-teal-50" @click="openMembership(user)">واحدها</button>
                                            <button v-if="user.status === 'inactive'" type="button" class="rounded-lg border border-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-700 transition hover:bg-amber-50" @click="inviteUser(user)">دعوت</button>
                                            <button v-if="user.status === 'active'" type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50" @click="toggleStatus(user)">غیرفعال</button>
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
                <form class="space-y-5 p-6" autocomplete="off" @submit.prevent="submit">
                    <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex h-20 w-20 shrink-0 overflow-hidden rounded-full bg-white ring-1 ring-slate-200">
                                    <img :src="avatarPreview" alt="تصویر پروفایل کاربر" class="h-full w-full object-cover" />
                                </span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-700">تصویر پروفایل</p>
                                    <p class="mt-1 text-xs leading-5 text-slate-500">برای کاربر جدید تصویر پیش‌فرض نمایش داده می‌شود و در صورت داشتن تصویر قبلی، همان تصویر نمایش داده خواهد شد.</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 sm:mr-auto">
                                <input
                                    id="user-avatar"
                                    ref="avatarInput"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                    class="sr-only"
                                    @change="handleAvatarChange"
                                />
                                <label
                                    for="user-avatar"
                                    class="inline-flex cursor-pointer items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-slate-800 focus-within:ring-2 focus-within:ring-teal-500 focus-within:ring-offset-2"
                                >
                                    انتخاب تصویر
                                </label>
                                <span
                                    class="max-w-[220px] truncate text-xs text-slate-500"
                                    :title="form.avatar?.name || 'تصویری انتخاب نشده است'"
                                >
                                    {{ form.avatar?.name || 'تصویری انتخاب نشده است' }}
                                </span>
                                <button
                                    v-if="form.avatar"
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
                                    @click="clearAvatarSelection(avatarInput)"
                                >
                                    لغو انتخاب
                                </button>
                            </div>
                        </div>
                        <p v-if="form.errors.avatar" class="mt-1.5 text-xs text-red-600">{{ form.errors.avatar }}</p>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">نام کاربری</label>
                            <input v-model="form.username" dir="ltr" type="text" maxlength="50" class="w-full rounded-xl border-slate-200 bg-slate-50/50 text-left focus:border-teal-500 focus:ring-teal-500" />
                            <p v-if="form.errors.username" class="mt-1 text-xs text-red-600">{{ form.errors.username }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">ایمیل</label>
                            <input v-model="form.email" dir="ltr" type="email" class="w-full rounded-xl border-slate-200 bg-slate-50/50 text-left focus:border-teal-500 focus:ring-teal-500" />
                            <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">نام</label><input v-model="form.first_name" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50/50 focus:border-teal-500 focus:ring-teal-500" /><p v-if="form.errors.first_name" class="mt-1 text-xs text-red-600">{{ form.errors.first_name }}</p></div>
                        <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">نام خانوادگی</label><input v-model="form.last_name" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50/50 focus:border-teal-500 focus:ring-teal-500" /><p v-if="form.errors.last_name" class="mt-1 text-xs text-red-600">{{ form.errors.last_name }}</p></div>
                        <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">کد پرسنلی</label><input v-model="form.employee_code" dir="ltr" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50/50 text-left focus:border-teal-500 focus:ring-teal-500" /><p v-if="form.errors.employee_code" class="mt-1 text-xs text-red-600">{{ form.errors.employee_code }}</p></div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">کد ملی</label><input v-model="form.national_id" dir="ltr" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50/50 text-left focus:border-teal-500 focus:ring-teal-500" /><p v-if="form.errors.national_id" class="mt-1 text-xs text-red-600">{{ form.errors.national_id }}</p></div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">تاریخ تولد</label>
                            <JalaliDatePicker v-model="form.birth_date" placeholder="انتخاب تاریخ تولد" />
                            <p v-if="form.errors.birth_date" class="mt-1 text-xs text-red-600">{{ form.errors.birth_date }}</p>
                        </div>
                        <div></div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">موبایل</label><input v-model="form.mobile" dir="ltr" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50/50 text-left focus:border-teal-500 focus:ring-teal-500" /></div>
                        <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">تلفن ثابت</label><input v-model="form.phone" dir="ltr" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50/50 text-left focus:border-teal-500 focus:ring-teal-500" /></div>
                        <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">داخلی</label><input v-model="form.extension" dir="ltr" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50/50 text-left focus:border-teal-500 focus:ring-teal-500" /></div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ editingUser ? 'رمز عبور جدید' : 'رمز عبور (اختیاری برای دعوت)' }}</label>
                            <div class="relative">
                                <input
                                    v-model="form.password"
                                    dir="ltr"
                                    :type="showPassword ? 'text' : 'password'"
                                    autocomplete="new-password"
                                    placeholder="برای تغییر، رمز جدید را وارد کنید"
                                    class="w-full rounded-xl border-slate-200 bg-slate-50/50 pl-3 pr-11 text-left focus:border-teal-500 focus:ring-teal-500"
                                />
                                <button
                                    type="button"
                                    tabindex="-1"
                                    class="absolute inset-y-0 right-0 inline-flex w-10 items-center justify-center text-slate-400 transition hover:text-slate-700"
                                    :aria-label="showPassword ? 'مخفی کردن رمز عبور' : 'نمایش رمز عبور'"
                                    :title="showPassword ? 'مخفی کردن رمز عبور' : 'نمایش رمز عبور'"
                                    @click="showPassword = !showPassword"
                                >
                                    <EyeOff v-if="showPassword" class="h-4 w-4" />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </div>
                            <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">تکرار رمز عبور</label>
                            <div class="relative">
                                <input
                                    v-model="form.password_confirmation"
                                    dir="ltr"
                                    :type="showPasswordConfirmation ? 'text' : 'password'"
                                    autocomplete="new-password"
                                    placeholder="تکرار رمز جدید"
                                    class="w-full rounded-xl border-slate-200 bg-slate-50/50 pl-3 pr-11 text-left focus:border-teal-500 focus:ring-teal-500"
                                />
                                <button
                                    type="button"
                                    tabindex="-1"
                                    class="absolute inset-y-0 right-0 inline-flex w-10 items-center justify-center text-slate-400 transition hover:text-slate-700"
                                    :aria-label="showPasswordConfirmation ? 'مخفی کردن رمز عبور' : 'نمایش رمز عبور'"
                                    :title="showPasswordConfirmation ? 'مخفی کردن رمز عبور' : 'نمایش رمز عبور'"
                                    @click="showPasswordConfirmation = !showPasswordConfirmation"
                                >
                                    <EyeOff v-if="showPasswordConfirmation" class="h-4 w-4" />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </div>
                            <p v-if="form.errors.password_confirmation" class="mt-1 text-xs text-red-600">{{ form.errors.password_confirmation }}</p>
                        </div>
                    </div>
                    <div v-if="props.customFields.length" class="rounded-xl border border-teal-100 bg-teal-50/40 p-4">
                        <div class="mb-4"><h4 class="text-sm font-bold text-slate-800">اطلاعات تکمیلی سازمانی</h4><p class="mt-1 text-xs text-slate-500">فیلدهای سفارشی تعریف‌شده توسط مدیر سامانه</p></div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div v-for="field in props.customFields" :key="field.id">
                                <label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ field.label }}<span v-if="field.is_required" class="text-red-500"> *</span></label>
                                <textarea v-if="field.type === 'textarea'" :value="String(form.custom_fields[field.key] ?? '')" @input="form.custom_fields[field.key] = ($event.target as HTMLTextAreaElement).value" rows="3" class="w-full rounded-xl border-slate-200 bg-white"></textarea>
                                <select v-else-if="field.type === 'select'" v-model="form.custom_fields[field.key]" class="w-full rounded-xl border-slate-200 bg-white"><option :value="null">انتخاب کنید</option><option v-for="option in field.options ?? []" :key="option" :value="option">{{ option }}</option></select>
                                <input v-else-if="field.type === 'boolean'" v-model="form.custom_fields[field.key]" type="checkbox" class="rounded border-slate-300 text-teal-600" />
                                <input v-else v-model="form.custom_fields[field.key]" :type="field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text'" class="w-full rounded-xl border-slate-200 bg-white" />
                                <p v-if="(form.errors as Record<string, CustomFieldError>)['custom_fields.' + field.key]" class="mt-1 text-xs text-red-600">{{ (form.errors as Record<string, CustomFieldError>)['custom_fields.' + field.key] }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-5 md:grid-cols-2">
                        <div v-if="!editingUser" class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 text-sm text-slate-600">
                            پس از ایجاد کاربر، عضویت او در واحدهای سازمانی را از بخش «مدیریت واحدها» تعیین کنید.
                        </div>
                        <div v-if="canManageUserRoles()" class="rounded-xl border border-slate-200 p-4">
                            <div class="mb-3">
                                <label class="block text-sm font-semibold text-slate-700">نقش کاربری</label>
                                <p class="mt-1 text-xs leading-5 text-slate-500">نقش کاربری، مجموعه مجوزهای این کاربر در سامانه را مشخص می‌کند.</p>
                            </div>
                            <div>
                                 <select
                                     v-model="selectedRoleId"
                                     aria-label="نقش کاربری"
                                     class="w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-medium text-slate-700 shadow-sm transition focus:border-teal-500 focus:ring-teal-500"
                                 >
                                     <option :value="null">کاربر عادی (بدون نقش کاربری)</option>
                                     <option v-for="role in activeRoles" :key="role.id" :value="role.id">
                                         {{ role.name }}{{ role.code ? ` — ${role.code}` : '' }}
                                     </option>
                                 </select>
                                 <p v-if="activeRoles.length === 0" class="mt-2 text-xs text-slate-400">
                                     نقش کاربری فعالی برای تخصیص تعریف نشده است؛ این کاربر به‌صورت عادی و بدون نقش ایجاد می‌شود.
                                 </p>
                             </div>
                         </div>
                        <div v-else class="rounded-xl border border-amber-100 bg-amber-50/60 p-4 text-sm leading-6 text-amber-800">
                            تخصیص و تغییر نقش‌های کاربری فقط برای مدیرانی که مجوز «مدیریت نقش‌های کاربر» دارند فعال است.
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700"><input v-model="form.status" type="checkbox" :true-value="'active'" :false-value="'inactive'" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500" /> کاربر فعال باشد</label>
                    <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                        <button type="button" :disabled="form.processing" class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50" @click="closeForm">انصراف</button>
                        <button type="submit" :disabled="form.processing" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50">{{ form.processing ? 'در حال ذخیره...' : editingUser ? 'ذخیره تغییرات' : 'ایجاد کاربر' }}</button>
                    </div>
                </form>
            </div>
        <div v-if="membershipUser" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between">
                    <div><p class="text-xs font-semibold text-teal-600">ساختار سازمانی</p><h3 class="mt-1 text-lg font-bold text-slate-900">مدیریت واحدهای کاربر</h3><p class="mt-1 text-xs text-slate-500">{{ membershipUser.name }}</p></div>
                    <button type="button" class="rounded-lg p-2 text-xl text-slate-400 hover:bg-slate-100" @click="closeMembership">×</button>
                </div>
                <div class="mt-5 max-h-72 space-y-2 overflow-y-auto">
                    <label v-for="department in props.departments" :key="department.id" class="flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 hover:bg-slate-50">
                        <input type="checkbox" :checked="membershipForm.department_ids.includes(department.id)" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500" @change="toggleMembershipDepartment(department.id)" />
                        <span class="text-sm text-slate-700">{{ department.name }}</span>
                    </label>
                </div>
                <p v-if="membershipForm.errors.department_ids" class="mt-2 text-xs text-red-600">{{ membershipForm.errors.department_ids }}</p>
                <label class="mt-4 mb-1.5 block text-xs font-semibold text-slate-500">واحد اصلی</label>
                <select v-model="membershipForm.primary_department_id" class="w-full rounded-xl border-slate-200 text-sm focus:border-teal-500 focus:ring-teal-500">
                    <option :value="null">بدون واحد اصلی</option>
                    <option v-for="department in props.departments.filter(d => membershipForm.department_ids.includes(d.id))" :key="department.id" :value="department.id">{{ department.name }}</option>
                </select>
                <p v-if="membershipForm.errors.primary_department_id" class="mt-1 text-xs text-red-600">{{ membershipForm.errors.primary_department_id }}</p>
                <div class="mt-6 flex justify-end gap-3 border-t border-slate-200 pt-4">
                    <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700" @click="closeMembership">انصراف</button>
                    <button type="button" :disabled="membershipForm.processing" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" @click="submitMembership">{{ membershipForm.processing ? 'در حال ذخیره...' : 'ذخیره عضویت' }}</button>
                </div>
            </div>
        </div>

        </div>
    </AuthenticatedLayout>
</template>