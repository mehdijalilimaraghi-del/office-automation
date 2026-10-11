<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Permission {
    id: number;
    name: string;
    code: string;
    description: string | null;
    is_active: boolean;
    is_system: boolean;
}
const props = defineProps<{ permissions: Permission[] }>();
const search = ref('');
const showRetired = ref(false);

function groupLabel(code: string): string {
    if (code.startsWith('users.')) return 'کاربران و حساب‌ها';
    if (code.startsWith('departments.')) return 'ساختار سازمانی';
    if (code.startsWith('roles.')) return 'نقش‌های کاربری';
    if (code.startsWith('permissions.')) return 'مجوزهای سیستم';
    if (code.startsWith('projects.')) return 'پروژه‌ها';
    if (code.startsWith('tasks.')) return 'دستورکارها';
    if (code.startsWith('documents.')) return 'اسناد';
    if (code.startsWith('external_parties.')) return 'طرف‌های خارجی';
    if (code.startsWith('notifications.')) return 'اعلان‌ها';
    if (code === 'cartable.view') return 'کارتابل';
    if (code.startsWith('settings.')) return 'تنظیمات سامانه';
    if (code.startsWith('custom_fields.')) return 'فیلدهای سفارشی';
    return 'سایر امکانات';
}
const activeCount = computed(() => props.permissions.filter((permission) => permission.is_active).length);
const retiredCount = computed(() => props.permissions.length - activeCount.value);
const filteredPermissions = computed(() => {
    const query = search.value.trim().toLowerCase();
    return props.permissions.filter((permission) => {
        if (!showRetired.value && !permission.is_active) return false;
        if (!query) return true;
        return [permission.name, permission.code, permission.description ?? '', groupLabel(permission.code)]
            .join(' ').toLowerCase().includes(query);
    });
});
const groupedPermissions = computed(() => {
    const groups = new Map<string, Permission[]>();
    for (const permission of filteredPermissions.value) {
        const label = groupLabel(permission.code);
        if (!groups.has(label)) groups.set(label, []);
        groups.get(label)!.push(permission);
    }
    return [...groups.entries()].map(([label, permissions]) => ({ label, permissions }));
});
</script>

<template>
    <Head title="مجوزها" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div><p class="text-xs font-semibold text-slate-400">مدیریت سازمان</p><h2 class="mt-1 text-xl font-bold text-slate-900">مجوزهای سیستم</h2></div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ activeCount }} مجوز فعال</span>
            </div>
        </template>
        <div class="min-h-screen bg-slate-50/70 py-8" dir="rtl">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="overflow-hidden rounded-3xl bg-gradient-to-l from-slate-950 via-slate-900 to-cyan-950 p-6 text-white shadow-xl shadow-slate-200/50 sm:p-8">
                    <div class="max-w-3xl"><div class="mb-4 inline-flex rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs font-medium text-cyan-100">فهرست مرجع مجوزها</div><h1 class="text-2xl font-black tracking-tight sm:text-3xl">دسترسی‌های سیستم را شفاف و قابل کنترل نگه دارید.</h1><p class="mt-3 text-sm leading-7 text-slate-300">این فهرست فقط مجوزهای تعریف‌شده در کد سامانه را نمایش می‌دهد. برای اعطای مجوزهای مدیریتی، از بخش نقش‌های کاربری استفاده کنید؛ مسئولیت‌های عملیاتی از جایگاه واقعی کاربر استخراج می‌شوند.</p></div>
                </section>
                <section class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl bg-white p-5 ring-1 ring-slate-200"><p class="text-sm text-slate-500">کل رکوردها</p><p class="mt-2 text-3xl font-black text-slate-900">{{ props.permissions.length }}</p></div>
                    <div class="rounded-2xl bg-white p-5 ring-1 ring-slate-200"><p class="text-sm text-slate-500">فعال</p><p class="mt-2 text-3xl font-black text-emerald-600">{{ activeCount }}</p></div>
                    <div class="rounded-2xl bg-white p-5 ring-1 ring-slate-200"><p class="text-sm text-slate-500">سیستمی</p><p class="mt-2 text-3xl font-black text-cyan-700">{{ props.permissions.filter(p => p.is_system && p.is_active).length }}</p></div>
                </section>
                <section class="space-y-6">\n                    <div class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-200">
                        <div class="border-b border-slate-100 px-6 py-5">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div><h3 class="font-black text-slate-900">فهرست مجوزها بر اساس بخش</h3><p class="mt-1 text-xs leading-6 text-slate-500">مجوزهای بازنشسته به‌صورت پیش‌فرض پنهان‌اند. تعریف و تغییر مجوزها از این صفحه انجام نمی‌شود تا مجوز بدون اتصال به عملیات سامانه ساخته نشود.</p></div>
                                <input v-model="search" type="search" placeholder="جستجوی نام، کد یا بخش..." class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-sm lg:max-w-sm" />
                            </div>
                            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                                <label class="flex items-center gap-2 text-xs font-semibold text-slate-600"><input v-model="showRetired" type="checkbox" class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-500" /> نمایش مجوزهای بازنشسته ({{ retiredCount }})</label>
                                <span class="text-xs text-slate-500">{{ filteredPermissions.length }} مورد</span>
                            </div>
                        </div>
                        <div v-if="filteredPermissions.length === 0" class="px-6 py-16 text-center"><p class="font-bold text-slate-700">{{ search ? 'موردی با این عبارت پیدا نشد.' : 'مجوزی برای نمایش وجود ندارد.' }}</p></div>
                        <div v-else class="divide-y divide-slate-100">
                            <section v-for="group in groupedPermissions" :key="group.label" class="border-b border-slate-100 last:border-b-0">
                                <div class="bg-slate-50 px-6 py-3"><h4 class="text-sm font-extrabold text-slate-800">{{ group.label }}</h4><p class="mt-1 text-xs text-slate-500">{{ group.permissions.length }} مجوز</p></div>
                                <div v-for="permission in group.permissions" :key="permission.id" class="flex flex-col gap-4 px-6 py-5 transition hover:bg-slate-50/80 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h4 class="font-bold text-slate-900">{{ permission.name }}</h4><span v-if="permission.is_system" class="rounded-full bg-cyan-50 px-2.5 py-1 text-[11px] font-bold text-cyan-700">سیستمی</span><span :class="permission.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'" class="rounded-full px-2.5 py-1 text-[11px] font-bold">{{ permission.is_active ? 'فعال' : 'غیرفعال' }}</span></div><p class="mt-2 font-mono text-xs text-slate-500" dir="ltr">{{ permission.code }}</p><p v-if="permission.description" class="mt-1 text-xs leading-6 text-slate-500">{{ permission.description }}</p></div>
                                <div class="shrink-0"><span v-if="permission.is_system" class="rounded-xl bg-cyan-50 px-3 py-2 text-xs font-bold text-cyan-800">تعریف‌شده در سامانه</span><span v-else class="rounded-xl bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">مجوز سفارشی · نیازمند بررسی اتصال</span></div>
                                </div>
                            </section>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
