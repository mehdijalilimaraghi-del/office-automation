<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

type Tab = 'home' | 'insert' | 'table' | 'picture' | 'shape';
const props = withDefaults(defineProps<{ modelValue: string; label: string; fontFamily?: string; disabled?: boolean }>(), {
    fontFamily: 'Vazirmatn',
    disabled: false,
});
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const editor = ref<HTMLDivElement | null>(null);
const imageInput = ref<HTMLInputElement | null>(null);
const activeTab = ref<Tab>('home');
const context = ref<{ table: boolean; picture: boolean; shape: boolean }>({ table: false, picture: false, shape: false });
const history = ref<string[]>(['']);
const historyIndex = ref(0);
const currentImage = ref<HTMLImageElement | null>(null);
const currentShape = ref<HTMLElement | null>(null);
const currentCell = ref<HTMLTableCellElement | null>(null);
let syncing = false;
let savedRange: Range | null = null;

const tabs = computed(() => [
    { id: 'home' as const, label: 'خانه' },
    { id: 'insert' as const, label: 'درج' },
    ...(context.value.table ? [{ id: 'table' as const, label: 'جدول' }] : []),
    ...(context.value.picture ? [{ id: 'picture' as const, label: 'تصویر' }] : []),
    ...(context.value.shape ? [{ id: 'shape' as const, label: 'شکل' }] : []),
]);

function emitContent(): void {
    if (!editor.value) return;
    const html = editor.value.innerHTML;
    emit('update:modelValue', html === '<br>' ? '' : html);
    if (history.value[historyIndex.value] !== html) {
        history.value = history.value.slice(0, historyIndex.value + 1);
        history.value.push(html);
        if (history.value.length > 80) history.value.shift();
        historyIndex.value = history.value.length - 1;
    }
}
function insideEditor(node: Node | null): boolean {
    return !!node && !!editor.value && (node === editor.value || editor.value.contains(node));
}
function selectedElement(): Element | null {
    const selection = window.getSelection();
    const range = selection?.rangeCount && insideEditor(selection.getRangeAt(0).commonAncestorContainer)
        ? selection.getRangeAt(0)
        : savedRange;
    if (range?.collapsed && range.startContainer instanceof Element) {
        const previous = range.startContainer.childNodes[range.startOffset - 1];
        if (previous instanceof HTMLImageElement) return previous;
    }
    const node = insideEditor(selection?.anchorNode ?? null) ? selection?.anchorNode : savedRange?.startContainer;
    return node instanceof Element ? node : node?.parentElement ?? null;
}
function findBlock(element: Element | null): HTMLElement | null {
    const block = element?.closest('p,div,li,h2,h3,blockquote,td,th');
    return block instanceof HTMLElement && editor.value?.contains(block) ? block : null;
}
function refreshContext(): void {
    if (document.activeElement?.closest('.rte-toolbar')) {
        if (currentImage.value?.isConnected) { context.value = { table: false, picture: true, shape: false }; return; }
        if (currentShape.value?.isConnected) { context.value = { table: false, picture: false, shape: true }; return; }
        if (currentCell.value?.isConnected) { context.value = { table: true, picture: false, shape: false }; return; }
    }
    const element = selectedElement();
    if (!insideEditor(window.getSelection()?.anchorNode ?? null)) {
        if (document.activeElement?.closest('.rte-toolbar')) return;
        context.value = { table: false, picture: false, shape: false };
        if (['table', 'picture', 'shape'].includes(activeTab.value)) activeTab.value = 'home';
        return;
    }
    const selection = window.getSelection();
    if (selection?.rangeCount) savedRange = selection.getRangeAt(0).cloneRange();
    const table = element?.closest('table') ?? null;
    const image = element?.closest('img') ?? null;
    const shape = element?.closest('[data-editor-shape]') ?? null;
    currentCell.value = element?.closest('td,th') as HTMLTableCellElement | null;
    currentImage.value = image as HTMLImageElement | null;
    currentShape.value = shape as HTMLElement | null;
    context.value = { table: !!table, picture: !!image, shape: !!shape };
    if (image) activeTab.value = 'picture';
    else if (shape) activeTab.value = 'shape';
    else if (table) activeTab.value = 'table';
    else if (['table', 'picture', 'shape'].includes(activeTab.value)) activeTab.value = 'home';
}
function onSelectionChange(): void {
    refreshContext();
}
function commit(): void {
    emitContent();
    refreshContext();
}
function restoreSelection(range: Range): void {
    const selection = window.getSelection();
    selection?.removeAllRanges();
    selection?.addRange(range);
}
function wrapSelection(tag: string, style?: Record<string, string>): void {
    const target = editor.value;
    const selection = window.getSelection();
    if (!target) return;
    target.focus();
    const activeRange = selection && selection.rangeCount && insideEditor(selection.getRangeAt(0).commonAncestorContainer) ? selection.getRangeAt(0) : savedRange;
    if (!activeRange || !insideEditor(activeRange.commonAncestorContainer)) return;
    const range = activeRange.cloneRange();
    const wrapper = document.createElement(tag);
    if (style) Object.entries(style).forEach(([key, value]) => wrapper.style.setProperty(key, value));
    if (range.collapsed) {
        wrapper.textContent = '\u200b';
        range.insertNode(wrapper);
        const next = document.createRange();
        next.setStart(wrapper.firstChild ?? wrapper, 1);
        next.collapse(true);
        restoreSelection(next);
        savedRange = next.cloneRange();
    } else {
        wrapper.append(range.extractContents());
        range.insertNode(wrapper);
        const next = document.createRange();
        next.selectNodeContents(wrapper);
        restoreSelection(next);
        savedRange = next.cloneRange();
    }
    commit();
}
function setInlineStyle(property: string, value: string): void {
    wrapSelection('span', { [property]: value });
}
function setBlockStyle(property: string, value: string): void {
    const block = findBlock(selectedElement());
    if (!block) return;
    block.style.setProperty(property, value);
    commit();
}
function applyFormat(tag: 'strong' | 'em' | 'u' | 's' | 'sup' | 'sub'): void {
    const existing = selectedElement()?.closest(tag);
    if (existing && editor.value?.contains(existing)) {
        const parent = existing.parentNode;
        if (!parent) return;
        while (existing.firstChild) parent.insertBefore(existing.firstChild, existing);
        existing.remove();
        commit();
        return;
    }
    wrapSelection(tag);
}
function applyFontFamily(value: string): void { setInlineStyle('font-family', value); }
function applyFontSize(value: string): void { setInlineStyle('font-size', value); }
function applyTextColor(value: string): void { setInlineStyle('color', value); }
function applyHighlight(value: string): void { setInlineStyle('background-color', value); }
function align(value: string): void { setBlockStyle('text-align', value); }
function setDirection(value: 'rtl' | 'ltr'): void {
    const block = findBlock(selectedElement()) ?? editor.value;
    if (block) block.setAttribute('dir', value);
    commit();
}
function setLineHeight(value: string): void { setBlockStyle('line-height', value); }
function setParagraphSpacing(value: string): void { setBlockStyle('margin-bottom', value); }
function indent(increase: boolean): void {
    const block = findBlock(selectedElement());
    if (!block) return;
    const current = Number.parseFloat(block.style.marginRight || '0') || 0;
    block.style.marginRight = Math.max(0, current + (increase ? 18 : -18)) + 'px';
    commit();
}
function toggleList(ordered: boolean): void {
    const block = findBlock(selectedElement());
    if (!block || !editor.value) return;
    const existingList = block.closest('ul,ol');
    if (existingList && editor.value.contains(existingList)) {
        const item = block.closest('li');
        if (!item) return;
        const replacement = document.createElement('p');
        replacement.innerHTML = item.innerHTML;
        item.replaceWith(replacement);
        if (!existingList.querySelector('li')) existingList.remove();
    } else {
        const list = document.createElement(ordered ? 'ol' : 'ul');
        const item = document.createElement('li');
        item.innerHTML = block.innerHTML || '<br>';
        list.append(item);
        block.replaceWith(list);
    }
    commit();
}
function clearFormatting(): void {
    const selection = window.getSelection();
    const range = selection?.rangeCount && insideEditor(selection.getRangeAt(0).commonAncestorContainer) ? selection.getRangeAt(0).cloneRange() : savedRange?.cloneRange();
    if (!range || !insideEditor(range.commonAncestorContainer) || range.collapsed) return;
    const text = range.toString();
    range.deleteContents();
    range.insertNode(document.createTextNode(text));
    commit();
}
function insertNode(node: Node): void {
    const target = editor.value;
    if (!target) return;
    target.focus();
    const selection = window.getSelection();
    const activeRange = selection && selection.rangeCount > 0 && insideEditor(selection.getRangeAt(0).commonAncestorContainer)
        ? selection.getRangeAt(0)
        : savedRange;
    if (activeRange && insideEditor(activeRange.commonAncestorContainer)) {
        const range = activeRange.cloneRange();
        range.deleteContents();
        range.insertNode(node);
        range.setStartAfter(node);
        range.collapse(true);
        restoreSelection(range);
        savedRange = range.cloneRange();
    } else {
        target.append(node);
    }
    commit();
}
function insertTable(): void {
    const table = document.createElement('table');
    table.style.borderCollapse = 'collapse';
    table.style.width = '100%';
    table.style.margin = '8px 0';
    const tbody = document.createElement('tbody');
    for (let row = 0; row < 3; row += 1) {
        const tr = document.createElement('tr');
        for (let col = 0; col < 3; col += 1) {
            const cell = document.createElement('td');
            cell.style.border = '1px solid #94a3b8';
            cell.style.padding = '6px';
            cell.innerHTML = '<br>';
            tr.append(cell);
        }
        tbody.append(tr);
    }
    table.append(tbody);
    insertNode(table);
    const first = table.querySelector('td');
    if (first) {
        const range = document.createRange();
        range.selectNodeContents(first);
        restoreSelection(range);
    }
    refreshContext();
}
function addTableRow(): void {
    const cell = currentCell.value;
    const row = cell?.parentElement as HTMLTableRowElement | null;
    const table = cell?.closest('table');
    if (!cell || !row || !table) return;
    const next = document.createElement('tr');
    Array.from(row.cells).forEach(() => {
        const td = document.createElement('td');
        td.style.border = '1px solid #94a3b8';
        td.style.padding = '6px';
        td.innerHTML = '<br>';
        next.append(td);
    });
    row.after(next);
    commit();
}
function addTableColumn(): void {
    const cell = currentCell.value;
    const table = cell?.closest('table');
    if (!cell || !table) return;
    const index = cell.cellIndex;
    table.querySelectorAll('tr').forEach((row) => {
        const td = document.createElement('td');
        td.style.border = '1px solid #94a3b8';
        td.style.padding = '6px';
        td.innerHTML = '<br>';
        row.insertBefore(td, row.children[index + 1] ?? null);
    });
    commit();
}
function deleteTableRow(): void {
    const row = currentCell.value?.parentElement;
    const table = currentCell.value?.closest('table');
    if (!row || !table) return;
    row.remove();
    if (!table.querySelector('tr')) table.remove();
    commit();
}
function deleteTableColumn(): void {
    const cell = currentCell.value;
    const table = cell?.closest('table');
    if (!cell || !table) return;
    const index = cell.cellIndex;
    table.querySelectorAll('tr').forEach((row) => row.children[index]?.remove());
    if (!table.querySelector('td,th')) table.remove();
    commit();
}
function deleteTable(): void {
    const table = currentCell.value?.closest('table');
    table?.remove();
    currentCell.value = null;
    commit();
}
function setCellBackground(value: string): void {
    if (currentCell.value) currentCell.value.style.backgroundColor = value;
    commit();
}
function setCellAlignment(value: string): void {
    if (currentCell.value) currentCell.value.style.textAlign = value;
    commit();
}
function setImageWidth(value: string): void {
    if (currentImage.value) currentImage.value.style.width = value;
    commit();
}
function setImageAlignment(value: string): void {
    if (!currentImage.value) return;
    currentImage.value.style.display = 'block';
    currentImage.value.style.marginLeft = value === 'right' ? 'auto' : value === 'left' ? '0' : 'auto';
    currentImage.value.style.marginRight = value === 'left' ? 'auto' : value === 'right' ? '0' : 'auto';
    commit();
}
function chooseImage(): void { imageInput.value?.click(); }
function insertImageFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file || !['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) return;
    if (file.size > 180 * 1024) {
        window.alert('حجم تصویر برای درج مستقیم در متن نباید بیشتر از ۱۸۰ کیلوبایت باشد.');
        return;
    }
    const reader = new FileReader();
    reader.onload = () => {
        if (typeof reader.result !== 'string') return;
        const image = document.createElement('img');
        image.src = reader.result;
        image.alt = file.name;
        image.style.width = '50%';
        image.style.height = 'auto';
        image.style.display = 'block';
        if (currentImage.value?.isConnected) {
            currentImage.value.replaceWith(image);
            emitContent();
        } else {
            insertNode(image);
        }
        currentImage.value = image;
        context.value = { table: false, picture: true, shape: false };
        activeTab.value = 'picture';
    };
    reader.readAsDataURL(file);
}
function insertShape(shape: 'rectangle' | 'ellipse' | 'line' | 'arrow'): void {
    const element = document.createElement('div');
    element.dataset.editorShape = shape;
    element.contentEditable = 'true';
    element.style.display = 'inline-block';
    element.style.verticalAlign = 'middle';
    if (shape === 'line') {
        element.style.width = '140px';
        element.style.height = '2px';
        element.style.backgroundColor = '#334155';
    } else {
        element.style.width = '100px';
        element.style.height = '54px';
        element.style.border = '1px solid #334155';
        element.style.backgroundColor = '#f8fafc';
        if (shape === 'ellipse') element.style.borderRadius = '50%';
        if (shape === 'arrow') element.textContent = '➜';
        else element.textContent = '\u00a0';
    }
    insertNode(element);
    const range = document.createRange();
    range.selectNodeContents(element);
    restoreSelection(range);
    savedRange = range.cloneRange();
    currentShape.value = element;
    context.value = { table: false, picture: false, shape: true };
    activeTab.value = 'shape';
}
function setShapeColor(value: string): void {
    if (!currentShape.value) return;
    currentShape.value.style.backgroundColor = value;
    commit();
}
function setShapeBorderColor(value: string): void {
    if (!currentShape.value) return;
    currentShape.value.style.borderColor = value;
    commit();
}
function setShapeWidth(value: string): void {
    if (!currentShape.value) return;
    currentShape.value.style.width = value;
    commit();
}
function insertLink(): void {
    const url = window.prompt('نشانی پیوند را وارد کنید:');
    if (!url || !/^https?:\/\/|^mailto:/i.test(url)) return;
    const text = savedRange?.toString() || window.getSelection()?.toString() || url;
    const link = document.createElement('a');
    link.href = url;
    link.textContent = text;
    insertNode(link);
}
function removeImage(): void { currentImage.value?.remove(); currentImage.value = null; commit(); }
function removeShape(): void { currentShape.value?.remove(); currentShape.value = null; commit(); }
function undo(): void {
    if (historyIndex.value <= 0 || !editor.value) return;
    historyIndex.value -= 1;
    syncing = true;
    editor.value.innerHTML = history.value[historyIndex.value];
    emit('update:modelValue', editor.value.innerHTML);
    syncing = false;
    refreshContext();
}
function redo(): void {
    if (historyIndex.value >= history.value.length - 1 || !editor.value) return;
    historyIndex.value += 1;
    syncing = true;
    editor.value.innerHTML = history.value[historyIndex.value];
    emit('update:modelValue', editor.value.innerHTML);
    syncing = false;
    refreshContext();
}
function onEditorClick(event: MouseEvent): void {
    const target = event.target;
    if (!(target instanceof Element) || !editor.value?.contains(target)) return;
    currentImage.value = target.closest('img') as HTMLImageElement | null;
    currentShape.value = target.closest('[data-editor-shape]') as HTMLElement | null;
    currentCell.value = target.closest('td,th') as HTMLTableCellElement | null;
    context.value = { table: !!target.closest('table'), picture: !!currentImage.value, shape: !!currentShape.value };
    if (currentImage.value) activeTab.value = 'picture';
    else if (currentShape.value) activeTab.value = 'shape';
    else if (target.closest('table')) activeTab.value = 'table';
    else if (['table', 'picture', 'shape'].includes(activeTab.value)) activeTab.value = 'home';
}
function onInput(): void {
    if (!syncing) commit();
}
watch(() => props.modelValue, async (value) => {
    if (!editor.value || editor.value.innerHTML === value) return;
    const focused = editor.value.contains(document.activeElement);
    if (focused) return;
    editor.value.innerHTML = value || '';
    history.value = [editor.value.innerHTML];
    historyIndex.value = 0;
    await nextTick();
    refreshContext();
});
onMounted(() => {
    if (editor.value) editor.value.innerHTML = props.modelValue || '';
    history.value = [editor.value?.innerHTML ?? ''];
    window.addEventListener('selectionchange', onSelectionChange);
});
onBeforeUnmount(() => window.removeEventListener('selectionchange', onSelectionChange));
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-slate-300 bg-white focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
        <div class="flex flex-wrap items-center gap-1 border-b border-slate-200 bg-slate-50 px-2 py-2" role="tablist" aria-label="ابزارهای ویرایش متن">
            <button v-for="tab in tabs" :key="tab.id" type="button" role="tab" :aria-selected="activeTab === tab.id" class="rounded-lg px-3 py-2 text-xs font-semibold" :class="activeTab === tab.id ? 'bg-indigo-600 text-white' : 'text-slate-700 hover:bg-slate-200'" @click="activeTab = tab.id">{{ tab.label }}</button>
        </div>

        <div v-if="activeTab === 'home'" class="rte-toolbar flex flex-wrap items-center gap-1.5 border-b border-slate-200 bg-white p-2.5">
            <select class="h-9 max-w-36 rounded-lg border-slate-300 text-xs" :value="fontFamily" aria-label="نوع قلم" @change="applyFontFamily(($event.target as HTMLSelectElement).value)">
                <option value="Vazirmatn">وزیرمتن</option><option value="Arial">Arial</option><option value="Tahoma">Tahoma</option><option value="B Nazanin">B Nazanin</option><option value="B Mitra">B Mitra</option><option value="IRANSans">IRANSans</option><option value="Times New Roman">Times New Roman</option>
            </select>
            <select class="h-9 w-20 rounded-lg border-slate-300 text-xs" aria-label="اندازه قلم" @change="applyFontSize(($event.target as HTMLSelectElement).value)">
                <option value="10pt">۱۰</option><option value="11pt">۱۱</option><option value="12pt" selected>۱۲</option><option value="14pt">۱۴</option><option value="16pt">۱۶</option><option value="18pt">۱۸</option><option value="24pt">۲۴</option><option value="32pt">۳۲</option>
            </select>
            <button type="button" class="rte-tool font-bold" title="پررنگ" @click="applyFormat('strong')">B</button>
            <button type="button" class="rte-tool italic" title="مورب" @click="applyFormat('em')">I</button>
            <button type="button" class="rte-tool underline" title="زیرخط" @click="applyFormat('u')">U</button>
            <button type="button" class="rte-tool line-through" title="خط‌خورده" @click="applyFormat('s')">S</button>
            <label class="rte-tool" title="رنگ متن">A<input type="color" class="absolute h-px w-px opacity-0" @input="applyTextColor(($event.target as HTMLInputElement).value)" /></label>
            <label class="rte-tool" title="رنگ زمینه متن">▰<input type="color" value="#fff59d" class="absolute h-px w-px opacity-0" @input="applyHighlight(($event.target as HTMLInputElement).value)" /></label>
            <span class="mx-1 h-6 w-px bg-slate-200"></span>
            <button type="button" class="rte-tool" title="راست‌چین" @click="align('right')">≡ راست</button>
            <button type="button" class="rte-tool" title="وسط‌چین" @click="align('center')">≡ وسط</button>
            <button type="button" class="rte-tool" title="چپ‌چین" @click="align('left')">≡ چپ</button>
            <button type="button" class="rte-tool" title="تراز دوطرفه" @click="align('justify')">≋</button>
            <select class="h-9 w-24 rounded-lg border-slate-300 text-xs" aria-label="فاصله خطوط" @change="setLineHeight(($event.target as HTMLSelectElement).value)"><option value="1.5">فاصله ۱٫۵</option><option value="1.8" selected>فاصله ۱٫۸</option><option value="2">فاصله ۲</option></select>
            <select class="h-9 w-24 rounded-lg border-slate-300 text-xs" aria-label="فاصله پاراگراف" @change="setParagraphSpacing(($event.target as HTMLSelectElement).value)"><option value="4px">کم</option><option value="12px" selected>معمولی</option><option value="20px">زیاد</option></select>
            <button type="button" class="rte-tool" title="فهرست نشانه‌دار" @click="toggleList(false)">• فهرست</button>
            <button type="button" class="rte-tool" title="فهرست شماره‌دار" @click="toggleList(true)">۱. فهرست</button>
            <button type="button" class="rte-tool" title="افزایش تورفتگی" @click="indent(true)">⇥</button>
            <button type="button" class="rte-tool" title="کاهش تورفتگی" @click="indent(false)">⇤</button>
            <button type="button" class="rte-tool" title="راست به چپ" @click="setDirection('rtl')">RTL</button>
            <button type="button" class="rte-tool" title="چپ به راست" @click="setDirection('ltr')">LTR</button>
            <button type="button" class="rte-tool" title="واگرد" :disabled="historyIndex === 0" @click="undo">↶</button>
            <button type="button" class="rte-tool" title="ازنو" :disabled="historyIndex >= history.length - 1" @click="redo">↷</button>
            <button type="button" class="rte-tool text-rose-700" title="حذف قالب‌بندی" @click="clearFormatting">پاک‌سازی قالب</button>
        </div>

        <div v-else-if="activeTab === 'insert'" class="rte-toolbar flex flex-wrap items-center gap-2 border-b border-slate-200 bg-white p-3">
            <button type="button" class="rte-tool" @click="insertTable">درج جدول ۳×۳</button>
            <button type="button" class="rte-tool" @click="chooseImage">درج تصویر</button>
            <button type="button" class="rte-tool" @click="insertShape('rectangle')">مستطیل</button>
            <button type="button" class="rte-tool" @click="insertShape('ellipse')">بیضی</button>
            <button type="button" class="rte-tool" @click="insertShape('line')">خط</button>
            <button type="button" class="rte-tool" @click="insertShape('arrow')">پیکان</button>
            <button type="button" class="rte-tool" @click="insertLink">پیوند</button>
            <input ref="imageInput" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="insertImageFile" />
            <p class="w-full text-xs text-slate-500">تصویر به‌صورت امن داخل محتوای نامه ذخیره می‌شود؛ فرمت PNG، JPG یا WEBP و حداکثر ۱۸۰ کیلوبایت.</p>
        </div>

        <div v-else-if="activeTab === 'table'" class="rte-toolbar flex flex-wrap items-center gap-2 border-b border-slate-200 bg-white p-3">
            <button type="button" class="rte-tool" @click="addTableRow">افزودن ردیف</button>
            <button type="button" class="rte-tool" @click="addTableColumn">افزودن ستون</button>
            <button type="button" class="rte-tool" @click="deleteTableRow">حذف ردیف</button>
            <button type="button" class="rte-tool" @click="deleteTableColumn">حذف ستون</button>
            <button type="button" class="rte-tool text-rose-700" @click="deleteTable">حذف جدول</button>
            <label class="rte-tool">زمینه سلول<input type="color" class="h-5 w-5" @input="setCellBackground(($event.target as HTMLInputElement).value)" /></label>
            <select class="h-9 rounded-lg border-slate-300 text-xs" @change="setCellAlignment(($event.target as HTMLSelectElement).value)"><option value="right">راست</option><option value="center">وسط</option><option value="left">چپ</option></select>
        </div>

        <div v-else-if="activeTab === 'picture'" class="rte-toolbar flex flex-wrap items-center gap-2 border-b border-slate-200 bg-white p-3">
            <label class="text-xs">اندازه تصویر<select class="mr-2 h-9 rounded-lg border-slate-300 text-xs" @change="setImageWidth(($event.target as HTMLSelectElement).value)"><option value="25%">۲۵٪</option><option value="50%">۵۰٪</option><option value="75%">۷۵٪</option><option value="100%">۱۰۰٪</option></select></label>
            <label class="text-xs">جایگاه<select class="mr-2 h-9 rounded-lg border-slate-300 text-xs" @change="setImageAlignment(($event.target as HTMLSelectElement).value)"><option value="right">راست</option><option value="center">وسط</option><option value="left">چپ</option></select></label>
            <button type="button" class="rte-tool" @click="chooseImage">جایگزینی تصویر</button>
            <button type="button" class="rte-tool text-rose-700" @click="removeImage">حذف تصویر</button>
        </div>

        <div v-else-if="activeTab === 'shape'" class="rte-toolbar flex flex-wrap items-center gap-2 border-b border-slate-200 bg-white p-3">
            <label class="rte-tool">رنگ زمینه<input type="color" class="h-5 w-5" @input="setShapeColor(($event.target as HTMLInputElement).value)" /></label>
            <label class="rte-tool">رنگ خط<input type="color" class="h-5 w-5" @input="setShapeBorderColor(($event.target as HTMLInputElement).value)" /></label>
            <select class="h-9 rounded-lg border-slate-300 text-xs" @change="setShapeWidth(($event.target as HTMLSelectElement).value)"><option value="60px">کوچک</option><option value="100px">متوسط</option><option value="160px">بزرگ</option></select>
            <button type="button" class="rte-tool text-rose-700" @click="removeShape">حذف شکل</button>
        </div>

        <div
            ref="editor"
            role="textbox"
            aria-multiline="true"
            :aria-label="label"
            :aria-disabled="disabled"
            :contenteditable="!disabled"
            :style="{ fontFamily }"
            class="rte-content min-h-72 whitespace-normal p-4 text-sm leading-8 text-slate-800 outline-none"
            data-placeholder="متن را اینجا بنویسید..."
            @input="onInput"
            @click="onEditorClick"
            @mouseup="refreshContext"
            @keyup="refreshContext"
            @focus="refreshContext"
        ></div>
    </div>
</template>

<style scoped>
.rte-tool{position:relative;display:inline-flex;min-height:34px;align-items:center;justify-content:center;gap:5px;border:1px solid #cbd5e1;border-radius:7px;background:#fff;padding:5px 9px;font-size:12px;color:#334155}
.rte-tool:hover{background:#f1f5f9}
.rte-tool:disabled{opacity:.4;cursor:not-allowed}
.rte-content{min-height:18rem;line-height:1.9;direction:rtl;text-align:right;overflow-wrap:anywhere}
.rte-content:empty:before{content:attr(data-placeholder);color:#94a3b8;pointer-events:none}
.rte-content :deep(table){width:100%;border-collapse:collapse;margin:8px 0}
.rte-content :deep(td),.rte-content :deep(th){border:1px solid #94a3b8;padding:6px;min-width:35px}
.rte-content :deep(img){max-width:100%;height:auto;vertical-align:middle}
.rte-content :deep([data-editor-shape]){margin:3px;min-width:12px;min-height:2px}
</style>
