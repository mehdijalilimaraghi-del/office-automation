<script setup lang="ts">
import { ref } from 'vue';
import { Plus, MapPin } from '@lucide/vue';
import ArchiveLocationNode, { type LocationNode } from './ArchiveLocationNode.vue';
interface Location { building: string; room: string; shelf: string; folder?: string | null }
const props = defineProps<{ modelValue: Location[] }>();
const emit = defineEmits<{ 'update:modelValue': [value: Location[]] }>();
let nextId = 0;
const roots = ref<LocationNode[]>([]);
const buildingName = ref('');
const error = ref('');
for (const location of props.modelValue) {
    let children = roots.value;
    for (const value of [location.building, location.room, location.shelf, location.folder]) {
        if (!value) break;
        let node = children.find(item => item.name === value);
        if (!node) { node = { id: nextId++, name: value, children: [] }; children.push(node); }
        children = node.children;
    }
}
function publish(): void {
    const locations: Location[] = [];
    function walk(nodes: LocationNode[], path: string[]): void {
        for (const node of nodes) {
            const parts = [...path, node.name];
            if (node.children.length) walk(node.children, parts);
            if (parts.length === 3 || !node.children.length) locations.push({ building: parts[0] ?? '', room: parts[1] ?? '', shelf: parts[2] ?? '', folder: parts[3] ?? '' });
        }
    }
    walk(roots.value, []);
    emit('update:modelValue', locations);
}
function add(parent: LocationNode | null, value: string): void {
    const name = value.trim();
    const siblings = parent?.children ?? roots.value;
    if (!name) return;
    if (siblings.some(node => node.name === name)) { error.value = 'این نام در همین محل وجود دارد.'; return; }
    error.value = '';
    siblings.push({ id: nextId++, name, children: [] });
    if (!parent) buildingName.value = '';
    publish();
}
</script>
<template>
    <div class="space-y-4">
        <p class="text-sm leading-7 text-slate-600">ساختمان، اتاق و قفسه را یک بار تعریف کنید؛ سپس زونکن‌ها را زیر قفسه مربوط اضافه کنید. نام هر محل فقط در زیرمجموعه همان بخش باید یکتا باشد.</p>
        <div class="flex flex-wrap items-end gap-3 rounded-xl border border-indigo-100 bg-indigo-50/50 p-4">
            <label class="grid min-w-0 flex-1 gap-2 text-sm font-medium">نام ساختمان یا بایگانی<input v-model="buildingName" maxlength="60" placeholder="مثلاً ساختمان اداری" class="w-full rounded-lg border-slate-300" @keydown.enter.prevent="add(null, buildingName)" /></label>
            <button type="button" :disabled="!buildingName.trim()" class="inline-flex min-h-10 items-center gap-1 rounded-lg bg-indigo-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50" @click="add(null, buildingName)"><Plus class="size-4" />افزودن ساختمان</button>
        </div>
        <p v-if="error" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
        <ul class="space-y-3">
            <ArchiveLocationNode v-for="node in roots" :key="node.id" :node="node" :depth="0" :path="[]" @add="add" @change="publish"
                @remove="roots.splice(roots.indexOf(node), 1); publish()" />
        </ul>
        <div v-if="!roots.length" class="rounded-xl border border-dashed p-6 text-center text-sm text-slate-500"><MapPin class="mx-auto mb-2 size-7 text-slate-400" />هنوز محلی تعریف نشده است. با افزودن ساختمان یا بایگانی شروع کنید.</div>
        <p class="text-xs leading-6 text-slate-500">هر محل باید حداقل تا قفسه تکمیل شود. زونکن اختیاری است. حذف محل استفاده‌شده در اسناد پذیرفته نمی‌شود.</p>
    </div>
</template>
