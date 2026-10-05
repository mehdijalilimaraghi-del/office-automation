
<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="ورود" />

        <div class="mb-6 text-center">
            <h1 class="text-2xl font-bold text-gray-900">
                ورود به سامانه
            </h1>

            <p class="mt-2 text-sm text-gray-600">
                برای ورود، اطلاعات حساب کاربری خود را وارد کنید.
            </p>
        </div>

        <div
            v-if="status"
            class="mb-4 rounded-md bg-green-50 p-3 text-sm font-medium text-green-700"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="username" value="نام کاربری" />

                <TextInput
                    id="username"
                    type="text"
                    class="mt-1 block w-full text-right"
                    v-model="form.username"
                    required
                    autofocus
                    autocomplete="username"
                    dir="ltr"
                />

                <InputError class="mt-2" :message="form.errors.username" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="رمز عبور" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full text-right"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    dir="ltr"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <label class="flex items-center">
                    <Checkbox
                        name="remember"
                        v-model:checked="form.remember"
                    />

                    <span class="me-2 text-sm text-gray-600">
                        مرا به خاطر بسپار
                    </span>
                </label>
            </div>

            <div class="mt-6 flex items-center justify-between">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    رمز عبور را فراموش کرده‌اید؟
                </Link>

                <PrimaryButton
                    class="me-auto"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    ورود
                </PrimaryButton>
            </div>

        </form>
    </GuestLayout>
</template>

