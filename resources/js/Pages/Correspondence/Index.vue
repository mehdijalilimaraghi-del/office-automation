<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface CorrespondenceRow {
    id: number;
    type: 'incoming' | 'outgoing' | 'internal';
    classification: 'ordinary' | 'confidential';
    subject: string;
    status: 'draft' | 'registered';
    can_register: boolean;
    registration_number: string | null;
    registered_at: string | null;
    created_at: string;
    creator: { id: number; name: string } | null;
    registrar: { id: number; name: string } | null;
    created_department: { id: number; name: string } | null;
    recipient_department: { id: number; name: string } | null;
}
interface PaginationLink { url: string | null; label: string; active: boolean; }
const props = defineProps<{
    correspondences: { data: CorrespondenceRow[]; total: number; from: number | null; to: number | null; links: PaginationLink[] };
    filters: { search: string; type: string; status: string };
    canCreate: boolean;
}>();
const search = ref(props.filters.search);
const type = ref(props.filters.type);
const status = ref(props.filters.status);
const typeLabels: Record<string, string> = { incoming: 'وارده', outgoing: 'صادره', internal: 'داخلی' };
const statusLabels: Record<string, string> = { draft: 'پیش‌نویس', registered: 'ثبت‌شده' };
const classificationLabels: Record<string, string> = { ordinary: 'عادی', confidential: 'محرمانه' };
function applyFilters(): void {
    router.get(route('correspondence.index'), { search: search.value || undefined, type: type.value || undefined, status: status.value || undefined }, { preserveState: true, replace: true });
}
function register(id: number): void {
    if (window.confirm('پس از ثبت نهایی، شماره مکاتبه ثابت خواهد شد. ادامه می‌دهید؟')) {
        router.post(route('correspondence.register', id), {}, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="مکاتبات اداری" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h2 class="text-xl font-bold text-slate-900">مکاتبات اداری</h2><p class="mt-1 text-sm text-slate-500">ثبت و پیگیری نامه‌های وارده، صادره و داخلی</p></div>
                <Link v-if="canCreate" :href="route('correspondence.create')" class="inline-flex items-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">ایجاد مکاتبه</Link>
            </div>
        </template>
        <div class="space-y-5 p-4 sm:p-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <input v-model="search" type="search" placeholder="جست‌وجو در موضوع یا شماره ثبت" class="rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" @keydown.enter="applyFilters" />
                    <select v-model="type" class="rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"><option value="">همه انواع</option><option value="incoming">وارده</option><option value="outgoing">صادره</option><option value="internal">داخلی</option></select>
                    <select v-model="status" class="rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"><option value="">همه وضعیت‌ها</option><option value="draft">پیش‌نویس</option><option value="registered">ثبت‌شده</option></select>
                    <button type="button" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700" @click="applyFilters">اعمال فیلتر</button>
                </div>
            </section>
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><h3 class="font-bold text-slate-900">فهرست مکاتبات</h3><span class="text-xs text-slate-500">{{ correspondences.total }} مورد</span></div>
                <div v-if="correspondences.data.length" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-right text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold text-slate-500"><tr><th class="px-5 py-3">موضوع</th><th class="px-5 py-3">نوع</th><th class="px-5 py-3">طبقه‌بندی</th><th class="px-5 py-3">شماره ثبت</th><th class="px-5 py-3">واحد</th><th class="px-5 py-3">ایجادکننده</th><th class="px-5 py-3">وضعیت</th><th class="px-5 py-3">عملیات</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="item in correspondences.data" :key="item.id" class="hover:bg-slate-50/70">
                                <td class="max-w-sm px-5 py-4"><Link :href="route('correspondence.show', item.id)" class="font-semibold text-indigo-700 hover:text-indigo-900 hover:underline">{{ item.subject }}</Link><div v-if="item.registration_number" class="mt-1 text-xs text-slate-500">ثبت: {{ item.registered_at ?? '—' }}</div></td>
                                <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ typeLabels[item.type] }}</td>
                                <td class="whitespace-nowrap px-5 py-4"><span :class="item.classification === 'confidential' ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-700'" class="rounded-full px-2.5 py-1 text-xs font-semibold">{{ classificationLabels[item.classification] }}</span></td>
                                <td class="whitespace-nowrap px-5 py-4 font-mono text-slate-700">{{ item.registration_number ?? '—' }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ item.recipient_department?.name ?? item.created_department?.name ?? '—' }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ item.creator?.name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4"><span :class="item.status === 'registered' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'" class="rounded-full px-2.5 py-1 text-xs font-semibold">{{ statusLabels[item.status] }}</span></td>
                                <td class="whitespace-nowrap px-5 py-4"><button v-if="item.can_register && item.status === 'draft'" type="button" class="rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50" @click="register(item.id)">ثبت نهایی</button><span v-else class="text-xs text-slate-400">—</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="px-6 py-14 text-center"><p class="font-semibold text-slate-800">مکاتبه‌ای پیدا نشد</p><p class="mt-1 text-sm text-slate-500">با تغییر فیلترها جست‌وجو کنید یا یک مکاتبه جدید بسازید.</p></div>
                <div v-if="correspondences.links.length > 3" class="flex flex-wrap gap-2 border-t border-slate-100 px-5 py-4">
                    <button v-for="(link, index) in correspondences.links" :key="index" type="button" :disabled="!link.url" class="rounded-lg border px-3 py-1.5 text-xs disabled:opacity-40" :class="link.active ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600'" v-html="link.label" @click="link.url && router.visit(link.url)" />
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
