<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

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
        <Head title="ورود به سامانه" />

        <div class="space-y-8">
            <div class="space-y-3 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-900 text-white shadow-sm">
                    <svg
                        class="h-7 w-7"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                        <path d="m10 17 5-5-5-5" />
                        <path d="M15 12H3" />
                    </svg>
                </div>

                <div class="space-y-1.5">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-950">
                        ورود به سامانه
                    </h1>

                    <p class="text-sm leading-6 text-slate-500">
                        نام کاربری و رمز عبور خود را وارد کنید.
                    </p>
                </div>
            </div>

            <div
                v-if="status"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm leading-6 font-medium text-emerald-800"
                role="status"
            >
                {{ status }}
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <div class="space-y-2">
                    <InputLabel
                        for="username"
                        value="نام کاربری"
                        class="text-sm font-semibold text-slate-800"
                    />

                    <TextInput
                        id="username"
                        type="text"
                        class="block min-h-12 w-full rounded-xl border-slate-300 bg-white px-4 text-base text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                        v-model="form.username"
                        required
                        autofocus
                        autocomplete="username"
                        dir="ltr"
                        spellcheck="false"
                        autocapitalize="none"
                    />

                    <InputError class="mt-1" :message="form.errors.username" />
                </div>

                <div class="space-y-2">
                    <InputLabel
                        for="password"
                        value="رمز عبور"
                        class="text-sm font-semibold text-slate-800"
                    />

                    <div class="relative">
                        <TextInput
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            class="block min-h-12 w-full rounded-xl border-slate-300 bg-white px-4 pe-12 text-base tracking-normal text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            v-model="form.password"
                            required
                            autocomplete="current-password"
                            dir="ltr"
                        />

                        <button
                            type="button"
                            class="absolute inset-y-0 end-0 flex w-12 items-center justify-center rounded-e-xl text-slate-500 transition hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-inset"
                            :aria-label="showPassword ? 'مخفی کردن رمز عبور' : 'نمایش رمز عبور'"
                            :title="showPassword ? 'مخفی کردن رمز عبور' : 'نمایش رمز عبور'"
                            @click="showPassword = !showPassword"
                        >
                            <svg
                                v-if="!showPassword"
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M2.06 12.35a1 1 0 0 1 0-.7C3.75 7.83 7.59 5 12 5s8.25 2.83 9.94 6.65a1 1 0 0 1 0 .7C20.25 16.17 16.41 19 12 19s-8.25-2.83-9.94-6.65Z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>

                            <svg
                                v-else
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="m3 3 18 18" />
                                <path d="M10.58 10.58a2 2 0 0 0 2.83 2.83" />
                                <path d="M9.88 5.09A10.94 10.94 0 0 1 12 5c4.41 0 8.25 2.83 9.94 6.65a1 1 0 0 1 0 .7 10.98 10.98 0 0 1-4.06 4.76" />
                                <path d="M6.61 6.61A10.98 10.98 0 0 0 2.06 11.65a1 1 0 0 0 0 .7C3.75 16.17 7.59 19 12 19c1.43 0 2.8-.29 4.04-.82" />
                            </svg>
                        </button>
                    </div>

                    <InputError class="mt-1" :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between gap-4">
                    <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-sm text-slate-600">
                        <Checkbox
                            name="remember"
                            v-model:checked="form.remember"
                        />

                        <span>مرا به خاطر بسپار</span>
                    </label>

                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="rounded-lg px-2 py-1 text-sm font-medium text-slate-700 underline decoration-slate-300 underline-offset-4 transition hover:text-slate-950 hover:decoration-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2"
                    >
                        فراموشی رمز عبور
                    </Link>
                </div>

                <PrimaryButton
                    class="min-h-12 w-full justify-center rounded-xl bg-slate-900 px-5 text-sm font-semibold tracking-normal shadow-sm hover:bg-slate-800 focus:bg-slate-800 focus:ring-slate-300 active:bg-slate-950"
                    :class="{ 'opacity-60': form.processing }"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing">در حال ورود...</span>
                    <span v-else>ورود به سامانه</span>
                </PrimaryButton>
            </form>
        </div>
    </GuestLayout>
</template>
