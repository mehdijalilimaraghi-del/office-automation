<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Building2, Settings2 } from '@lucide/vue';
defineProps<{ departments: { id: number; name: string }[]; centralDepartmentId: number | null }>();
</script>
<template>
    <Head title="بایگانی واحدهای من" />
    <AuthenticatedLayout>
        <template #header><h1 class="text-xl font-bold text-slate-900">بایگانی واحدهای من</h1></template>
        <main dir="rtl" class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div><h2 class="text-lg font-bold">مدیریت بایگانی واحد</h2><p class="mt-1 text-sm text-slate-600">واحد را برای تعیین مسئول بایگانی، دسته‌های اسناد و محل‌های نگهداری انتخاب کنید.</p></div>
                <Link :href="route('archive.index')" class="inline-flex items-center gap-2 rounded-lg border bg-white px-4 py-2 text-sm"><ArrowLeft class="size-4" />اسناد من</Link>
            </div>
            <div v-if="departments.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link v-for="department in departments" :key="department.id" :href="route('archive.departments.edit', department.id)" class="group rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-indigo-300 hover:shadow-md focus-visible:outline-indigo-600">
                    <Building2 class="mb-4 size-8 text-indigo-600" /><span v-if="Number(department.id) === centralDepartmentId" class="mb-3 inline-block rounded-md bg-indigo-100 px-2 py-1 text-xs font-semibold text-indigo-800">بایگانی مرکزی سازمان</span><h3 class="font-bold">{{ department.name }}</h3>
                    <span class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-indigo-700"><Settings2 class="size-4" />تنظیمات بایگانی</span>
                </Link>
            </div>
            <div v-if="centralDepartmentId && departments.some(unit => Number(unit.id) === centralDepartmentId)" class="flex flex-wrap gap-3 rounded-xl border border-indigo-100 bg-indigo-50 p-4"><Link :href="route('archive.central.index')" class="text-sm font-semibold text-indigo-700">اسناد بایگانی مرکزی سازمان</Link><Link :href="route('archive.central.create')" class="text-sm font-semibold text-indigo-700">ثبت سند سازمانی</Link></div>
            <p v-if="!departments.length" class="rounded-2xl border border-dashed bg-white p-10 text-center text-slate-600">مسئولیت مدیریت بایگانی واحدی به شما واگذار نشده است.</p>
        </main>
    </AuthenticatedLayout>
</template>
