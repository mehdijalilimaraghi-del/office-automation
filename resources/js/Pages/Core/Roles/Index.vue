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
    <Head title="نقش‌های کاربری" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold text-slate-400">مدیریت سازمان</p>
                    <h2 class="mt-1 text-xl font-bold text-slate-900">نقش‌های کاربری و دسترسی‌ها</h2>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ props.roles.length }} نقش</span>
            </div>
        </template>
        <div class="min-h-screen bg-slate-50/70 py-8" dir="rtl">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="overflow-hidden rounded-3xl bg-gradient-to-l from-slate-950 via-slate-900 to-teal-950 p-6 text-white shadow-xl shadow-slate-200/50 sm:p-8">
                    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div class="max-w-2xl">
                            <div class="mb-4 inline-flex rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs font-medium text-teal-100">کنترل دسترسی</div>
                            <h1 class="text-2xl font-black tracking-tight sm:text-3xl">نقش‌ها، مرز دسترسی کاربران را مشخص می‌کنند.</h1>
                            <p class="mt-3 text-sm leading-7 text-slate-300">نقش‌های کاربری سامانه را مدیریت کنید و دسترسی‌های هر نقش را از یک نقطه کنترل کنید.</p>
                        </div>
                        <button type="button" class="rounded-2xl bg-white px-5 py-3 text-sm font-bold text-slate-900 shadow-lg transition hover:-translate-y-0.5 hover:bg-teal-50" @click="resetForm">+ نقش کاربری جدید</button>
                    </div>
                </section>

                <section class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl bg-white p-5 ring-1 ring-slate-200"><p class="text-sm text-slate-500">کل نقش‌ها</p><p class="mt-2 text-3xl font-black text-slate-900">{{ props.roles.length }}</p></div>
                    <div class="rounded-2xl bg-white p-5 ring-1 ring-slate-200"><p class="text-sm text-slate-500">فعال</p><p class="mt-2 text-3xl font-black text-emerald-600">{{ props.roles.filter(r => r.is_active).length }}</p></div>
                    <div class="rounded-2xl bg-white p-5 ring-1 ring-slate-200"><p class="text-sm text-slate-500">سیستمی</p><p class="mt-2 text-3xl font-black text-teal-700">{{ props.roles.filter(r => r.is_system).length }}</p></div>
                </section>

                <section class="grid gap-6 lg:grid-cols-3">
                    <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200 lg:col-span-1">
                        <div class="mb-6">
                            <p class="text-xs font-bold text-teal-600">{{ editingRole ? 'ویرایش نقش' : 'نقش کاربری جدید' }}</p>
                            <h3 class="mt-1 text-xl font-black text-slate-900">{{ editingRole ? 'به‌روزرسانی اطلاعات' : 'تعریف نقش کاربری' }}</h3>
                        </div>
                        <form class="space-y-5" @submit.prevent="submit">
                            <div><label for="role-name" class="mb-2 block text-sm font-semibold text-slate-700">نام نقش</label><input id="role-name" v-model="form.name" required maxlength="100" class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" /><p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p></div>
                                                        <div><label for="role-description" class="mb-2 block text-sm font-semibold text-slate-700">توضیحات</label><textarea id="role-description" v-model="form.description" rows="4" class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500"></textarea><p v-if="form.errors.description" class="mt-1 text-xs text-red-600">{{ form.errors.description }}</p></div>
                            <label class="flex items-center gap-3 rounded-2xl bg-slate-50 p-4"><input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500" /><span class="text-sm font-semibold text-slate-700">نقش فعال باشد</span></label>
                            <div class="flex gap-2"><button type="submit" :disabled="form.processing" class="flex-1 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-bold text-white transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50">{{ form.processing ? 'در حال ذخیره...' : editingRole ? 'ذخیره تغییرات' : 'ایجاد نقش' }}</button><button v-if="editingRole" type="button" :disabled="form.processing" class="rounded-2xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-600" @click="cancelEdit">انصراف</button></div>
                        </form>
                    </div>

                    <div class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-200 lg:col-span-2">
                        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5"><div><h3 class="font-black text-slate-900">فهرست نقش‌ها</h3><p class="mt-1 text-xs text-slate-500">نقش‌های سیستمی قابل ویرایش یا حذف نیستند.</p></div></div>
                        <div v-if="props.roles.length === 0" class="px-6 py-16 text-center"><p class="font-bold text-slate-700">هنوز نقشی ثبت نشده است.</p><p class="mt-1 text-sm text-slate-400">اولین نقش کاربری را ایجاد کنید.</p></div>
                        <div v-else class="divide-y divide-slate-100">
                            <div v-for="role in props.roles" :key="role.id" class="flex flex-col gap-4 px-6 py-5 transition hover:bg-slate-50/80 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2"><h4 class="font-bold text-slate-900">{{ role.name }}</h4><span v-if="role.is_system" class="rounded-full bg-teal-50 px-2.5 py-1 text-[11px] font-bold text-teal-700">سیستمی</span><span :class="role.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'" class="rounded-full px-2.5 py-1 text-[11px] font-bold">{{ role.is_active ? 'فعال' : 'غیرفعال' }}</span></div>
                                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500"><span dir="ltr" class="font-mono">{{ role.code || 'بدون کد' }}</span><span v-if="role.description">• {{ role.description }}</span></div>
                                </div>
                                <div class="flex shrink-0 flex-wrap gap-2">
                                    <a :href="route('roles.permissions.edit', role.id)" class="rounded-xl bg-teal-50 px-3 py-2 text-xs font-bold text-teal-700 transition hover:bg-teal-100">مجوزها</a>
                                    <button type="button" :disabled="role.is_system || form.processing" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 disabled:cursor-not-allowed disabled:opacity-40" @click="startEdit(role)">ویرایش</button>
                                    <button type="button" :disabled="role.is_system || form.processing" :class="role.is_active ? 'text-amber-700 bg-amber-50 hover:bg-amber-100' : 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100'" class="rounded-xl px-3 py-2 text-xs font-bold disabled:cursor-not-allowed disabled:opacity-40" @click="toggleStatus(role)">{{ role.is_active ? 'غیرفعال‌سازی' : 'فعال‌سازی' }}</button>
                                    <button type="button" :disabled="role.is_system || form.processing" class="rounded-xl bg-red-50 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-40" @click="deleteRole(role)">حذف</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
