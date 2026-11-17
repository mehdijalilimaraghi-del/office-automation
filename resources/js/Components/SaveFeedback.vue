<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { CheckCircle2, X } from '@lucide/vue';

const page = usePage();
const message = ref('');
let timeout: number | undefined;

function dismiss() {
    window.clearTimeout(timeout);
    message.value = '';
}

function show(value: unknown) {
    if (typeof value !== 'string' || !value.trim()) return;
    dismiss();
    message.value = value;
    timeout = window.setTimeout(dismiss, 7000);
}

const removeListener = router.on('flash', event => show(event.detail.flash.saveSuccess));
onMounted(() => show(page.flash?.saveSuccess));
onUnmounted(() => {
    removeListener();
    dismiss();
});
</script>

<template>
    <div class="pointer-events-none fixed inset-x-4 bottom-4 z-[1100] flex justify-center sm:justify-end" dir="rtl">
        <p class="sr-only" role="status" aria-atomic="true">{{ message }}</p>
        <div v-if="message" class="pointer-events-auto flex w-full max-w-md items-center gap-3 rounded-xl border border-emerald-200 bg-white px-4 py-3 text-sm text-emerald-950 shadow-lg">
            <CheckCircle2 class="h-5 w-5 shrink-0 text-emerald-700" aria-hidden="true" />
            <span class="min-w-0 flex-1">{{ message }}</span>
            <button type="button" aria-label="بستن پیام" class="rounded-md p-1.5 text-slate-600 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700" @click="dismiss">
                <X class="h-4 w-4" aria-hidden="true" />
            </button>
        </div>
    </div>
</template>
