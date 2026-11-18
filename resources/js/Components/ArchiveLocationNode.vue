<script setup lang="ts">
import { ref } from 'vue';
import { Plus, Trash2, ChevronLeft, ChevronDown, Folder } from '@lucide/vue';
export interface LocationNode { id: number; name: string; children: LocationNode[] }
const props = defineProps<{ node: LocationNode; depth: number; path: string[] }>();
const emit = defineEmits<{ remove: []; change: []; add: [parent: LocationNode, name: string] }>();
const expanded = ref(true);
const adding = ref(false);
const name = ref('');
const labels = ['ساختمان یا بایگانی', 'اتاق', 'قفسه یا کمد', 'زونکن یا پوشه'];
function add(): void {
    if (!name.value.trim()) return;
    emit('add', props.node, name.value.trim());
    name.value = '';
    expanded.value = true;
}
</script>
<template>
    <li class="min-w-0">
        <div class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-3">
            <button v-if="depth < 3" type="button" class="rounded-lg p-1.5 hover:bg-slate-100" :aria-expanded="expanded" :aria-label="'نمایش زیرمجموعه ' + node.name" @click="expanded = !expanded">
                <ChevronDown v-if="expanded" class="size-4" /><ChevronLeft v-else class="size-4" />
            </button>
            <Folder class="size-5 shrink-0 text-indigo-500" />
            <div class="min-w-[7rem] flex-1"><span class="block text-xs text-slate-500">{{ labels[depth] }}</span><span class="break-words text-sm font-semibold text-slate-800">{{ node.name }}</span></div>
            <button v-if="depth < 3" type="button" class="inline-flex items-center gap-1 rounded-lg px-2 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50" @click="adding = !adding; expanded = true"><Plus class="size-4" />افزودن {{ labels[depth + 1] }}</button>
            <button type="button" :aria-label="'حذف ' + [...path, node.name].join(' / ')" class="rounded-lg p-2 text-red-600 hover:bg-red-50" @click="emit('remove')"><Trash2 class="size-4" /></button>
        </div>
        <div v-if="expanded && depth < 3" class="ms-2 mt-2 border-s-2 border-indigo-100 ps-2 sm:ms-5 sm:ps-4">
            <div v-if="adding" class="mb-3 flex flex-wrap items-end gap-2 rounded-xl bg-indigo-50 p-3">
                <label class="grid min-w-0 flex-1 gap-1 text-sm">نام {{ labels[depth + 1] }}<input v-model="name" maxlength="60" class="w-full rounded-lg border-slate-300" @keydown.enter.prevent="add" /></label>
                <button type="button" :disabled="!name.trim()" class="rounded-lg bg-indigo-700 px-3 py-2 text-sm text-white disabled:opacity-50" @click="add">افزودن</button>
            </div>
            <ul class="space-y-2">
                <ArchiveLocationNode v-for="child in node.children" :key="child.id" :node="child" :depth="depth + 1" :path="[...path, node.name]"
                    @remove="node.children.splice(node.children.indexOf(child), 1); emit('change')" @change="emit('change')" @add="(parent, value) => emit('add', parent, value)" />
            </ul>
            <p v-if="!node.children.length" class="py-2 text-xs leading-6 text-slate-500">{{ depth < 2 ? 'برای تکمیل محل، ' + labels[depth + 1] + ' اضافه کنید.' : 'می‌توانید سند را در همین قفسه نگهداری کنید یا زونکن‌های آن را تعریف کنید.' }}</p>
        </div>
    </li>
</template>
