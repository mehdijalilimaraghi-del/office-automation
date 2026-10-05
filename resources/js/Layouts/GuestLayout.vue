<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        dir="rtl"
        class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-8 sm:px-6"
    >
        <div class="w-full max-w-[440px]">
            <div class="mb-4 flex justify-center">
                <Link
                    href="/"
                    class="inline-flex rounded-2xl focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-4"
                    aria-label="صفحه اصلی"
                >
                    <ApplicationLogo class="h-10 w-10 fill-current text-slate-700" />
                </Link>
            </div>

            <div
                class="overflow-hidden rounded-3xl border border-slate-200 bg-white px-5 py-6 shadow-[0_10px_40px_rgba(15,23,42,0.08)] sm:px-8 sm:py-8"
            >
                <slot />
            </div>
        </div>
    </div>
</template>
