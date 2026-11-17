<script setup lang="ts">
import JalaliDatePicker from '@/Components/JalaliDatePicker.vue';
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

type Action = 'move' | 'request' | 'approve' | 'reject' | 'checkout' | 'return';
interface CustodyEvent {
    id: number; action: Action; location: string | null; recipient: string | null;
    approval_required: boolean; due_on: string | null; note: string | null; created_at: string; actor: { name: string } | null;
}
const actions: Record<Action, string> = {
    move: 'جابه‌جایی داخلی', request: 'درخواست خروج', approve: 'تأیید خروج',
    reject: 'رد یا لغو خروج', checkout: 'تحویل اصل مدرک', return: 'بازگشت اصل مدرک',
};
const props = defineProps<{
    record: { id: number; title: string; description: string | null; document_type: string; archive_kind: string; archive_location: string | null };
    versions: { version_number: number; original_name: string; created_at: string }[];
    canUpdate: boolean;
    custody: { data: CustodyEvent[]; prev_page_url: string | null; next_page_url: string | null } | null;
    custodyCurrent: Pick<CustodyEvent, 'action' | 'recipient' | 'due_on' | 'approval_required'> | null;
}>();

const availableActions = computed<Action[]>(() => {
    switch (props.custodyCurrent?.action) {
        case 'request': return props.custodyCurrent.approval_required ? ['approve', 'reject'] : ['checkout', 'reject'];
        case 'approve': return ['checkout', 'reject'];
        case 'checkout': return ['return'];
        default: return ['move', 'request'];
    }
});
const custodyForm = useForm({ action: '' as Action | '', location: '', recipient: '', due_on: null as string | null, note: '' });
function saveCustody(): void {
    custodyForm.post(route('archive.custody.store', props.record.id), {
        preserveScroll: true,
        onSuccess: () => custodyForm.reset(),
    });
}
const dateFormatter = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { dateStyle: 'medium', timeZone: 'Asia/Tehran' });
function formatDate(value: string): string {
    return dateFormatter.format(new Date(value));
}

const form = useForm({ file: null as File | null });
function save(event: Event): void {
    const element = event.currentTarget as HTMLFormElement;
    form.post(route('archive.versions.store', props.record.id), {
        preserveScroll: true,
        onSuccess: () => { form.reset(); element.reset(); },
    });
}
</script>

<template>
    <Head :title="record.title" />
    <AuthenticatedLayout>
        <template #header><h1 class="text-xl font-semibold">{{ record.title }}</h1></template>
        <div dir="rtl" class="mx-auto grid max-w-4xl gap-4 p-4 sm:p-6">
            <Link :href="route('archive.index')" class="text-indigo-700 hover:underline">بازگشت به آرشیو</Link>
            <section class="space-y-2 rounded-xl border bg-white p-4">
                <p>{{ record.document_type }} · {{ record.archive_kind === 'physical' ? 'مدرک فیزیکی' : 'مدرک دیجیتال' }}</p>
                <p v-if="record.archive_location">محل نگهداری اصل: {{ record.archive_location }}</p>
                <p v-if="record.description" class="whitespace-pre-wrap">{{ record.description }}</p>
            </section>
            <section class="space-y-3 rounded-xl border bg-white p-4">
                <h2 class="font-semibold">نسخه‌های مدرک</h2>
                <ul class="divide-y">
                    <li v-for="version in versions" :key="version.version_number" class="flex flex-wrap items-center justify-between gap-2 py-3">
                        <span>نسخه {{ version.version_number }} · <bdi>{{ version.original_name }}</bdi></span>
                        <a :href="route('documents.download', { document: record.id, version: version.version_number })" class="text-indigo-700 hover:underline">دریافت نسخه {{ version.version_number }}</a>
                    </li>
                </ul>
                <form v-if="canUpdate" class="flex flex-wrap items-end gap-3 border-t pt-3" @submit.prevent="save">
                    <label class="grid gap-1">فایل نسخه جدید
                        <input type="file" required class="max-w-full rounded-md border p-2"
                            @change="form.file = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                    </label>
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-700 px-4 py-2 text-white disabled:opacity-60">
                        {{ form.processing ? 'در حال ذخیره…' : 'ذخیره نسخه جدید' }}
                    </button>
                    <p v-if="form.errors.file" role="alert" class="w-full text-sm text-red-700">{{ form.errors.file }}</p>
                </form>
            </section>
            <section v-if="custody" class="space-y-3 rounded-xl border bg-white p-4">
                <h2 class="font-semibold">گردش اصل مدرک</h2>
                <p v-if="custodyCurrent?.action === 'checkout'" class="text-amber-800">
                    اصل مدرک نزد {{ custodyCurrent.recipient }} است.
                    <template v-if="custodyCurrent.due_on"> موعد بازگشت {{ formatDate(custodyCurrent.due_on) }}</template>
                </p>
                <ul v-if="custody.data.length" class="divide-y">
                    <li v-for="event in custody.data" :key="event.id" class="grid gap-1 py-2">
                        <strong>{{ actions[event.action] }} · {{ event.actor?.name }}</strong>
                        <span>{{ formatDate(event.created_at) }} · {{ event.location }}</span>
                        <span v-if="event.recipient">تحویل‌گیرنده پیشنهادی یا ثبت‌شده: {{ event.recipient }}</span>
                        <span v-if="event.note">{{ event.note }}</span>
                    </li>
                </ul>
                <p v-else class="text-sm text-slate-600">هنوز رویدادی برای اصل مدرک ثبت نشده است.</p>
                <nav v-if="custody.prev_page_url || custody.next_page_url" aria-label="صفحه‌بندی رویدادها" class="flex gap-3">
                    <Link v-if="custody.prev_page_url" :href="custody.prev_page_url">صفحه قبل</Link>
                    <Link v-if="custody.next_page_url" :href="custody.next_page_url">صفحه بعد</Link>
                </nav>
                <form v-if="canUpdate" class="grid gap-3 border-t pt-3 sm:grid-cols-2" @submit.prevent="saveCustody">
                    <label class="grid gap-1">اقدام
                        <select v-model="custodyForm.action" class="rounded-md border p-2" required>
                            <option value="">انتخاب اقدام</option>
                            <option v-for="action in availableActions" :key="action" :value="action">{{ actions[action] }}</option>
                        </select>
                    </label>
                    <label v-if="['move', 'return'].includes(custodyForm.action)" class="grid gap-1">محل جدید
                        <input v-model="custodyForm.location" maxlength="255" required class="rounded-md border p-2" />
                    </label>
                    <template v-if="custodyForm.action === 'request'">
                        <label class="grid gap-1">تحویل‌گیرنده
                            <input v-model="custodyForm.recipient" maxlength="255" required class="rounded-md border p-2" />
                        </label>
                        <div class="grid gap-1" role="group" aria-labelledby="archive-due-label">
                            <span id="archive-due-label">موعد بازگشت</span><JalaliDatePicker v-model="custodyForm.due_on" required />
                        </div>
                    </template>
                    <label v-if="custodyForm.action" class="grid gap-1">توضیح
                        <textarea v-model="custodyForm.note" maxlength="500" :required="['request', 'reject'].includes(custodyForm.action)" class="rounded-md border p-2" />
                    </label>
                    <ul v-if="custodyForm.hasErrors" role="alert" class="text-sm text-red-700 sm:col-span-2">
                        <li v-for="(error, field) in custodyForm.errors" :key="field">{{ error }}</li>
                    </ul>
                    <div class="sm:col-span-2">
                        <button type="submit" :disabled="custodyForm.processing || !custodyForm.action" class="rounded-md bg-indigo-700 px-4 py-2 text-white disabled:opacity-60">ثبت اقدام</button>
                    </div>
                </form>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
