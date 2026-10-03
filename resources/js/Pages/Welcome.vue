<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
}>();
</script>

<template>
    <Head title="اتوماسیون اداری" />

    <div dir="rtl" class="min-h-screen overflow-hidden bg-slate-950 text-white">
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute -right-32 -top-32 h-96 w-96 rounded-full bg-teal-500/20 blur-3xl"></div>
            <div class="absolute -bottom-40 -left-20 h-[28rem] w-[28rem] rounded-full bg-blue-500/15 blur-3xl"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(255,255,255,.04)_1px,transparent_1px)] [background-size:28px_28px]"></div>
        </div>

        <div class="relative mx-auto flex min-h-screen max-w-7xl flex-col px-5 sm:px-8">
            <header class="flex items-center justify-between py-6">
                <div class="flex items-center gap-3">
                    <ApplicationLogo class="h-11 w-11" />
                    <div>
                        <div class="text-sm font-bold text-white">اتوماسیون اداری</div>
                        <div class="text-[11px] text-slate-400">Office Automation</div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Link v-if="canLogin" :href="route('login')" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/10 hover:text-white">
                        ورود
                    </Link>
                    <Link v-if="canRegister" :href="route('register')" class="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-900 shadow-lg shadow-black/10 transition hover:bg-slate-100">
                        ایجاد حساب
                    </Link>
                </div>
            </header>

            <main class="flex flex-1 items-center py-10 lg:py-16">
                <div class="grid w-full items-center gap-14 lg:grid-cols-[1.05fr_.95fr]">
                    <section class="max-w-2xl">
                        <span class="inline-flex items-center gap-2 rounded-full border border-teal-400/20 bg-teal-400/10 px-3.5 py-1.5 text-xs font-semibold text-teal-200">
                            <span class="h-1.5 w-1.5 rounded-full bg-teal-400"></span>
                            محیط یکپارچه مدیریت عملیات
                        </span>

                        <h1 class="mt-6 text-4xl font-black leading-[1.2] tracking-tight sm:text-5xl lg:text-6xl">
                            کارها را منظم،
                            <span class="text-teal-300">تیم را هماهنگ</span>
                            نگه دارید.
                        </h1>

                        <p class="mt-6 max-w-xl text-base leading-8 text-slate-300 sm:text-lg">
                            یک فضای متمرکز برای مدیریت تسک‌ها، پروژه‌ها، مسئولیت‌ها و پیگیری ارتباطات سازمانی؛ با دسترسی کنترل‌شده و سابقه فعالیت قابل پیگیری.
                        </p>

                        <div class="mt-8 flex flex-wrap gap-3">
                            <Link v-if="canLogin" :href="route('login')" class="inline-flex items-center gap-2 rounded-xl bg-teal-500 px-5 py-3 text-sm font-bold text-white shadow-xl shadow-teal-950/30 transition hover:bg-teal-400">
                                ورود به سامانه
                                <span>←</span>
                            </Link>
                            <a href="#features" class="inline-flex items-center rounded-xl border border-white/10 bg-white/5 px-5 py-3 text-sm font-semibold text-slate-200 transition hover:bg-white/10">
                                آشنایی با امکانات
                            </a>
                        </div>

                        <div class="mt-10 grid max-w-xl grid-cols-3 gap-5 border-t border-white/10 pt-7">
                            <div>
                                <div class="text-sm font-bold text-white">تسک‌ها</div>
                                <div class="mt-1 text-xs text-slate-400">پیگیری و ارجاع</div>
                            </div>
                            <div>
                                <div class="text-sm font-bold text-white">پروژه‌ها</div>
                                <div class="mt-1 text-xs text-slate-400">همکاری تیمی</div>
                            </div>
                            <div>
                                <div class="text-sm font-bold text-white">گزارش فعالیت</div>
                                <div class="mt-1 text-xs text-slate-400">سابقه و شفافیت</div>
                            </div>
                        </div>
                    </section>

                    <section id="features" class="relative">
                        <div class="rounded-3xl border border-white/10 bg-white/[0.06] p-4 shadow-2xl backdrop-blur-xl sm:p-5">
                            <div class="rounded-2xl border border-white/10 bg-slate-900/80 p-5 sm:p-6">
                                <div class="flex items-center justify-between border-b border-white/10 pb-5">
                                    <div>
                                        <div class="text-xs font-medium text-teal-300">نمای کلی سیستم</div>
                                        <div class="mt-1 text-lg font-bold text-white">مرکز عملیات</div>
                                    </div>
                                    <div class="rounded-xl bg-teal-400/10 px-3 py-2 text-xs font-semibold text-teal-300">زنده</div>
                                </div>

                                <div class="mt-5 grid grid-cols-2 gap-3">
                                    <div class="rounded-2xl border border-white/10 bg-white/[0.04] p-4">
                                        <div class="text-xs text-slate-400">تسک‌های جاری</div>
                                        <div class="mt-2 text-2xl font-black">24</div>
                                        <div class="mt-1 text-[11px] text-teal-300">در حال پیگیری</div>
                                    </div>
                                    <div class="rounded-2xl border border-white/10 bg-white/[0.04] p-4">
                                        <div class="text-xs text-slate-400">پروژه‌های فعال</div>
                                        <div class="mt-2 text-2xl font-black">08</div>
                                        <div class="mt-1 text-[11px] text-blue-300">در حال اجرا</div>
                                    </div>
                                </div>

                                <div class="mt-3 rounded-2xl border border-white/10 bg-white/[0.04] p-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-semibold text-slate-300">فعالیت‌های اخیر</span>
                                        <span class="text-[11px] text-slate-500">امروز</span>
                                    </div>
                                    <div class="mt-4 space-y-3">
                                        <div v-for="item in [
                                            { title: 'ارجاع تسک به تیم فنی', meta: 'تخصیص مسئول', dot: 'bg-teal-400' },
                                            { title: 'به‌روزرسانی وضعیت پروژه', meta: 'در حال انجام', dot: 'bg-blue-400' },
                                            { title: 'ثبت گزارش فعالیت', meta: 'گزارش کار', dot: 'bg-violet-400' },
                                        ]" :key="item.title" class="flex items-center gap-3">
                                            <span class="h-2 w-2 shrink-0 rounded-full" :class="item.dot"></span>
                                            <div class="min-w-0">
                                                <div class="truncate text-xs font-medium text-slate-200">{{ item.title }}</div>
                                                <div class="mt-0.5 text-[10px] text-slate-500">{{ item.meta }}</div>
                                            </div>
                                            <span class="mr-auto text-[10px] text-slate-600">اکنون</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </main>

            <footer class="border-t border-white/10 py-5 text-center text-xs text-slate-500">
                اتوماسیون اداری · مدیریت یکپارچه کار و همکاری سازمانی
            </footer>
        </div>
    </div>
</template>
