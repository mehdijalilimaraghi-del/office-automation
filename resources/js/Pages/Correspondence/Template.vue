<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface ElementBox { id: string; kind: 'text'|'stamp'|'signature'|'letterhead'; label: string; content: string; x: number; y: number; width: number; height: number; font_size: number; bold: boolean; align: 'right'|'center'|'left'; visible: boolean; }
interface Token { key: string; label: string; }
const props = defineProps<{ elements: ElementBox[]; tokens: Token[] }>();
const form = useForm({ elements: props.elements.map(item => ({ ...item })) });
const selectedId = ref(form.elements[0]?.id ?? '');
const selected = computed(() => form.elements.find(item => item.id === selectedId.value) ?? null);
const page = ref<HTMLElement|null>(null);
const dragging = ref<{id:string; mode:'move'|'resize'; startX:number; startY:number; x:number; y:number; width:number; height:number}|null>(null);
function addBox(kind: ElementBox['kind'] = 'text'): void {
    const id = 'custom_' + Math.random().toString(36).slice(2, 9);
    form.elements.push({ id, kind, label: kind === 'stamp' ? 'مهر سازمان' : kind === 'signature' ? 'امضای مدیر واحد' : kind === 'letterhead' ? 'تصویر سربرگ' : 'کادر جدید', content: '', x: 10, y: 30, width: 40, height: 8, font_size: 12, bold: false, align: 'right', visible: true });
    selectedId.value = id;
}
function removeBox(): void {
    if (form.elements.length <= 1) return;
    form.elements = form.elements.filter(item => item.id !== selectedId.value);
    selectedId.value = form.elements[0]?.id ?? '';
}
function insertToken(token: string): void {
    if (selected.value) selected.value.content += token;
}
function pointerDown(event: PointerEvent, item: ElementBox, mode: 'move'|'resize'): void {
    if (!page.value) return;
    event.preventDefault();
    selectedId.value = item.id;
    dragging.value = { id: item.id, mode, startX: event.clientX, startY: event.clientY, x: item.x, y: item.y, width: item.width, height: item.height };
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
}
function pointerMove(event: PointerEvent): void {
    if (!dragging.value || !page.value) return;
    const rect = page.value.getBoundingClientRect();
    const dx = (event.clientX - dragging.value.startX) / rect.width * 100;
    const dy = (event.clientY - dragging.value.startY) / rect.height * 100;
    const item = form.elements.find(entry => entry.id === dragging.value?.id);
    if (!item) return;
    if (dragging.value.mode === 'move') {
        item.x = Math.max(0, Math.min(100 - item.width, dragging.value.x + dx));
        item.y = Math.max(0, Math.min(100 - item.height, dragging.value.y + dy));
    } else {
        item.width = Math.max(5, Math.min(100 - item.x, dragging.value.width + dx));
        item.height = Math.max(3, Math.min(100 - item.y, dragging.value.height + dy));
    }
}
function pointerUp(): void { dragging.value = null; }
</script>

<template>
    <Head title="طراح قالب نامه" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><p class="text-xs font-semibold text-teal-700">مکاتبات</p><h2 class="mt-1 text-2xl font-bold text-slate-900">طراح قالب نامه</h2><p class="mt-1 text-sm text-slate-500">کادرها را انتخاب، جابه‌جا و اندازه آن‌ها را تنظیم کنید؛ متغیرها هنگام چاپ با اطلاعات واقعی جایگزین می‌شوند.</p></div>
                <Link :href="route('settings.organization.edit')" class="text-sm font-semibold text-slate-600 hover:text-slate-900">بازگشت به تنظیمات سازمان</Link>
            </div>
        </template>
        <div class="bg-slate-50 py-6" dir="rtl">
            <div class="mx-auto grid max-w-[1500px] gap-5 px-4 xl:grid-cols-[minmax(0,1fr)_340px]">
                <section class="overflow-auto rounded-2xl border border-slate-200 bg-slate-200 p-4">
                    <div class="mx-auto mb-3 flex max-w-[210mm] items-center justify-between text-xs text-slate-600"><span>صفحه A4 · برای جابه‌جایی کادر آن را بکشید</span><div class="flex flex-wrap gap-2"><button type="button" class="rounded-lg bg-white px-3 py-2 font-semibold" @click="addBox('text')">کادر متنی +</button><button type="button" class="rounded-lg bg-white px-3 py-2 font-semibold" @click="addBox('stamp')">محل مهر +</button><button type="button" class="rounded-lg bg-white px-3 py-2 font-semibold" @click="addBox('signature')">محل امضا +</button><button type="button" class="rounded-lg bg-white px-3 py-2 font-semibold" @click="addBox('letterhead')">تصویر سربرگ +</button></div></div>
                    <div ref="page" class="relative mx-auto aspect-[210/297] w-full max-w-[210mm] overflow-hidden bg-white shadow-xl" @pointermove="pointerMove" @pointerup="pointerUp" @pointercancel="pointerUp">
                        <div v-for="item in form.elements" :key="item.id" class="absolute cursor-move whitespace-pre-wrap border border-dashed p-2 text-slate-900" :class="selectedId===item.id ? 'border-teal-600 bg-teal-50/40 ring-1 ring-teal-500' : 'border-slate-300'" :style="{ left:item.x+'%', top:item.y+'%', width:item.width+'%', height:item.height+'%', fontSize:item.font_size+'px', fontWeight:item.bold?'700':'400', textAlign:item.align, display:item.visible?'block':'none' }" @pointerdown="pointerDown($event,item,'move')">
                            <span class="pointer-events-none">{{ item.kind === 'text' ? (item.content || item.label) : (item.kind === 'stamp' ? 'مهر سازمان' : item.kind === 'signature' ? 'امضای مدیر واحد' : 'تصویر سربرگ') }}</span>
                            <button type="button" aria-label="تغییر اندازه کادر" class="absolute bottom-0 left-0 h-3 w-3 cursor-nwse-resize rounded-sm bg-teal-600" @pointerdown.stop="pointerDown($event,item,'resize')"></button>
                        </div>
                    </div>
                </section>
                <aside class="space-y-4">
                    <section class="rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="mb-3 flex items-center justify-between"><h3 class="font-bold">کادرها</h3><button type="button" class="text-xs text-rose-600" @click="removeBox">حذف کادر انتخابی</button></div>
                        <div class="space-y-1">
                            <button v-for="item in form.elements" :key="item.id" type="button" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm" :class="selectedId===item.id?'bg-teal-50 text-teal-800':'hover:bg-slate-50'" @click="selectedId=item.id">{{ item.label }}<span class="text-xs text-slate-400">{{ item.visible?'نمایش':'مخفی' }}</span></button>
                        </div>
                    </section>
                    <section v-if="selected" class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
                        <h3 class="font-bold">تنظیم کادر انتخابی</h3>
                        <label class="block text-xs font-semibold">عنوان کادر<input v-model="selected.label" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                        <label v-if="selected.kind === 'text'" class="block text-xs font-semibold">محتوا<textarea v-model="selected.content" rows="4" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label><p v-else class="rounded-lg bg-slate-50 p-2 text-xs leading-5 text-slate-600">تصویر این کادر از فایل فعال سربرگ، مهر سازمان یا امضای مدیر واحد در زمان چاپ دریافت می‌شود.</p>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="text-xs">اندازه قلم<input v-model.number="selected.font_size" type="number" min="7" max="48" class="mt-1 w-full rounded-lg border-slate-300" /></label>
                            <label class="text-xs">تراز<select v-model="selected.align" class="mt-1 w-full rounded-lg border-slate-300"><option value="right">راست</option><option value="center">وسط</option><option value="left">چپ</option></select></label>
                            <label class="text-xs">عرض (%)<input v-model.number="selected.width" type="number" min="5" max="100" class="mt-1 w-full rounded-lg border-slate-300" /></label>
                            <label class="text-xs">ارتفاع (%)<input v-model.number="selected.height" type="number" min="3" max="80" class="mt-1 w-full rounded-lg border-slate-300" /></label>
                        </div>
                        <label class="flex items-center gap-2 text-sm"><input v-model="selected.bold" type="checkbox" /> قلم ضخیم</label>
                        <label class="flex items-center gap-2 text-sm"><input v-model="selected.visible" type="checkbox" /> نمایش در خروجی</label>
                        <div v-if="selected.kind === 'text'" class="border-t pt-3"><p class="mb-2 text-xs font-bold">درج متغیر آماده</p><div class="flex flex-wrap gap-1.5"><button v-for="token in tokens" :key="token.key" type="button" class="rounded-md bg-slate-100 px-2 py-1 text-xs hover:bg-teal-50" :title="token.key" @click="insertToken(token.key)">{{ token.label }}</button></div></div>
                    </section>
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-6 text-amber-900">کادرهای متنی، مهر و امضا در صفحه اول قابل چیدمان هستند. بدنه نامه همچنان با جریان چندصفحه‌ای مستقل چاپ می‌شود.</div>
                    <PrimaryButton class="w-full justify-center" :disabled="form.processing" @click="form.put(route('correspondence.template.update'))">ذخیره قالب نامه</PrimaryButton>
                    <InputError :message="form.errors.elements" />
                </aside>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
