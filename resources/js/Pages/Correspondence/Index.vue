<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

interface Correspondence {
    id: number;
    registration_number: string;
    type: 'incoming' | 'outgoing' | 'internal';
    status: 'draft' | 'registered';
    registered_at: string | null;
    subject: string;
    creator: { name: string } | null;
}

const props = defineProps<{ correspondences: Correspondence[] }>();
const form = useForm({
    type: 'incoming' as Correspondence['type'],
    subject: '',
    body: '',
});

const submit = (): void => {
    form.post(route('correspondence.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const typeLabels: Record<Correspondence['type'], string> = {
    incoming: 'وارده',
    outgoing: 'صادره',
    internal: 'داخلی',
};

const statusLabels: Record<Correspondence['status'], string> = {
    draft: 'پیش‌نویس',
    registered: 'ثبت‌شده',
};
</script>

<template>
    <Head title="مکاتبات" />
    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-bold text-gray-900">مکاتبات</h2>
                <p class="mt-1 text-sm text-gray-500">ثبت و پیگیری مکاتبات سازمان</p>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <section class="rounded-xl border bg-white p-5 shadow-sm">
                <h3 class="font-bold text-gray-900">ثبت مکاتبه</h3>
                <form class="mt-4 grid gap-4 md:grid-cols-3" @submit.prevent="submit">
                    <select v-model="form.type" class="h-10 rounded-lg border px-3 text-sm">
                        <option value="incoming">وارده</option>
                        <option value="outgoing">صادره</option>
                        <option value="internal">داخلی</option>
                    </select>
                    <input v-model="form.subject" required maxlength="500" class="h-10 rounded-lg border px-3 text-sm md:col-span-2" placeholder="موضوع مکاتبه" />
                    <textarea v-model="form.body" rows="3" class="rounded-lg border p-3 text-sm md:col-span-3" placeholder="متن یا توضیحات"></textarea>
                    <button :disabled="form.processing" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50 md:col-span-3">
                        {{ form.processing ? 'در حال ثبت...' : 'ثبت مکاتبه' }}
                    </button>
                </form>
            </section>

            <section class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <div class="border-b px-5 py-4">
                    <h3 class="font-bold">فهرست مکاتبات</h3>
                </div>
                <div v-if="props.correspondences.length === 0" class="p-10 text-center text-sm text-gray-500">هنوز مکاتبه‌ای ثبت نشده است.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-right">شماره</th>
                                <th class="px-4 py-3 text-right">نوع</th>
                                <th class="px-4 py-3 text-right">موضوع</th>
                                <th class="px-4 py-3 text-right">وضعیت</th>
                                <th class="px-4 py-3 text-right">ثبت‌کننده</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="item in props.correspondences" :key="item.id">
                                <td class="px-5 py-3 font-medium">{{ item.registration_number }}</td>
                                <td class="px-4 py-3">{{ typeLabels[item.type] }}</td>
                                <td class="px-4 py-3">{{ item.subject }}</td>
                                <td class="px-4 py-3">{{ statusLabels[item.status] }}</td>
                                <td class="px-4 py-3">{{ item.creator?.name ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
