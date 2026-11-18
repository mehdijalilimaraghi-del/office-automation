<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Plus, Trash2, Save, UserRound, FolderTree, MapPin } from '@lucide/vue';
const props = defineProps<{
    department: { id: number; name: string };
    settings: { archivist_id: number | null; categories: { name: string; approval_required: boolean }[]; locations: { building: string; room: string; shelf: string; folder: string }[] };
    canAssign: boolean;
    centralMode: boolean;
    members: { id: number; name: string }[];
}>();
const form = useForm({
    ...(props.canAssign ? { archivist_id: props.settings.archivist_id ?? '' as number | string } : {}),
    categories: props.settings.categories.map(category => ({ ...category })),
    locations: props.settings.locations.map(location => ({ ...location })),
});
function save(): void { form.put(route('archive.departments.update', props.department.id), { preserveScroll: true }); }
</script>
<template>
    <Head :title="'بایگانی ' + department.name" />
    <AuthenticatedLayout>
        <template #header><h1 class="text-xl font-bold">{{ centralMode ? 'تنظیمات بایگانی مرکزی سازمان' : 'بایگانی ' + department.name }}</h1></template>
        <main dir="rtl" class="mx-auto max-w-5xl space-y-6 p-4 pb-28 sm:p-6">
            <Link :href="route('archive.departments.index')" class="text-sm font-semibold text-indigo-700">بازگشت به واحدهای من</Link>
            <form class="space-y-6" @submit.prevent="save">
                <section v-if="canAssign" class="rounded-2xl border bg-white p-5 shadow-sm sm:p-6">
                    <h2 class="flex items-center gap-2 font-bold"><UserRound class="size-5 text-indigo-600" />مسئول بایگانی واحد</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">یکی از اعضای فعال همین واحد را برای مدیریت دسته‌ها و محل‌های بایگانی تعیین کنید.</p>
                    <label class="mt-4 grid max-w-md gap-2 text-sm font-medium">کاربر مسئول
                        <select v-model="form.archivist_id" class="rounded-lg border-slate-300"><option value="">تعیین نشده</option><option v-for="member in members" :key="member.id" :value="member.id">{{ member.name }}</option></select>
                    </label>
                </section>
                <section class="rounded-2xl border bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex items-center justify-between gap-3"><h2 class="flex items-center gap-2 font-bold"><FolderTree class="size-5 text-indigo-600" />دسته‌های اسناد</h2><button type="button" class="inline-flex items-center gap-1 rounded-lg border px-3 py-2 text-sm" @click="form.categories.push({ name: '', approval_required: true })"><Plus class="size-4" />افزودن دسته</button></div>
                    <p class="mt-2 text-sm leading-6 text-slate-600">دسته‌هایی مانند قرارداد، صورت‌جلسه یا استاندارد طراحی را یک بار تعریف کنید. ثبت‌کننده سند از این فهرست انتخاب می‌کند.</p>
                    <div v-for="(category, index) in form.categories" :key="index" class="mt-4 flex flex-wrap items-end gap-4 rounded-xl bg-slate-50 p-4">
                        <label class="grid flex-1 gap-2 text-sm">نام دسته<input v-model="category.name" required maxlength="100" class="rounded-lg border-slate-300" /></label>
                        <label class="flex items-center gap-2 py-2 text-sm"><input v-model="category.approval_required" type="checkbox" class="rounded text-indigo-600" />خروج اصل نیاز به تأیید دارد</label>
                        <button type="button" :aria-label="'حذف دسته ' + category.name" class="rounded-lg p-2 text-red-600 hover:bg-red-50" @click="form.categories.splice(index, 1)"><Trash2 class="size-4" /></button>
                    </div>
                    <p v-if="!form.categories.length" class="mt-5 rounded-xl border border-dashed p-5 text-sm text-slate-500">هنوز دسته‌ای برای این واحد تعریف نشده است.</p>
                </section>
                <section class="rounded-2xl border bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex items-center justify-between gap-3"><h2 class="flex items-center gap-2 font-bold"><MapPin class="size-5 text-indigo-600" />محل‌های نگهداری اصل</h2><button type="button" class="inline-flex items-center gap-1 rounded-lg border px-3 py-2 text-sm" @click="form.locations.push({ building: '', room: '', shelf: '', folder: '' })"><Plus class="size-4" />افزودن محل</button></div>
                    <p class="mt-2 text-sm leading-6 text-slate-600">نشانی واقعی نگهداری اصل را مشخص کنید تا همکاران هنگام ثبت یا بازگرداندن سند، محل کامل را انتخاب کنند.</p>
                    <div v-for="(location, index) in form.locations" :key="index" class="mt-4 grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_1fr_auto]">
                        <label class="grid gap-2 text-sm">ساختمان یا بایگانی<input v-model="location.building" required maxlength="60" class="w-full rounded-lg border-slate-300" /></label>
                        <label class="grid gap-2 text-sm">اتاق<input v-model="location.room" required maxlength="60" class="w-full rounded-lg border-slate-300" /></label>
                        <label class="grid gap-2 text-sm">قفسه یا کمد<input v-model="location.shelf" required maxlength="60" class="w-full rounded-lg border-slate-300" /></label>
                        <label class="grid gap-2 text-sm">زونکن یا پوشه<input v-model="location.folder" maxlength="60" class="w-full rounded-lg border-slate-300" /></label>
                        <button type="button" :aria-label="'حذف محل ' + (index + 1)" class="self-end justify-self-start rounded-lg p-2 text-red-600 hover:bg-red-50" @click="form.locations.splice(index, 1)"><Trash2 class="size-4" /></button>
                    </div>
                    <p v-if="!form.locations.length" class="mt-5 rounded-xl border border-dashed p-5 text-sm text-slate-500">هنوز محلی برای نگهداری اسناد فیزیکی تعریف نشده است.</p>
                </section>
                <ul v-if="form.hasErrors" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><li v-for="(error, field) in form.errors" :key="field">{{ error }}</li></ul>
                <div class="sticky bottom-4 flex justify-end rounded-xl border bg-white/95 p-3 shadow-lg"><button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-lg bg-indigo-700 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-60"><Save class="size-4" />{{ form.processing ? 'در حال ذخیره…' : 'ذخیره تنظیمات واحد' }}</button></div>
            </form>
        </main>
    </AuthenticatedLayout>
</template>
