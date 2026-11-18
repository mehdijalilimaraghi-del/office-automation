<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Archive, Plus, Search, SlidersHorizontal, FileText, FolderOpen, Building2, ArrowDownToLine } from '@lucide/vue';
interface RecordItem { id: number; title: string; document_type: string; archive_kind: string; archive_location: string | null; created_at: string; archive_restricted: boolean; archive_visibility: string | null }
const props = defineProps<{
    records: { data: RecordItem[]; total: number; from: number | null; to: number | null; prev_page_url: string | null; next_page_url: string | null };
    filters: { search?: string; kind?: string; category?: string; access?: string };
    canCreate: boolean;
    categories: string[];
    centralMode: boolean;
    centralArchive: { id: number; name: string; canFile: boolean; canManage: boolean } | null;
}>();
const filters = useForm({ search: props.filters.search ?? '', kind: props.filters.kind ?? '', category: props.filters.category ?? '', access: props.filters.access ?? '' });
const scopeLabels: Record<string, string> = { department: 'واحد مرتبط', organization: 'کل سازمان', selected: 'واحدها یا نقش‌های منتخب', restricted: 'افراد مشخص' };
function search(): void { filters.get(route(props.centralMode ? 'archive.central.index' : 'archive.index'), { preserveState: true, preserveScroll: true }); }
function reset(): void { filters.reset(); filters.search = ''; filters.kind = ''; filters.category = ''; filters.access = ''; search(); }
</script>
<template>
    <Head :title="centralMode ? 'بایگانی مرکزی سازمان' : 'آرشیو اسناد'" />
    <AuthenticatedLayout>
        <template #header><h1 class="text-xl font-bold">{{ centralMode ? 'بایگانی مرکزی سازمان' : 'آرشیو اسناد' }}</h1></template>
        <main dir="rtl" class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3"><span class="rounded-2xl bg-indigo-50 p-3 text-indigo-700"><Archive class="size-7" /></span><div><h2 class="text-xl font-bold text-slate-900">{{ centralMode ? 'اسناد نگهداری‌شده در بایگانی مرکزی' : 'اسناد در دسترس من' }}</h2><p class="mt-1 text-sm text-slate-500">{{ centralMode ? 'واحد مسئول نگهداری: ' + centralArchive?.name : 'اسناد مجاز سازمان، واحدهای شما و مدارک اشتراک‌گذاری‌شده با شما.' }}</p></div></div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('archive.departments.index')" class="inline-flex items-center gap-2 rounded-lg border bg-white px-4 py-2.5 text-sm font-semibold text-slate-700"><Building2 class="size-4" />بایگانی واحدهای من</Link>
                    <Link v-if="centralMode ? centralArchive?.canFile : canCreate" :href="route(centralMode ? 'archive.central.create' : 'archive.create')" class="inline-flex items-center gap-2 rounded-lg bg-indigo-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm"><Plus class="size-4" />{{ centralMode ? 'ثبت سند سازمانی' : 'ثبت سند واحد' }}</Link>
                </div>
            </div>
            <section v-if="centralArchive && (centralArchive.canFile || centralArchive.canManage)" class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-indigo-200 bg-indigo-50 p-5">
                <div><h3 class="font-bold text-indigo-950">بایگانی مرکزی سازمان</h3><p class="mt-1 text-sm leading-6 text-indigo-800">نامه‌ها و قراردادهایی که اصل یا فایل آن‌ها در {{ centralArchive.name }} نگهداری می‌شود، از این مسیر ثبت و اداره می‌شوند.</p></div>
                <div class="flex flex-wrap gap-2">
                    <Link v-if="!centralMode" :href="route('archive.central.index')" class="rounded-lg border border-indigo-200 bg-white px-4 py-2 text-sm font-semibold text-indigo-700">ورود به بایگانی مرکزی</Link>
                    <Link v-if="centralArchive.canFile && !centralMode" :href="route('archive.central.create')" class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-semibold text-white">ثبت سند سازمانی</Link>
                    <Link v-if="centralArchive.canManage" :href="route('archive.departments.edit', centralArchive.id)" class="rounded-lg border border-indigo-200 bg-white px-4 py-2 text-sm text-indigo-700">تنظیمات بایگانی مرکزی</Link>
                    <Link v-if="centralMode" :href="route('archive.index')" class="rounded-lg border border-indigo-200 bg-white px-4 py-2 text-sm text-indigo-700">همه اسناد در دسترس من</Link>
                </div>
            </section>
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <form class="space-y-4 border-b bg-slate-50/70 p-5" @submit.prevent="search">
                    <div class="flex flex-wrap gap-3">
                        <label class="relative min-w-52 flex-1"><span class="sr-only">عنوان یا توضیحات سند</span><Search class="pointer-events-none absolute right-3 top-3 size-5 text-slate-400" /><input v-model="filters.search" maxlength="100" placeholder="جست‌وجو در عنوان و توضیحات سند…" class="w-full rounded-lg border-slate-300 py-2.5 pr-10 text-sm" /></label>
                        <button type="submit" :disabled="filters.processing" class="rounded-lg bg-indigo-700 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-60">جست‌وجو</button>
                    </div>
                    <div class="flex flex-wrap items-end gap-3">
                        <span class="hidden self-center text-slate-400 sm:block"><SlidersHorizontal class="size-4" /></span>
                        <label class="grid min-w-36 gap-1.5 text-xs font-semibold text-slate-600">نوع نگهداری<select v-model="filters.kind" class="rounded-lg border-slate-300 text-sm"><option value="">همه اسناد</option><option value="digital">فایل دیجیتال</option><option value="physical">اصل فیزیکی</option></select></label>
                        <label class="grid min-w-36 gap-1.5 text-xs font-semibold text-slate-600">دسته سند<select v-model="filters.category" class="rounded-lg border-slate-300 text-sm"><option value="">همه دسته‌ها</option><option v-for="category in categories" :key="category" :value="category">{{ category }}</option></select></label>
                        <label class="grid min-w-36 gap-1.5 text-xs font-semibold text-slate-600">نحوه دسترسی<select v-model="filters.access" class="rounded-lg border-slate-300 text-sm"><option value="">همه اسناد مجاز</option><option value="mine">ثبت‌شده توسط من</option><option value="shared">اشتراک‌گذاری‌شده با من</option></select></label>
                        <button type="button" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100" @click="reset">پاک‌کردن فیلترها</button>
                    </div>
                </form>
                <div class="flex items-center justify-between border-b px-5 py-4"><h3 class="font-semibold">فهرست اسناد</h3><span class="text-sm text-slate-500">{{ new Intl.NumberFormat('fa-IR').format(records.total) }} سند</span></div>
                <div v-if="!records.data.length" class="grid justify-items-center gap-3 px-5 py-16 text-center"><FolderOpen class="size-12 text-slate-300" /><h3 class="font-semibold">سندی پیدا نشد</h3><p class="max-w-sm text-sm leading-6 text-slate-500">فیلترها را تغییر دهید یا اولین سند واحدتان را ثبت کنید. اسناد محدود با دسترسی مشخص نمایش داده می‌شوند.</p></div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[620px] text-right text-sm"><thead class="border-b bg-slate-50 text-xs text-slate-500"><tr><th class="px-5 py-3 font-medium">عنوان سند</th><th class="px-4 py-3 font-medium">دسته</th><th class="px-4 py-3 font-medium">نگهداری</th><th class="px-4 py-3 font-medium">دسترسی</th><th class="px-5 py-3"><span class="sr-only">عملیات</span></th></tr></thead>
                        <tbody class="divide-y divide-slate-100"><tr v-for="record in records.data" :key="record.id" class="hover:bg-indigo-50/30"><td class="px-5 py-4"><Link :href="route('archive.show', record.id)" class="inline-flex items-center gap-2 font-semibold text-slate-900 hover:text-indigo-700"><FileText class="size-5 shrink-0 text-indigo-500" />{{ record.title }}</Link><p v-if="record.archive_location" class="mr-7 mt-1 text-xs text-slate-500">{{ record.archive_location }}</p></td><td class="px-4 py-4 text-slate-600">{{ record.document_type }}</td><td class="px-4 py-4"><span class="rounded-md bg-slate-100 px-2 py-1 text-xs">{{ record.archive_kind === 'physical' ? 'اصل فیزیکی' : 'دیجیتال' }}</span></td><td class="px-4 py-4 text-slate-600">{{ scopeLabels[record.archive_visibility ?? (record.archive_restricted ? 'restricted' : 'department')] }}</td><td class="px-5 py-4"><a :href="route('documents.download', record.id)" :aria-label="'دریافت ' + record.title" class="inline-flex rounded-lg border p-2 text-slate-600 hover:bg-white"><ArrowDownToLine class="size-4" /></a></td></tr></tbody>
                    </table>
                </div>
                <nav v-if="records.prev_page_url || records.next_page_url" aria-label="صفحه‌بندی اسناد" class="flex items-center justify-between border-t px-5 py-4 text-sm"><span class="text-slate-500">{{ records.from }} تا {{ records.to }} از {{ records.total }}</span><div class="flex gap-2"><Link v-if="records.prev_page_url" :href="records.prev_page_url" class="rounded-lg border px-3 py-2">قبلی</Link><Link v-if="records.next_page_url" :href="records.next_page_url" class="rounded-lg border px-3 py-2">بعدی</Link></div></nav>
            </section>
        </main>
    </AuthenticatedLayout>
</template>
