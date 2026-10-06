<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import JalaliDatePicker from '@/Components/JalaliDatePicker.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import type { User } from '@/types';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();

type UserProfile = {
    first_name?: string | null;
    last_name?: string | null;
    employee_code?: string | null;
    national_id?: string | null;
    birth_date?: string | null;
    avatar_path?: string | null;
};

type ProfileUser = User & {
    profile?: UserProfile | null;
};

const page = usePage();
const user = page.props.auth.user as ProfileUser;

const form = useForm({
    username: user.username,
    first_name: user.profile?.first_name ?? '',
    last_name: user.profile?.last_name ?? '',
    employee_code: user.profile?.employee_code ?? '',
    national_id: user.profile?.national_id ?? '',
    birth_date: user.profile?.birth_date ?? '',
    name: user.name,
    email: user.email,
    avatar: null as File | null,
});

function submit(): void {
    form.transform((data) => ({ ...data, _method: 'patch' })).post(route('profile.update'), {
        preserveScroll: true,
        forceFormData: true,
    });
}
</script>

<template>
    <section dir="rtl">
        <header>
            <h2 class="text-lg font-bold text-slate-900">اطلاعات پروفایل</h2>
            <p class="mt-1 text-sm text-slate-500">
                مشخصات هویتی و اطلاعات سازمانی خود را تکمیل و به‌روزرسانی کنید.
            </p>
        </header>

        <form @submit.prevent="submit" class="mt-6 space-y-5">
            <div class="flex items-center gap-4 rounded-2xl bg-slate-50 p-4">
                <UserAvatar
                    :name="user.name"
                    :avatar-path="user.profile?.avatar_path"
                    size="xl"
                    alt="تصویر پروفایل"
                />
                <div class="min-w-0 flex-1">
                    <label class="mb-1.5 block text-sm font-semibold text-slate-700">تصویر پروفایل</label>
                    <input
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="block w-full text-sm text-slate-500 file:ml-3 file:rounded-lg file:border-0 file:bg-slate-900 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white"
                        @change="form.avatar = ($event.target as HTMLInputElement).files?.[0] ?? null"
                    />
                    <p class="mt-1 text-xs text-slate-400">حداکثر ۲ مگابایت</p>
                    <InputError class="mt-1" :message="form.errors.avatar" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <InputLabel for="username" value="نام کاربری" />
                    <TextInput id="username" v-model="form.username" dir="ltr" required class="mt-1 block w-full text-left" autocomplete="username" />
                    <InputError class="mt-1" :message="form.errors.username" />
                </div>
                <div>
                    <InputLabel for="first_name" value="نام" />
                    <TextInput id="first_name" v-model="form.first_name" class="mt-1 block w-full" autocomplete="given-name" />
                    <InputError class="mt-1" :message="form.errors.first_name" />
                </div>
                <div>
                    <InputLabel for="last_name" value="نام خانوادگی" />
                    <TextInput id="last_name" v-model="form.last_name" class="mt-1 block w-full" autocomplete="family-name" />
                    <InputError class="mt-1" :message="form.errors.last_name" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="employee_code" value="کد پرسنلی" />
                    <TextInput id="employee_code" v-model="form.employee_code" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.employee_code" />
                </div>
                <div>
                    <InputLabel for="national_id" value="کد ملی" />
                    <TextInput id="national_id" v-model="form.national_id" dir="ltr" class="mt-1 block w-full text-left" />
                    <InputError class="mt-1" :message="form.errors.national_id" />
                </div>
            </div>

            <div>
                <InputLabel for="birth_date" value="تاریخ تولد" />
                <JalaliDatePicker v-model="form.birth_date" placeholder="انتخاب تاریخ تولد" />
                <InputError class="mt-1" :message="form.errors.birth_date" />
            </div>

            <div>
                <InputLabel for="email" value="ایمیل" />
                <TextInput id="email" v-model="form.email" type="email" required dir="ltr" class="mt-1 block w-full text-left" autocomplete="email" />
                <InputError class="mt-1" :message="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-slate-700">
                    آدرس ایمیل شما هنوز تأیید نشده است.
                    <Link :href="route('verification.send')" method="post" as="button" class="text-sm text-slate-600 underline hover:text-slate-900">
                        ارسال مجدد ایمیل تأیید
                    </Link>
                </p>
                <div v-show="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-emerald-600">
                    لینک جدید تأیید ارسال شد.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">ذخیره</PrimaryButton>
                <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                    <p v-if="form.recentlySuccessful" class="text-sm text-slate-500">تغییرات با موفقیت ذخیره شد.</p>
                </Transition>
            </div>
        </form>
    </section>
</template>