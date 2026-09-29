<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

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

const assignedPermissionIds = computed(() =>
    new Set(props.role.permissions.map((permission) => permission.id)),
);

const form = useForm({
    permissions: props.role.permissions.map((permission) => ({
        permission_id: permission.id,
        scope: existingScope(permission),
    })),
});

function assignedPermission(permissionId: number): AssignedPermission | undefined {
    return form.permissions.find(
        (permission) => permission.permission_id === permissionId,
    );
}

function isAssigned(permissionId: number): boolean {
    return assignedPermissionIds.value.has(permissionId);
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
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        مجوزهای نقش
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ props.role.name }}
                    </p>
                </div>
                <Link
                    :href="route('roles.index')"
                    class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    بازگشت به نقش‌ها
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="border-b border-gray-200 px-6 py-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">
                                    انتخاب مجوزها
                                </h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    مجوزهای مورد نیاز این نقش را انتخاب کنید و محدوده دسترسی هر مجوز را مشخص کنید.
                                </p>
                            </div>
                            <span
                                v-if="props.role.is_system"
                                class="rounded-full bg-purple-100 px-3 py-1 text-xs font-medium text-purple-700"
                            >
                                نقش سیستمی
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="props.role.is_system"
                        class="border-b border-amber-200 bg-amber-50 px-6 py-4 text-sm text-amber-800"
                    >
                        مجوزهای نقش سیستمی قابل تغییر نیستند.
                    </div>

                    <form class="p-6" @submit.prevent="submit">
                        <div v-if="props.permissions.length" class="space-y-3">
                            <div
                                v-for="permission in props.permissions"
                                :key="permission.id"
                                class="rounded-lg border p-4 transition"
                                :class="isAssigned(permission.id)
                                    ? 'border-indigo-300 bg-indigo-50'
                                    : 'border-gray-200'"
                            >
                                <div class="flex items-start gap-3">
                                    <input
                                        type="checkbox"
                                        :checked="isAssigned(permission.id)"
                                        :disabled="props.role.is_system || !permission.is_active"
                                        class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                        @change="togglePermission(permission)"
                                    />

                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium text-gray-900">
                                            {{ permission.name }}
                                        </div>
                                        <div class="mt-1 text-sm text-gray-500" dir="ltr">
                                            {{ permission.code }}
                                        </div>

                                        <div
                                            v-if="permission.description"
                                            class="mt-2 text-xs text-gray-500"
                                        >
                                            {{ permission.description }}
                                        </div>

                                        <div
                                            v-if="isAssigned(permission.id)"
                                            class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center"
                                        >
                                            <label class="text-sm font-medium text-gray-700">
                                                محدوده دسترسی
                                            </label>
                                            <select
                                                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                :value="assignedPermission(permission.id)?.scope"
                                                :disabled="props.role.is_system"
                                                @change="updateScope(
                                                    permission.id,
                                                    ($event.target as HTMLSelectElement).value,
                                                )"
                                            >
                                                <option
                                                    v-for="scope in props.scopes"
                                                    :key="scope.value"
                                                    :value="scope.value"
                                                >
                                                    {{ scope.label }}
                                                </option>
                                            </select>
                                        </div>

                                        <div class="mt-3 flex gap-2">
                                            <span
                                                v-if="permission.is_system"
                                                class="rounded-full bg-purple-100 px-2 py-0.5 text-xs text-purple-700"
                                            >
                                                سیستمی
                                            </span>
                                            <span
                                                v-if="!permission.is_active"
                                                class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600"
                                            >
                                                غیرفعال
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-else
                            class="rounded-lg border border-dashed border-gray-300 px-6 py-10 text-center text-sm text-gray-500"
                        >
                            هنوز هیچ مجوزی تعریف نشده است.
                        </div>

                        <div class="mt-6 flex items-center justify-between border-t border-gray-200 pt-6">
                            <span class="text-sm text-gray-500">
                                تعداد مجوزهای انتخاب‌شده:
                                {{ form.permissions.length }}
                            </span>
                            <button
                                type="submit"
                                :disabled="form.processing || props.role.is_system"
                                class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{ form.processing ? 'در حال ذخیره...' : 'ذخیره مجوزها' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>