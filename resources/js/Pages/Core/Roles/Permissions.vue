<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
interface Permission {
    id: number;
    name: string;
    code: string;
    description: string | null;
    is_active: boolean;
    is_system: boolean;
    pivot?: { scope: string };
}

interface Role {
    id: number;
    name: string;
    code: string | null;
    description: string | null;
    is_active: boolean;
    is_system: boolean;
    permissions: Permission[];
}

interface ScopeOption {
    value: string;
    label: string;
}

interface AssignedPermission {
    permission_id: number;
    scope: string;
}

const props = defineProps<{
    role: Role;
    permissions: Permission[];
    scopes: ScopeOption[];
}>();

function defaultScope(permission: Permission): string {
    return permission.code.startsWith('tasks.')
        ? 'project'
        : 'organization';
}

function existingScope(permission: Permission): string {
    return permission.pivot?.scope ?? defaultScope(permission);
}

const form = useForm({
    permissions: props.role.permissions.map((permission) => ({
        permission_id: permission.id,
        scope: existingScope(permission),
    })),
});

const search = ref('');

const filteredPermissions = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return props.permissions;
    }

    return props.permissions.filter((permission) =>
        [permission.name, permission.code, permission.description ?? '']
            .join(' ')
            .toLowerCase()
            .includes(query),
    );
});

const assignedCount = computed(() => form.permissions.length);
const activeCount = computed(() => props.permissions.filter((permission) => permission.is_active).length);

function assignedPermission(permissionId: number): AssignedPermission | undefined {
    return form.permissions.find(
        (permission) => permission.permission_id === permissionId,
    );
}

function isAssigned(permissionId: number): boolean {
    return form.permissions.some(
        (permission) => permission.permission_id === permissionId,
    );
}

function togglePermission(permission: Permission): void {
    if (props.role.is_system) {
        return;
    }

    const index = form.permissions.findIndex(
        (item) => item.permission_id === permission.id,
    );

    if (index === -1) {
        form.permissions.push({
            permission_id: permission.id,
            scope: defaultScope(permission),
        });
    } else {
        form.permissions.splice(index, 1);
    }
}

function updateScope(permissionId: number, scope: string): void {
    const assigned = assignedPermission(permissionId);

    if (assigned) {
        assigned.scope = scope;
    }
}

function submit(): void {
    if (props.role.is_system) {
        return;
    }

    form.put(route('roles.permissions.update', props.role.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="'مجوزهای نقش ' + props.role.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-400">مدیریت سازمان / نقش‌ها</p>
                    <h2 class="mt-1 text-xl font-bold text-slate-900">دسترسی‌های نقش</h2>
                </div>
                <Link
                    :href="route('roles.index')"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
                >
                    ← بازگشت به نقش‌ها
                </Link>
            </div>
        </template>

        <div class="min-h-screen bg-slate-50/70 py-8" dir="rtl">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="overflow-hidden rounded-3xl bg-gradient-to-l from-slate-950 via-slate-900 to-teal-950 p-6 text-white shadow-xl shadow-slate-200/50 sm:p-8">
                    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div class="max-w-3xl">
                            <div class="mb-4 flex flex-wrap items-center gap-2">
                                <span class="rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs font-semibold text-teal-100">کنترل دسترسی</span>
                                <span v-if="props.role.is_system" class="rounded-full border border-amber-300/20 bg-amber-300/10 px-3 py-1 text-xs font-semibold text-amber-100">نقش سیستمی</span>
                            </div>
                            <h1 class="text-2xl font-black tracking-tight sm:text-3xl">{{ props.role.name }}</h1>
                            <p class="mt-3 text-sm leading-7 text-slate-300">
                                مجوزهای این نقش و محدوده دسترسی هر مجوز را مدیریت کنید.
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-3 sm:min-w-[280px]">
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p class="text-xs text-slate-400">مجوز انتخاب‌شده</p>
                                <p class="mt-1 text-2xl font-black">{{ assignedCount }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p class="text-xs text-slate-400">مجوز فعال سیستم</p>
                                <p class="mt-1 text-2xl font-black">{{ activeCount }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    v-if="props.role.is_system"
                    class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-medium leading-7 text-amber-800"
                >
                    این نقش سیستمی است؛ مجوزهای آن برای حفظ یکپارچگی سیستم قابل تغییر نیستند.
                </section>

                <form @submit.prevent="submit">
                    <section class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-200">
                        <div class="border-b border-slate-100 p-5 sm:p-6">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <h3 class="font-black text-slate-900">فهرست مجوزها</h3>
                                    <p class="mt-1 text-xs leading-6 text-slate-500">
                                        برای هر مجوز، محدوده دسترسی متناسب با نقش را انتخاب کنید.
                                    </p>
                                </div>
                                <div class="relative w-full lg:max-w-sm">
                                    <input
                                        v-model="search"
                                        type="search"
                                        placeholder="جستجوی نام، کد یا توضیحات..."
                                        class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500"
                                    />
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="filteredPermissions.length"
                            class="grid gap-3 p-4 sm:p-6 lg:grid-cols-2"
                        >
                            <div
                                v-for="permission in filteredPermissions"
                                :key="permission.id"
                                class="rounded-2xl border p-4 transition"
                                :class="isAssigned(permission.id)
                                    ? 'border-teal-200 bg-teal-50/50 shadow-sm'
                                    : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/70'"
                            >
                                <div class="flex items-start gap-3">
                                    <input
                                        type="checkbox"
                                        :checked="isAssigned(permission.id)"
                                        :disabled="props.role.is_system || !permission.is_active"
                                        class="mt-1 h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500 disabled:opacity-50"
                                        @change="togglePermission(permission)"
                                    />

                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-bold text-slate-900">{{ permission.name }}</span>
                                            <span v-if="permission.is_system" class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">سیستمی</span>
                                            <span v-if="!permission.is_active" class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700">غیرفعال</span>
                                        </div>

                                        <p class="mt-1 font-mono text-xs text-slate-500" dir="ltr">{{ permission.code }}</p>

                                        <p v-if="permission.description" class="mt-2 text-xs leading-6 text-slate-500">
                                            {{ permission.description }}
                                        </p>

                                        <div v-if="isAssigned(permission.id)" class="mt-4 rounded-xl bg-white/80 p-3 ring-1 ring-teal-100">
                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                <label class="text-xs font-bold text-slate-600">محدوده دسترسی</label>
                                                <select
                                                    class="rounded-xl border-slate-200 bg-white text-xs font-semibold shadow-sm focus:border-teal-500 focus:ring-teal-500"
                                                    :value="assignedPermission(permission.id)?.scope"
                                                    :disabled="props.role.is_system"
                                                    @change="updateScope(permission.id, ($event.target as HTMLSelectElement).value)"
                                                >
                                                    <option v-for="scope in props.scopes" :key="scope.value" :value="scope.value">
                                                        {{ scope.label }}
                                                    </option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="px-6 py-16 text-center">
                            <p class="font-bold text-slate-700">{{ search ? 'مجوزی با این عبارت پیدا نشد.' : 'هنوز هیچ مجوزی تعریف نشده است.' }}</p>
                            <p class="mt-1 text-sm text-slate-400">{{ search ? 'عبارت جستجو را تغییر دهید.' : 'ابتدا مجوزهای سیستم را تعریف کنید.' }}</p>
                        </div>

                        <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/60 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="text-sm text-slate-500">
                                <span class="font-bold text-slate-800">{{ assignedCount }}</span>
                                مجوز انتخاب شده
                            </div>
                            <button
                                type="submit"
                                :disabled="form.processing || props.role.is_system"
                                class="rounded-2xl bg-slate-900 px-6 py-3 text-sm font-bold text-white transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{ form.processing ? 'در حال ذخیره...' : 'ذخیره مجوزهای نقش' }}
                            </button>
                        </div>
                    </section>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
