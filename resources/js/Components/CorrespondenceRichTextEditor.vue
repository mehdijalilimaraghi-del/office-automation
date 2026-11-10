<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { AlignCenter, AlignJustify, AlignLeft, AlignRight, ArrowRight, Bold, Circle, Columns3, Image, IndentDecrease, IndentIncrease, Italic, Link, List, ListOrdered, Minus, PaintBucket, Redo2, RectangleHorizontal, Rows3, Strikethrough, Table2, Trash2, Underline, Undo2 } from '@lucide/vue';

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
const selectedCells = ref<HTMLTableCellElement[]>([]);
const cellSelectionAnchor = ref<HTMLTableCellElement | null>(null);
const borderTarget = ref<'all' | 'outer' | 'inner' | 'top' | 'bottom' | 'left' | 'right'>('all');
const colorPalette = ['#000000','#404040','#7f7f7f','#c0c0c0','#ffffff','#c00000','#ff0000','#ffc000','#ffff00','#92d050','#00b050','#00b0f0','#0070c0','#002060','#7030a0','#fce4d6','#f4cccc','#fff2cc','#e2f0d9','#d9eaf7','#e4dfec'];
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
    return block instanceof HTMLElement && block !== editor.value && editor.value?.contains(block) ? block : null;
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
    const activeRange = document.activeElement?.closest('.rte-toolbar')
        ? savedRange
        : (selection && selection.rangeCount && insideEditor(selection.getRangeAt(0).commonAncestorContainer) ? selection.getRangeAt(0) : savedRange);
    if (!activeRange || !insideEditor(activeRange.commonAncestorContainer)) return;
    const range = activeRange.cloneRange();
    target.focus();
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
        if (existingList.querySelectorAll('li').length > 1) return;
        const replacement = document.createElement('p');
        replacement.innerHTML = item.innerHTML;
        existingList.replaceWith(replacement);
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
    const range = document.activeElement?.closest('.rte-toolbar') ? savedRange?.cloneRange() : (selection?.rangeCount && insideEditor(selection.getRangeAt(0).commonAncestorContainer) ? selection.getRangeAt(0).cloneRange() : savedRange?.cloneRange());
    if (!range || !insideEditor(range.commonAncestorContainer) || range.collapsed) return;
    const text = range.toString();
    range.deleteContents();
    range.insertNode(document.createTextNode(text));
    commit();
}
function insertNode(node: Node): void {
    const target = editor.value;
    if (!target) return;
    const selection = window.getSelection();
    const activeRange = document.activeElement?.closest('.rte-toolbar')
        ? savedRange
        : (selection && selection.rangeCount > 0 && insideEditor(selection.getRangeAt(0).commonAncestorContainer) ? selection.getRangeAt(0) : savedRange);
    target.focus();
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
function selectTablePart(part: 'table' | 'row' | 'column'): void {
    const cell = currentCell.value, table = cell?.closest('table');
    if (!cell || !table) return;
    selectedCells.value = part === 'table' ? Array.from(table.querySelectorAll('td,th')) :
        part === 'row' ? Array.from(cell.parentElement?.querySelectorAll('td,th') ?? []) :
        Array.from(table.rows).map((row) => row.cells[cell.cellIndex]).filter((item): item is HTMLTableCellElement => !!item);
    paintCellSelection();
}
function paintCellSelection(): void {
    editor.value?.querySelectorAll('.rte-cell-selected').forEach((node) => node.classList.remove('rte-cell-selected'));
    selectedCells.value.forEach((cell) => cell.classList.add('rte-cell-selected'));
}
function selectCellRange(from: HTMLTableCellElement, to: HTMLTableCellElement): void {
    const table = from.closest('table');
    if (!table || to.closest('table') !== table) return;
    const rows = Array.from(table.rows), r1 = rows.indexOf(from.parentElement as HTMLTableRowElement), r2 = rows.indexOf(to.parentElement as HTMLTableRowElement);
    const c1 = Math.min(from.cellIndex,to.cellIndex), c2 = Math.max(from.cellIndex,to.cellIndex);
    selectedCells.value = [];
    for(let r=Math.min(r1,r2);r<=Math.max(r1,r2);r++) for(let col=c1;col<=c2;col++){const item=rows[r]?.cells[col];if(item)selectedCells.value.push(item);}
    paintCellSelection();
}
function mergeSelectedCells(): void {
    const cells=cellsForFormatting(); if(cells.length<2)return;
    const row=cells[0].parentElement;
    if(!row || !cells.every(cell=>cell.parentElement===row)){window.alert('برای ادغام، سلول‌های یک ردیف را انتخاب کنید.');return;}
    const ordered=cells.slice().sort((a,b)=>a.cellIndex-b.cellIndex);
    if(ordered.some((cell,i)=>i>0&&cell.cellIndex!==ordered[i-1].cellIndex+1))return;
    ordered[0].colSpan=ordered.length;
    ordered[0].innerHTML=ordered.map(cell=>cell.innerHTML).filter(Boolean).join(' ');
    ordered.slice(1).forEach(cell=>cell.remove());
    currentCell.value=ordered[0];selectedCells.value=[ordered[0]];commit();
}
function splitSelectedCell(): void {
    const cell=currentCell.value;if(!cell)return;
    const span=cell.colSpan||1;if(span<2)return;
    cell.colSpan=1;
    for(let i=1;i<span;i++){const next=document.createElement(cell.tagName.toLowerCase());next.innerHTML='<br>';next.style.cssText=cell.style.cssText;cell.after(next);}
    commit();
}
function deleteTable(): void {
    const table = currentCell.value?.closest('table');
    table?.remove();
    currentCell.value = null;
    commit();
}
function cellsForFormatting(): HTMLTableCellElement[] {
    return selectedCells.value.length ? selectedCells.value : currentCell.value ? [currentCell.value] : [];
}
function setCellBackground(value: string): void {
    cellsForFormatting().forEach((cell) => { cell.style.backgroundColor = value; });
    commit();
}
function setCellAlignment(value: string): void {
    cellsForFormatting().forEach((cell) => { cell.style.textAlign = value; });
    commit();
}
function setTableWidth(value: string): void {
    const table = currentCell.value?.closest('table');
    if (table instanceof HTMLTableElement) table.style.width = value;
    commit();
}
function setTableDimension(dimension: 'width' | 'height', value: string, unit: 'mm' | 'px'): void {
    const table = currentCell.value?.closest('table');
    if (table instanceof HTMLTableElement && value) table.style[dimension] = `${value}${unit}`;
    commit();
}
function setCellDimension(dimension: 'width' | 'height', value: string, unit: 'mm' | 'px'): void {
    if (value) cellsForFormatting().forEach((cell) => { cell.style[dimension] = `${value}${unit}`; });
    commit();
}

function setCellVerticalAlignment(value: string): void { cellsForFormatting().forEach(cell=>cell.style.verticalAlign=value); commit(); }
function setCellPadding(value: string, unit: 'mm'|'px'): void { if(value)cellsForFormatting().forEach(cell=>cell.style.padding=`${value}${unit}`);commit(); }
function applyCellBorders(callback:(cell:HTMLTableCellElement,side:string)=>void):void {
    cellsForFormatting().forEach(cell=>{
        let sides:string[];
        if(['top','bottom','left','right'].includes(borderTarget.value)) sides=[borderTarget.value];
        else if(borderTarget.value==='outer') {
            const row=cell.parentElement as HTMLTableRowElement, table=cell.closest('table') as HTMLTableElement;
            const rows=Array.from(table.rows), rowIndex=rows.indexOf(row);
            sides=[];
            if(rowIndex===0)sides.push('top');
            if(rowIndex===rows.length-1)sides.push('bottom');
            if(cell.cellIndex===0)sides.push('left');
            if(cell.cellIndex===row.cells.length-1)sides.push('right');
        } else sides=['top','right','bottom','left'];
        sides.forEach(side=>callback(cell,side));
    });
}
function setCellBorderColor(value:string):void {applyCellBorders((cell,side)=>cell.style.setProperty(`border-${side}-color`,value));commit();}
function setCellBorderWidth(value:string,unit:'mm'|'px'):void {applyCellBorders((cell,side)=>cell.style.setProperty(`border-${side}-width`,`${value}${unit}`));commit();}
function setCellBorderStyle(value:string):void {applyCellBorders((cell,side)=>cell.style.setProperty(`border-${side}-style`,value));commit();}
function setImageWidth(value: string): void {
    if (currentImage.value) currentImage.value.style.width = value;
    commit();
}
function setImageDimension(dimension: 'width' | 'height', value: string, unit: 'mm' | 'px'): void {
    if (!currentImage.value || !value) return;
    currentImage.value.style[dimension] = `${value}${unit}`;
    if (dimension === 'width') currentImage.value.style.height = 'auto';
    commit();
}
function setImageWrap(value: 'inline' | 'block' | 'left' | 'right'): void {
    if (!currentImage.value) return;
    const image=currentImage.value;
    image.style.display=value==='inline'?'inline-block':'block';
    image.style.float=value==='left'||value==='right'?value:'none';
    image.style.marginLeft=value==='right'?'0':value==='left'||value==='block'?'auto':'0';
    image.style.marginRight=value==='left'?'0':value==='right'||value==='block'?'auto':'0';
    commit();
}
function setImageAlignment(value: string): void {
    if (!currentImage.value) return;
    currentImage.value.style.display = 'block';
    currentImage.value.style.marginLeft = value === 'right' ? 'auto' : value === 'left' ? '0' : 'auto';
    currentImage.value.style.marginRight = value === 'left' ? 'auto' : value === 'right' ? '0' : 'auto';
    commit();
}
function setImageHeight(value: string): void {
    if (currentImage.value) currentImage.value.style.height = value;
    commit();
}
function setImageBorder(value: string): void {
    if (currentImage.value) currentImage.value.style.border = value;
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
function setShapeDimension(dimension: 'width' | 'height', value: string, unit: 'mm' | 'px'): void {
    if(currentShape.value&&value)currentShape.value.style[dimension]=`${value}${unit}`;
    commit();
}
function setShapeWidth(value: string): void {
    if (!currentShape.value) return;
    currentShape.value.style.width = value;
    commit();
}
function setShapeHeight(value: string): void {
    if (!currentShape.value) return;
    currentShape.value.style.height = value;
    commit();
}
function setShapeBorderWidth(value: string): void {
    if (!currentShape.value) return;
    currentShape.value.style.borderWidth = value;
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
let resizeState: { cell: HTMLTableCellElement; axis: 'width'|'height'; start: number; initial: number } | null = null;
function onEditorPointerDown(event: PointerEvent): void {
    const rawTarget=event.target;
    const target=rawTarget instanceof Element ? rawTarget.closest('td,th') as HTMLTableCellElement | null : null;
    if(!target)return;
    const rect=target.getBoundingClientRect(), edge=6;
    const nearRight=Math.abs(event.clientX-rect.right)<=edge;
    const nearBottom=Math.abs(event.clientY-rect.bottom)<=edge;
    if(!nearRight&&!nearBottom)return;
    resizeState={cell:target,axis:nearRight?'width':'height',start:nearRight?event.clientX:event.clientY,initial:nearRight?rect.width:rect.height};
    event.preventDefault();
    document.addEventListener('pointermove',onEditorPointerMove);
    document.addEventListener('pointerup',onEditorPointerUp,{once:true});
}
function onEditorPointerMove(event:PointerEvent):void {
    if(!resizeState)return;
    const delta=(resizeState.axis==='width'?event.clientX:event.clientY)-resizeState.start;
    const px=Math.max(20,resizeState.initial+delta);
    resizeState.cell.style[resizeState.axis]=`${px}px`;
}
function onEditorPointerUp():void {
    document.removeEventListener('pointermove',onEditorPointerMove);
    if(resizeState){resizeState=null;commit();}
}
function onEditorClick(event: MouseEvent): void {
    const target = event.target;
    if (!(target instanceof Element) || !editor.value?.contains(target)) return;
    currentImage.value = target.closest('img') as HTMLImageElement | null;
    currentShape.value = target.closest('[data-editor-shape]') as HTMLElement | null;
    const clickedCell = target.closest('td,th') as HTMLTableCellElement | null;
    if (clickedCell && event.shiftKey && cellSelectionAnchor.value) { currentCell.value=clickedCell; selectCellRange(cellSelectionAnchor.value, clickedCell); }
    else if (clickedCell) { currentCell.value=clickedCell;cellSelectionAnchor.value=clickedCell;selectedCells.value=[clickedCell];paintCellSelection(); }
    else selectedCells.value=[];
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
onBeforeUnmount(() => { window.removeEventListener('selectionchange', onSelectionChange); document.removeEventListener('pointermove', onEditorPointerMove); document.removeEventListener('pointerup', onEditorPointerUp); });
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
            <button type="button" class="rte-tool" title="پررنگ" aria-label="پررنگ" @click="applyFormat('strong')"><Bold :size="16" /></button>
            <button type="button" class="rte-tool" title="مورب" aria-label="مورب" @click="applyFormat('em')"><Italic :size="16" /></button>
            <button type="button" class="rte-tool" title="زیرخط" aria-label="زیرخط" @click="applyFormat('u')"><Underline :size="16" /></button>
            <button type="button" class="rte-tool" title="خط‌خورده" aria-label="خط‌خورده" @click="applyFormat('s')"><Strikethrough :size="16" /></button>
            <div class="rte-tool" title="رنگ متن">A
                <button v-for="color in colorPalette" :key="'font-'+color" type="button" class="h-4 w-4 rounded-sm border border-slate-300" :style="{backgroundColor:color}" :title="color" @click="applyTextColor(color)"></button>
                <input type="color" title="رنگ دلخواه متن" @input="applyTextColor(($event.target as HTMLInputElement).value)" />
            </div>
            <div class="rte-tool" title="رنگ پس‌زمینه متن">هایلایت
                <button v-for="color in colorPalette" :key="'highlight-'+color" type="button" class="h-4 w-4 rounded-sm border border-slate-300" :style="{backgroundColor:color}" :title="color" @click="applyHighlight(color)"></button>
                <input type="color" title="رنگ دلخواه زمینه متن" @input="applyHighlight(($event.target as HTMLInputElement).value)" />
            </div>
            <span class="mx-1 h-6 w-px bg-slate-200"></span>
            <button type="button" class="rte-tool" title="راست‌چین" aria-label="راست‌چین" @click="align('right')"><AlignRight :size="16" /></button>
            <button type="button" class="rte-tool" title="وسط‌چین" aria-label="وسط‌چین" @click="align('center')"><AlignCenter :size="16" /></button>
            <button type="button" class="rte-tool" title="چپ‌چین" aria-label="چپ‌چین" @click="align('left')"><AlignLeft :size="16" /></button>
            <button type="button" class="rte-tool" title="تراز دوطرفه" aria-label="تراز دوطرفه" @click="align('justify')"><AlignJustify :size="16" /></button>
            <select class="h-9 w-24 rounded-lg border-slate-300 text-xs" aria-label="فاصله خطوط" @change="setLineHeight(($event.target as HTMLSelectElement).value)"><option value="1.5">فاصله ۱٫۵</option><option value="1.8" selected>فاصله ۱٫۸</option><option value="2">فاصله ۲</option></select>
            <select class="h-9 w-24 rounded-lg border-slate-300 text-xs" aria-label="فاصله پاراگراف" @change="setParagraphSpacing(($event.target as HTMLSelectElement).value)"><option value="4px">کم</option><option value="12px" selected>معمولی</option><option value="20px">زیاد</option></select>
            <button type="button" class="rte-tool" title="فهرست نشانه‌دار" aria-label="فهرست نشانه‌دار" @click="toggleList(false)"><List :size="16" /></button>
            <button type="button" class="rte-tool" title="فهرست شماره‌دار" aria-label="فهرست شماره‌دار" @click="toggleList(true)"><ListOrdered :size="16" /></button>
            <button type="button" class="rte-tool" title="افزایش تورفتگی" aria-label="افزایش تورفتگی" @click="indent(true)"><IndentIncrease :size="16" /></button>
            <button type="button" class="rte-tool" title="کاهش تورفتگی" aria-label="کاهش تورفتگی" @click="indent(false)"><IndentDecrease :size="16" /></button>
            <button type="button" class="rte-tool" title="راست به چپ" @click="setDirection('rtl')">RTL</button>
            <button type="button" class="rte-tool" title="چپ به راست" @click="setDirection('ltr')">LTR</button>
            <button type="button" class="rte-tool" title="واگرد" aria-label="واگرد" :disabled="historyIndex === 0" @click="undo"><Undo2 :size="16" /></button>
            <button type="button" class="rte-tool" title="ازنو" aria-label="ازنو" :disabled="historyIndex >= history.length - 1" @click="redo"><Redo2 :size="16" /></button>
            <button type="button" class="rte-tool text-rose-700" title="حذف قالب‌بندی" @click="clearFormatting">پاک‌سازی قالب</button>
        </div>

        <div v-else-if="activeTab === 'insert'" class="rte-toolbar flex flex-wrap items-center gap-2 border-b border-slate-200 bg-white p-3">
            <button type="button" class="rte-tool" title="درج جدول ۳×۳" @click="insertTable"><Table2 :size="16" /> جدول</button>
            <button type="button" class="rte-tool" title="درج تصویر" @click="chooseImage"><Image :size="16" /> تصویر</button>
            <button type="button" class="rte-tool" title="درج مستطیل" @click="insertShape('rectangle')"><RectangleHorizontal :size="16" /> مستطیل</button>
            <button type="button" class="rte-tool" title="درج بیضی" @click="insertShape('ellipse')"><Circle :size="16" /> بیضی</button>
            <button type="button" class="rte-tool" title="درج خط" @click="insertShape('line')"><Minus :size="16" /> خط</button>
            <button type="button" class="rte-tool" title="درج پیکان" @click="insertShape('arrow')"><ArrowRight :size="16" /> پیکان</button>
            <button type="button" class="rte-tool" title="درج پیوند" @click="insertLink"><Link :size="16" /> پیوند</button>
            <input ref="imageInput" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="insertImageFile" />
            <p class="w-full text-xs text-slate-500">تصویر به‌صورت امن داخل محتوای نامه ذخیره می‌شود؛ فرمت PNG، JPG یا WEBP و حداکثر ۱۸۰ کیلوبایت.</p>
        </div>

        <div v-else-if="activeTab === 'table'" class="rte-toolbar flex flex-wrap items-center gap-2 border-b border-slate-200 bg-white p-3">
            <button type="button" class="rte-tool" @click="addTableRow"><Rows3 :size="16" /> ردیف +</button>
            <button type="button" class="rte-tool" @click="addTableColumn"><Columns3 :size="16" /> ستون +</button>
            <button type="button" class="rte-tool" @click="deleteTableRow">ردیف −</button>
            <button type="button" class="rte-tool" @click="deleteTableColumn">ستون −</button>
            <button type="button" class="rte-tool" @click="selectTablePart('table')">انتخاب جدول</button>
            <button type="button" class="rte-tool" @click="selectTablePart('row')">انتخاب ردیف</button>
            <button type="button" class="rte-tool" @click="selectTablePart('column')">انتخاب ستون</button>
            <button type="button" class="rte-tool" @click="mergeSelectedCells">ادغام سلول‌ها</button>
            <button type="button" class="rte-tool" @click="splitSelectedCell">تقسیم سلول</button>
            <button type="button" class="rte-tool text-rose-700" @click="deleteTable"><Trash2 :size="16" /> حذف جدول</button>
            <label class="text-xs">عرض جدول <input class="rte-number" type="number" min="10" max="210" value="180" @change="setTableDimension('width',($event.target as HTMLInputElement).value,'mm')" /> mm</label>
            <label class="text-xs">عرض ستون/ارتفاع ردیف <input class="rte-number" type="number" min="1" value="30" @change="setCellDimension('width',($event.target as HTMLInputElement).value,'mm')" /> mm</label>
            <label class="text-xs">ارتفاع ردیف <input class="rte-number" type="number" min="1" value="10" @change="setCellDimension('height',($event.target as HTMLInputElement).value,'mm')" /> mm</label>
            <label class="text-xs">حاشیه داخلی <input class="rte-number" type="number" min="0" value="2" @change="setCellPadding(($event.target as HTMLInputElement).value,'mm')" /> mm</label>
            <label class="text-xs">اعمال حاشیه
                <select v-model="borderTarget" class="rte-select"><option value="all">همه اضلاع</option><option value="outer">کادر بیرونی</option><option value="inner">خطوط داخلی</option><option value="top">بالا</option><option value="bottom">پایین</option><option value="left">چپ</option><option value="right">راست</option></select>
            </label>
            <label class="text-xs">رنگ خط <input type="color" value="#94a3b8" @input="setCellBorderColor(($event.target as HTMLInputElement).value)" /></label>
            <label class="text-xs">ضخامت خط <input class="rte-number" type="number" min="0" max="10" step="0.25" value="0.25" @change="setCellBorderWidth(($event.target as HTMLInputElement).value,'mm')" /> mm</label>
            <label class="text-xs">نوع خط <select class="rte-select" @change="setCellBorderStyle(($event.target as HTMLSelectElement).value)"><option value="solid">پیوسته</option><option value="dashed">خط‌چین</option><option value="dotted">نقطه‌چین</option><option value="double">دوتایی</option><option value="none">بدون خط</option></select></label>
            <label class="text-xs">زمینه سلول</label>
            <div class="flex flex-wrap gap-1">
                <button v-for="color in colorPalette" :key="color" type="button" class="h-6 w-6 rounded border border-slate-300" :style="{backgroundColor:color}" :title="color" @click="setCellBackground(color)"></button>
                <input type="color" title="رنگ دلخواه" @input="setCellBackground(($event.target as HTMLInputElement).value)" />
                <button type="button" class="rte-tool" @click="setCellBackground('transparent')">بدون رنگ</button>
            </div>
            <label class="text-xs">تراز افقی <select class="rte-select" @change="setCellAlignment(($event.target as HTMLSelectElement).value)"><option value="right">راست</option><option value="center">وسط</option><option value="left">چپ</option><option value="justify">دوطرفه</option></select></label>
            <label class="text-xs">تراز عمودی <select class="rte-select" @change="setCellVerticalAlignment(($event.target as HTMLSelectElement).value)"><option value="top">بالا</option><option value="middle">وسط</option><option value="bottom">پایین</option></select></label>
        </div>
        <div v-else-if="activeTab === 'picture'" class="rte-toolbar flex flex-wrap items-center gap-2 border-b border-slate-200 bg-white p-3">
            <label class="text-xs">عرض <input class="rte-number" type="number" min="1" value="50" @change="setImageDimension('width',($event.target as HTMLInputElement).value,'mm')" /> mm</label>
            <label class="text-xs">ارتفاع <input class="rte-number" type="number" min="1" value="30" @change="setImageDimension('height',($event.target as HTMLInputElement).value,'mm')" /> mm</label>
            <label class="text-xs">جایگاه <select class="rte-select" @change="setImageAlignment(($event.target as HTMLSelectElement).value)"><option value="right">راست</option><option value="center">وسط</option><option value="left">چپ</option></select></label>
            <label class="text-xs">قرارگیری نسبت به متن <select class="rte-select" @change="setImageWrap(($event.target as HTMLSelectElement).value as 'inline'|'block'|'left'|'right')"><option value="inline">همراه متن</option><option value="block">بالا و پایین متن</option><option value="right">شناور راست</option><option value="left">شناور چپ</option></select></label>
            <label class="text-xs">رنگ کادر <input type="color" @input="setImageBorder('1px solid '+($event.target as HTMLInputElement).value)" /></label>
            <button type="button" class="rte-tool" @click="chooseImage"><Image :size="16" /> جایگزینی</button>
            <button type="button" class="rte-tool text-rose-700" @click="removeImage"><Trash2 :size="16" /> حذف</button>
        </div>
        <div v-else-if="activeTab === 'shape'" class="rte-toolbar flex flex-wrap items-center gap-2 border-b border-slate-200 bg-white p-3">
            <label class="rte-tool">رنگ زمینه <input type="color" @input="setShapeColor(($event.target as HTMLInputElement).value)" /></label>
            <label class="rte-tool">رنگ خط <input type="color" @input="setShapeBorderColor(($event.target as HTMLInputElement).value)" /></label>
            <label class="text-xs">عرض <input class="rte-number" type="number" min="1" value="30" @change="setShapeDimension('width',($event.target as HTMLInputElement).value,'mm')" /> mm</label>
            <label class="text-xs">ارتفاع <input class="rte-number" type="number" min="1" value="15" @change="setShapeDimension('height',($event.target as HTMLInputElement).value,'mm')" /> mm</label>
            <button type="button" class="rte-tool text-rose-700" @click="removeShape"><Trash2 :size="16" /> حذف شکل</button>
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
            @pointerdown="onEditorPointerDown"
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
.rte-number{width:76px;height:32px;margin:0 4px;padding:3px 6px;border:1px solid #cbd5e1;border-radius:6px;direction:ltr}
.rte-select{height:32px;margin:0 4px;padding:3px 6px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px}
.rte-content :deep(.rte-cell-selected){outline:2px solid #2563eb;outline-offset:-2px;background-image:linear-gradient(#dbeafe66,#dbeafe66)}
.rte-content{min-height:18rem;line-height:1.9;direction:rtl;text-align:right;overflow-wrap:anywhere}
.rte-content:empty:before{content:attr(data-placeholder);color:#94a3b8;pointer-events:none}
.rte-content :deep(table){width:100%;border-collapse:collapse;margin:8px 0}
.rte-content :deep(td),.rte-content :deep(th){border:1px solid #94a3b8;padding:6px;min-width:35px;position:relative;cursor:cell}
.rte-content :deep(td:hover),.rte-content :deep(th:hover){outline:1px dashed #94a3b8;outline-offset:-1px}
.rte-content :deep(td),.rte-content :deep(th){user-select:text}
.rte-content :deep(img){max-width:100%;height:auto;vertical-align:middle}
.rte-content :deep([data-editor-shape]){margin:3px;min-width:12px;min-height:2px}
</style>
