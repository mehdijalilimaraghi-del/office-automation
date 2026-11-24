<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
defineProps<{ active: 'modems' | 'extensions' | 'ivr' | 'devices' }>();
const items = [
    { key: 'modems', title: 'خط و مودم', route: 'telephony.index' },
    { key: 'devices', title: 'اتصال کامپیوتر', route: 'telephony.devices.index' },
    { key: 'extensions', title: 'داخلی‌ها', route: 'telephony.extensions.index' },
    { key: 'ivr', title: 'راهنمای صوتی', route: 'telephony.ivr' },
];
</script>
<template>
<nav aria-label="بخش‌های تلفن سازمانی" class="flex flex-wrap gap-2 rounded-2xl border bg-white p-2">
    <Link v-for="item in items" :key="item.key" :href="route(item.route)" :aria-current="active === item.key ? 'page' : undefined" class="rounded-xl px-5 py-3 text-sm font-semibold" :class="active === item.key ? 'bg-indigo-700 text-white' : 'text-slate-600 hover:bg-slate-100'">{{ item.title }}</Link>
</nav>
</template>
