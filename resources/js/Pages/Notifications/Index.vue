<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

interface NotificationItem {
    id: string;
    type: string;
    title: string;
    message: string;
    task_id: number | null;
    url: string | null;
    read_at: string | null;
    created_at: string | null;
}

defineProps<{
    notifications: NotificationItem[];
    unreadCount: number;
}>();

const formatDate = (value: string | null) => {
    if (!value) return '—';
    return new Intl.DateTimeFormat('fa-IR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const markAsRead = (id: string) => {
    router.patch(route('notifications.read', id), {}, { preserveScroll: true });
};

const markAllAsRead = () => {
    router.patch(route('notifications.read-all'), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="اعلان‌ها" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-5xl space-y-6">
                <section class="overflow-hidden rounded-3xl bg-slate-950 p-6 text-white shadow-xl sm:p-8">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-sm font-medium text-teal-300">مرکز اطلاع‌رسانی</p>
                            <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">اعلان‌های من</h1>
                            <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-300">
                                رویدادهای مهم کارها، ارجاعات و فرایند تأیید را از اینجا دنبال کنید.
                            </p>
                        </div>
                        <button
                            v-if="unreadCount > 0"
                            type="button"
                            class="rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/20"
                            @click="markAllAsRead"
                        >
                            خواندن همه
                        </button>
                    </div>
                </section>

                <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div
                        v-if="notifications.length === 0"
                        class="px-6 py-16 text-center"
                    >
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl">✓</div>
                        <h2 class="mt-4 text-lg font-bold text-slate-900">اعلان جدیدی ندارید</h2>
                        <p class="mt-2 text-sm text-slate-500">وقتی رویداد مهمی برای شما اتفاق بیفتد، اینجا نمایش داده می‌شود.</p>
                    </div>

                    <div v-else class="divide-y divide-slate-100">
                        <div
                            v-for="item in notifications"
                            :key="item.id"
                            class="flex flex-col gap-4 px-5 py-5 transition hover:bg-slate-50 sm:flex-row sm:items-start sm:justify-between sm:px-6"
                            :class="{ 'bg-teal-50/50': !item.read_at }"
                        >
                            <div class="flex min-w-0 gap-4">
                                <div
                                    class="mt-1 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                                    :class="item.read_at ? 'bg-slate-100 text-slate-500' : 'bg-teal-100 text-teal-700'"
                                >
                                    !
                                </div>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="font-bold text-slate-900">{{ item.title }}</h2>
                                        <span
                                            v-if="!item.read_at"
                                            class="rounded-full bg-teal-100 px-2 py-0.5 text-xs font-semibold text-teal-700"
                                        >
                                            جدید
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm leading-6 text-slate-600">{{ item.message }}</p>
                                    <p class="mt-2 text-xs text-slate-400">{{ formatDate(item.created_at) }}</p>
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <Link
                                    v-if="item.url"
                                    :href="item.url"
                                    class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                    @click="!item.read_at && markAsRead(item.id)"
                                >
                                    مشاهده
                                </Link>
                                <button
                                    v-if="!item.read_at"
                                    type="button"
                                    class="rounded-xl px-3 py-2 text-xs font-semibold text-teal-700 hover:bg-teal-50"
                                    @click="markAsRead(item.id)"
                                >
                                    خوانده شد
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>