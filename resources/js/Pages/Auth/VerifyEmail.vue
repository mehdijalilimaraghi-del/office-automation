<script setup lang="ts">
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    status?: string;
}>();

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="تأیید ایمیل" />

        <div class="mb-4 text-sm leading-6 text-gray-600">
            از ثبت‌نام شما متشکریم.
            پیش از شروع کار، لطفاً با کلیک روی لینکی که به ایمیل شما ارسال شده،
            آدرس ایمیل خود را تأیید کنید.
            اگر ایمیل را دریافت نکرده‌اید، می‌توانید درخواست ارسال مجدد آن را بدهید.
        </div>

        <div
            v-if="verificationLinkSent"
            class="mb-4 text-sm font-medium leading-6 text-green-600"
        >
            لینک جدید تأیید ایمیل به آدرس ایمیل شما ارسال شد.
        </div>

        <form @submit.prevent="submit">
            <div class="mt-4 flex items-center justify-between gap-4">
                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    ارسال مجدد ایمیل تأیید
                </PrimaryButton>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    خروج
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>