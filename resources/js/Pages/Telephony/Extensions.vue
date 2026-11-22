<script setup lang="ts">
import { computed, ref, nextTick } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TelephonyNav from '@/Components/TelephonyNav.vue';
import { Plus, Download, Search, Phone, X } from '@lucide/vue';

type Extension = { id: number; number: string; label: string; owner_type: 'user' | 'department'; user_id: number | null; department_id: number | null; is_active: boolean };
const props = defineProps<{ extensions: Extension[]; users: { id: number; name: string }[]; departments: { id: number; name: string }[] }>();
const query = ref('');
const filtered = computed(() => props.extensions.filter(item => (item.number + ' ' + item.label).includes(query.value.trim())));
const editor = ref<HTMLDialogElement | null>(null);
const numberInput = ref<HTMLInputElement | null>(null);
const editing = ref<number | null>(null);
const form = useForm({ number: '', label: '', owner_type: 'user' as 'user' | 'department', user_id: null as number | null, department_id: null as number | null, is_active: true });
function open(item?: Extension): void {
    form.reset(); form.clearErrors(); editing.value = item?.id ?? null;
    if (item) Object.assign(form, { number: item.number, label: item.label, owner_type: item.owner_type, user_id: item.user_id, department_id: item.department_id, is_active: item.is_active });
    editor.value?.showModal(); void nextTick(() => numberInput.value?.focus());
}
function ownerChanged(): void { form.user_id = null; form.department_id = null; }
function save(): void {
    const options = { preserveScroll: true, onSuccess: () => editor.value?.close() };
    if (editing.value !== null) form.put(route('telephony.extensions.update', editing.value), options);
    else form.post(route('telephony.extensions.store'), options);
}
function owner(item: Extension): string {
    return item.owner_type === 'user' ? props.users.find(user => user.id === item.user_id)?.name ?? 'کاربر غیرفعال' : props.departments.find(department => department.id === item.department_id)?.name ?? 'واحد غیرفعال';
}
</script>
<template>
<Head title="مدیریت داخلی‌ها" />
<AuthenticatedLayout>
<template #header><h1 class="text-xl font-bold">تلفن سازمانی</h1></template>
<main dir="rtl" class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6">
    <TelephonyNav active="extensions" />
    <section class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border bg-white p-6">
        <div class="flex items-center gap-4"><span class="rounded-2xl bg-indigo-50 p-3 text-indigo-700"><Phone class="size-6" /></span><div><h2 class="text-lg font-bold">داخلی‌های سازمان</h2><p class="mt-2 text-sm leading-6 text-slate-600">هر داخلی را به یک همکار یا واحد اختصاص دهید.</p></div></div>
        <button class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-indigo-700 px-4 py-2 font-semibold text-white" @click="open()"><Plus class="size-4" />داخلی جدید</button>
    </section>
    <section class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-indigo-100 bg-indigo-50 p-5">
        <div><h3 class="font-semibold text-indigo-950">اعمال تنظیمات روی مرکز تلفن</h3><p class="mt-2 max-w-2xl text-sm leading-7 text-indigo-900">بسته را روی کامپیوتر مرکز تلفن باز و «Apply-OfficeConfig.cmd» را اجرا کنید. داخلی‌های هم‌شماره رمز قبلی را حفظ می‌کنند. داخلی‌هایی که در این فهرست نیستند، پس از اعمال بسته از تنظیمات مرکز حذف می‌شوند.</p></div>
        <a v-if="extensions.some(item => item.is_active)" :href="route('telephony.configuration.download')" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-indigo-200 bg-white px-4 py-2 font-semibold text-indigo-700"><Download class="size-4" />دریافت بسته تنظیمات</a><p v-else class="text-sm font-semibold text-indigo-900">برای دریافت بسته، ابتدا یک داخلی فعال تعریف کنید.</p>
    </section>
    <section class="overflow-hidden rounded-2xl border bg-white">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b p-5"><h3 class="font-bold">فهرست داخلی‌ها <span class="mr-2 rounded-full bg-slate-100 px-3 py-1 text-xs">{{ extensions.length }}</span></h3><label class="flex items-center gap-2 rounded-xl border px-3 py-2"><Search class="size-4 text-slate-400" /><span class="sr-only">جست‌وجوی شماره یا عنوان</span><input v-model="query" placeholder="شماره یا عنوان داخلی" class="w-52 border-0 p-0 text-sm focus:ring-0" /></label></div>
        <div v-if="!filtered.length" class="px-6 py-14 text-center"><Phone class="mx-auto mb-4 size-9 text-slate-300" /><p class="font-semibold">{{ extensions.length ? 'داخلی مطابق جست‌وجو پیدا نشد' : 'هنوز داخلی تعریف نشده است' }}</p><p class="mt-2 text-sm text-slate-500">برای حفظ داخلی‌های آزمایشی، شماره‌های 1001 و 1002 را نیز در این فهرست تعریف کنید.</p></div>
        <ul v-else class="divide-y">
            <li v-for="item in filtered" :key="item.id" class="flex flex-wrap items-center justify-between gap-4 p-5">
                <div class="flex items-center gap-4"><span dir="ltr" class="min-w-20 rounded-xl bg-slate-100 px-3 py-3 text-center font-mono text-lg font-bold">{{ item.number }}</span><div><p class="font-semibold">{{ item.label }}</p><p class="mt-1 text-sm text-slate-500">{{ item.owner_type === 'user' ? 'همکار' : 'واحد' }}: {{ owner(item) }}</p></div></div>
                <div class="flex items-center gap-4"><span class="rounded-full px-3 py-1 text-xs font-semibold" :class="item.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'">{{ item.is_active ? 'فعال در تنظیمات' : 'غیرفعال' }}</span><button class="min-h-11 rounded-lg border px-4 text-sm font-semibold" @click="open(item)">ویرایش</button></div>
            </li>
        </ul>
    </section>
    <dialog ref="editor" aria-labelledby="extension-editor-title" class="m-auto w-[min(36rem,calc(100%_-_2rem))] max-h-[85dvh] overflow-y-auto rounded-2xl border-0 p-0 shadow-xl backdrop:bg-slate-950/40" @cancel="form.processing && $event.preventDefault()">
        <form class="space-y-5 p-6" @submit.prevent="save">
            <div class="flex items-center justify-between"><h2 id="extension-editor-title" class="text-lg font-bold">{{ editing !== null ? 'ویرایش داخلی' : 'تعریف داخلی' }}</h2><button type="button" :disabled="form.processing" aria-label="بستن" class="rounded-lg p-2" @click="editor?.close()"><X class="size-5" /></button></div>
            <label class="block text-sm font-semibold">شماره داخلی<input ref="numberInput" v-model="form.number" dir="ltr" inputmode="numeric" maxlength="6" required class="mt-2 w-full rounded-xl border-slate-300" /><span class="mt-1 block text-xs font-normal text-slate-500">۳ تا ۶ رقم؛ شماره‌های 9196 و 9197 مخصوص آزمون هستند.</span></label>
            <label class="block text-sm font-semibold">عنوان<input v-model="form.label" maxlength="100" required class="mt-2 w-full rounded-xl border-slate-300" placeholder="مثلاً دبیرخانه یا نام همکار" /></label>
            <label class="block text-sm font-semibold">اختصاص داخلی به<select v-model="form.owner_type" class="mt-2 w-full rounded-xl border-slate-300" @change="ownerChanged"><option value="user">همکار</option><option value="department">واحد سازمانی</option></select></label>
            <label v-if="form.owner_type === 'user'" class="block text-sm font-semibold">همکار<select v-model="form.user_id" required class="mt-2 w-full rounded-xl border-slate-300"><option :value="null" disabled>انتخاب همکار</option><option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option></select></label>
            <label v-else class="block text-sm font-semibold">واحد<select v-model="form.department_id" required class="mt-2 w-full rounded-xl border-slate-300"><option :value="null" disabled>انتخاب واحد</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select></label>
            <label class="flex items-center gap-3 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" />داخلی فعال باشد</label>
            <ul v-if="Object.keys(form.errors).length" role="alert" class="space-y-1 rounded-xl bg-red-50 p-4 text-sm text-red-700"><li v-for="(error, field) in form.errors" :key="field">{{ error }}</li></ul>
            <div class="flex justify-end gap-3 border-t pt-4"><button type="button" :disabled="form.processing" class="min-h-11 rounded-xl border px-5" @click="editor?.close()">انصراف</button><button :disabled="form.processing" class="min-h-11 rounded-xl bg-indigo-700 px-5 font-semibold text-white disabled:opacity-50">{{ form.processing ? 'در حال ذخیره…' : 'ذخیره داخلی' }}</button></div>
        </form>
    </dialog>
</main>
</AuthenticatedLayout>
</template>
