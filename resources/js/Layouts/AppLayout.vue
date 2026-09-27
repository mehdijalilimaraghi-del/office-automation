<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
}>();
</script>

<template>
    <div dir="rtl" class="min-h-screen bg-background text-foreground">
        <header class="border-b bg-background">
            <div class="mx-auto flex h-16 items-center px-6">
                <Link href="/" class="text-lg font-semibold">
                    اتوماسیون اداری
                </Link>
            </div>
        </header>

        <main class="mx-auto w-full px-6 py-6">
            <slot />
        </main>
    </div>
</template>