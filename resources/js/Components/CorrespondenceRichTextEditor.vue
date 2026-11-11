<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { AlignCenter, AlignJustify, AlignLeft, AlignRight, ArrowRight, Bold, Circle, Columns3, Image, IndentDecrease, IndentIncrease, Italic, Link, List, ListOrdered, Minus, PaintBucket, Redo2, RectangleHorizontal, Rows3, Strikethrough, Table2, Trash2, Underline, Undo2, Palette, RotateCw, FlipHorizontal, FlipVertical, Scissors, Copy, Clipboard, Eraser, Grid3X3, TableProperties, Maximize2 } from '@lucide/vue';

type Tab = 'home' | 'insert' | 'table' | 'picture' | 'shape';
const props = withDefaults(defineProps<{ modelValue: string; label: string; fontFamily?: string; disabled?: boolean }>(), {
    fontFamily: 'Vazirmatn',
    disabled: false,
});
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const editor = ref<HTMLDivElement | null>(null);
const editorShell = ref<HTMLDivElement | null>(null);
const keepImageRatio = ref(false);
const currentFontSize = ref('12pt');
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
const colorPalette = ['#000000','#1f1f1f','#404040','#595959','#7f7f7f','#a6a6a6','#bfbfbf','#d9d9d9','#f2f2f2','#ffffff','#c00000','#ff0000','#ff6600','#ffc000','#ffff00','#92d050','#00b050','#00b0f0','#0070c0','#002060','#7030a0','#7030a0','#c55a11','#843c0c','#e6b8af','#f4cccc','#fce4d6','#fff2cc','#e2f0d9','#d9eaf7','#e4dfec'];
const themeColors = ['#ffffff','#000000','#e7e6e6','#44546a','#5b9bd5','#ed7d31','#a5a5a5','#ffc000','#4472c4','#70ad47'];
const colorMenu = ref<string | null>(null);
const dragSelection = ref<{ table: HTMLTableElement; start: HTMLTableCellElement } | null>(null);
let didDragSelect = false;
const contextMenu = ref<{x:number;y:number;kind:'text'|'table'|'picture'|'shape'} | null>(null);
const currentRotation = (element: HTMLElement | null): number => Number(element?.dataset.rotation || 0);
const setRotation = (element: HTMLElement | null, delta: number): void => { if(!element)return; element.dataset.rotation=String((currentRotation(element)+delta+360)%360); applyElementTransform(element); commit(); };
const applyElementTransform = (element: HTMLElement): void => { const flipX=element.dataset.flipX==='true'?-1:1; const flipY=element.dataset.flipY==='true'?-1:1; element.style.transform=`rotate(${currentRotation(element)}deg) scale(${flipX}, ${flipY})`; element.style.transformOrigin='center center'; };
const toggleFlip = (element: HTMLElement | null, axis:'x'|'y'): void => {if(!element)return;const key=axis==='x'?'flipX':'flipY';element.dataset[key]=element.dataset[key]==='true'?'false':'true';applyElementTransform(element);commit();};
const displayedColor = (kind:'text'|'highlight'|'cell'|'cell-fill'|'image'|'shape-fill'|'shape-line'): string => {const element=kind==='text'||kind==='highlight'?selectedElement():kind==='cell'?currentCell.value:kind==='image'?currentImage.value:currentShape.value;if(!element)return 'transparent';const style=getComputedStyle(element);return kind==='text'?style.color:kind==='highlight'?style.backgroundColor:kind==='cell'?style.borderTopColor:kind==='cell-fill'?style.backgroundColor:kind==='image'?style.borderTopColor:kind==='shape-fill'?style.backgroundColor:style.borderTopColor;};
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
    const toolbarFocused = !!document.activeElement?.closest('.rte-toolbar');
    const editorFocused = !!editor.value?.contains(document.activeElement);
    // Preserve contextual object selection while focus moves between editor and toolbar.
    if ((toolbarFocused || editorFocused) && currentImage.value?.isConnected) {
        context.value = { table: false, picture: true, shape: false };
        return;
    }
    if ((toolbarFocused || editorFocused) && currentShape.value?.isConnected) {
        context.value = { table: false, picture: false, shape: true };
        return;
    }
    if ((toolbarFocused || editorFocused) && currentCell.value?.isConnected) {
        context.value = { table: true, picture: false, shape: false };
        return;
    }
    if (toolbarFocused) {
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
    if (selection?.rangeCount) {
        savedRange = selection.getRangeAt(0).cloneRange();
        updateSelectedFontSize(savedRange);
    }
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
function updateSelectedFontSize(range: Range): void {
    const startNode = range.startContainer instanceof Element ? range.startContainer : range.startContainer.parentElement;
    const endNode = range.endContainer instanceof Element ? range.endContainer : range.endContainer.parentElement;
    if (!startNode || !endNode || !insideEditor(startNode) || !insideEditor(endNode)) return;
    const startPx = Number.parseFloat(getComputedStyle(startNode).fontSize);
    const endPx = Number.parseFloat(getComputedStyle(endNode).fontSize);
    if (!Number.isFinite(startPx) || !Number.isFinite(endPx)) return;
    const startPt = Math.round(startPx * 0.75 * 100) / 100;
    const endPt = Math.round(endPx * 0.75 * 100) / 100;
    currentFontSize.value = Math.abs(startPt - endPt) < 0.01 ? `${startPt}pt` : '';
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
function clearHighlight(): void {
    const selection = window.getSelection();
    const range = document.activeElement?.closest('.rte-toolbar')
        ? savedRange?.cloneRange()
        : (selection?.rangeCount && insideEditor(selection.getRangeAt(0).commonAncestorContainer) ? selection.getRangeAt(0).cloneRange() : savedRange?.cloneRange());
    if (!range || range.collapsed || !editor.value) return;
    let node: Node | null = range.commonAncestorContainer;
    if (node.nodeType !== Node.ELEMENT_NODE) node = node.parentElement;
    while (node instanceof HTMLElement && node !== editor.value) {
        const contents = document.createRange();
        contents.selectNodeContents(node);
        const fullySelected = range.compareBoundaryPoints(Range.START_TO_START, contents) <= 0
            && range.compareBoundaryPoints(Range.END_TO_END, contents) >= 0;
        if (fullySelected && node.style.backgroundColor) node.style.removeProperty('background-color');
        node = node.parentElement;
    }
    commit();
}
function align(value: string): void { setBlockStyle('text-align', value); }
function setDirection(value: 'rtl' | 'ltr'): void {
    const block = findBlock(selectedElement()) ?? editor.value;
    if (block) block.setAttribute('dir', value);
    commit();
}
function setLineHeight(value: string): void { if (value) setBlockStyle('line-height', value); }
function setParagraphSpacing(value: string): void { if (value !== '') setBlockStyle('margin-bottom', `${value}px`); }
function dimensionMm(element: HTMLElement | null, axis: 'width' | 'height'): string {
    if (!element) return '';
    const px = axis === 'width' ? element.offsetWidth : element.offsetHeight;
    return px > 0 ? (px * 25.4 / 96).toFixed(1) : '';
}
function selectedTable(): HTMLTableElement | null { return currentCell.value?.closest('table') as HTMLTableElement | null; }
function selectedCell(): HTMLTableCellElement | null { return currentCell.value; }
function closeColorMenus(event: PointerEvent): void {
    const target = event.target;
    if (!(target instanceof Element) || target.closest('.rte-color-menu')) return;
    editorShell.value?.querySelectorAll<HTMLDetailsElement>('.rte-color-menu[open]').forEach(menu => { menu.open = false; });
}
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
    const cells=cellsForFormatting();if(cells.length<2)return;
    const table=cells[0].closest('table') as HTMLTableElement|null;if(!table||!cells.every(cell=>cell.closest('table')===table))return;
    const rows=Array.from(table.rows), selected=new Set(cells);
    const coords=cells.map(cell=>({r:rows.indexOf(cell.parentElement as HTMLTableRowElement),c:cell.cellIndex}));
    const minR=Math.min(...coords.map(x=>x.r)),maxR=Math.max(...coords.map(x=>x.r)),minC=Math.min(...coords.map(x=>x.c)),maxC=Math.max(...coords.map(x=>x.c));
    const rectangle:HTMLTableCellElement[]=[];
    for(let r=minR;r<=maxR;r++)for(let col=minC;col<=maxC;col++){const cell=rows[r]?.cells[col];if(!cell||!selected.has(cell)){window.alert('برای ادغام، محدوده‌ای مستطیلی و پیوسته از سلول‌ها انتخاب کنید.');return;}rectangle.push(cell);}
    if(rectangle.some(cell=>cell.colSpan>1||cell.rowSpan>1)){window.alert('ابتدا سلول‌های ادغام‌شده موجود را تقسیم کنید.');return;}
    const first=rows[minR].cells[minC];
    first.innerHTML=rectangle.map(cell=>cell.innerHTML).filter(html=>html&&html!=='<br>').join('<br>')||'<br>';
    first.colSpan=maxC-minC+1;first.rowSpan=maxR-minR+1;
    rectangle.slice(1).forEach(cell=>cell.remove());
    currentCell.value=first;selectedCells.value=[first];commit();
}
function splitSelectedCell(): void {
    const cell=currentCell.value;if(!cell)return;
    const colspan=cell.colSpan||1,rowspan=cell.rowSpan||1;if(colspan===1&&rowspan===1)return;
    const table=cell.closest('table') as HTMLTableElement|null,row=cell.parentElement as HTMLTableRowElement|null;
    if(!table||!row)return;
    const rows=Array.from(table.rows),rowIndex=rows.indexOf(row),cellIndex=cell.cellIndex;
    cell.colSpan=1;cell.rowSpan=1;
    for(let r=0;r<rowspan;r++){
        const targetRow=rows[rowIndex+r];if(!targetRow)continue;
        for(let col=0;col<colspan;col++){
            if(r===0&&col===0)continue;
            const next=document.createElement(cell.tagName.toLowerCase());next.innerHTML='<br>';next.style.cssText=cell.style.cssText;
            targetRow.insertBefore(next,targetRow.children[cellIndex+col]??null);
        }
    }
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
    cellsForFormatting().forEach((cell) => { if (value === 'transparent') cell.style.removeProperty('background-color'); else cell.style.backgroundColor = value; });
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
function setTableAlignment(value:'left'|'center'|'right'):void {const table=currentCell.value?.closest('table') as HTMLTableElement|null;if(!table)return;table.style.marginLeft=value==='right'?'auto':value==='center'?'auto':'0';table.style.marginRight=value==='left'?'auto':value==='center'?'auto':'0';table.style.float='none';table.style.width=table.style.width||'100%';if(value==='left'){table.style.marginLeft='0';table.style.marginRight='auto';}commit();}
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
    const cells=cellsForFormatting(), selected=new Set(cells);
    cells.forEach(cell=>{
        const row=cell.parentElement as HTMLTableRowElement, table=cell.closest('table') as HTMLTableElement;
        const rows=Array.from(table.rows), ri=rows.indexOf(row), ci=cell.cellIndex;
        const neighbors:{top?:Element|null;right?:Element|null;bottom?:Element|null;left?:Element|null}={top:rows[ri-1]?.cells[ci],right:row.cells[ci+1],bottom:rows[ri+1]?.cells[ci],left:row.cells[ci-1]};
        let sides:string[];
        if(['top','bottom','left','right'].includes(borderTarget.value)) sides=[borderTarget.value];
        else if(borderTarget.value==='outer') sides=(['top','right','bottom','left'] as const).filter(side=>!neighbors[side]||!selected.has(neighbors[side] as HTMLTableCellElement));
        else if(borderTarget.value==='inner') sides=(['top','right','bottom','left'] as const).filter(side=>!!neighbors[side]&&selected.has(neighbors[side] as HTMLTableCellElement));
        else sides=['top','right','bottom','left'];
        sides.forEach(side=>callback(cell,side));
    });
}
function setCellBorderColor(value:string):void {applyCellBorders((cell,side)=>{if(value==='transparent'){cell.style.setProperty(`border-${side}-style`,'none');cell.style.removeProperty(`border-${side}-color`);}else{cell.style.setProperty(`border-${side}-color`,value);if(cell.style.getPropertyValue(`border-${side}-style`)==='none')cell.style.setProperty(`border-${side}-style`,'solid');}});commit();}
function setCellBorderWidth(value:string,unit:'mm'|'px'):void {applyCellBorders((cell,side)=>cell.style.setProperty(`border-${side}-width`,`${value}${unit}`));commit();}
function setCellBorderStyle(value:string):void {applyCellBorders((cell,side)=>cell.style.setProperty(`border-${side}-style`,value));commit();}
function setImageWidth(value: string): void {
    if (currentImage.value) currentImage.value.style.width = value;
    commit();
}
function setImageDimension(dimension: 'width' | 'height', value: string, unit: 'mm' | 'px'): void {
    const image=currentImage.value;if(!image||value==='')return;
    const numeric=Number(value);if(!Number.isFinite(numeric)||numeric<=0)return;
    const ratio=(image.naturalWidth||image.width)/(image.naturalHeight||image.height||1);
    image.style.aspectRatio=String(ratio);
    image.style[dimension]=`${numeric}${unit}`;
    if (keepImageRatio.value) image.style[dimension==='width'?'height':'width']=`${dimension==='width'?numeric/ratio:numeric*ratio}${unit}`;
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
    if (!currentImage.value) return;
    if (value === 'none') currentImage.value.style.removeProperty('border');
    else currentImage.value.style.border = value;
    commit();
}
function chooseImage(): void { imageInput.value?.click(); }
function insertImageFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file || !['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) return;
    if (file.size > 350 * 1024) {
        window.alert('حجم تصویر بیش از حد مجاز برای محتوای نامه است. تصویر را فشرده کنید یا از بخش پیوست فایل استفاده کنید.');
        return;
    }
    const reader = new FileReader();
    reader.onload = () => {
        if (typeof reader.result !== 'string') return;
        const image = document.createElement('img');
        image.alt = file.name;
        image.style.width = '50%';
        image.style.height = 'auto';
        image.onload = () => { image.style.aspectRatio = `${image.naturalWidth} / ${image.naturalHeight}`; commit(); };
        image.src = reader.result;
        image.style.display = 'block';
        if (currentImage.value?.isConnected) {
            currentImage.value.replaceWith(image);
            emitContent();
        } else {
            insertNode(image);
        }
        currentImage.value = image;image.classList.add('rte-selected');
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
    currentShape.value = element;element.classList.add('rte-selected');
    context.value = { table: false, picture: false, shape: true };
    activeTab.value = 'shape';
}
function setShapeColor(value: string): void {
    if (!currentShape.value) return;
    if (value === 'transparent') currentShape.value.style.removeProperty('background-color');
    else currentShape.value.style.backgroundColor = value;
    commit();
}
function setShapeBorderColor(value: string): void {
    if (!currentShape.value) return;
    if (value === 'transparent') currentShape.value.style.borderColor = 'transparent';
    else currentShape.value.style.borderColor = value;
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
let resizeState: { cell: HTMLTableCellElement; axis: 'width'|'height'; start: number; initial: number; table: HTMLTableElement; index: number } | null = null;
function onEditorPointerDown(event: PointerEvent): void {
    const rawTarget=event.target;
    const target=rawTarget instanceof Element ? rawTarget.closest('td,th') as HTMLTableCellElement | null : null;
    if(!target)return;
    const rect=target.getBoundingClientRect(), edge=6;
    const nearRight=Math.abs(event.clientX-rect.right)<=edge;
    const nearBottom=Math.abs(event.clientY-rect.bottom)<=edge;
    if(!nearRight&&!nearBottom){const table=target.closest('table') as HTMLTableElement|null;if(table){dragSelection.value={table,start:target};didDragSelect=false;event.preventDefault();document.addEventListener('pointermove',onCellDragMove);document.addEventListener('pointerup',onCellDragEnd,{once:true});}return;}
    const table=target.closest('table') as HTMLTableElement|null;if(!table)return;resizeState={cell:target,axis:nearRight?'width':'height',start:nearRight?event.clientX:event.clientY,initial:nearRight?rect.width:rect.height,table,index:nearRight?target.cellIndex:Array.from(table.rows).indexOf(target.parentElement as HTMLTableRowElement)};
    event.preventDefault();
    document.addEventListener('pointermove',onEditorPointerMove);
    document.addEventListener('pointerup',onEditorPointerUp,{once:true});
}
function onCellDragMove(event:PointerEvent):void {if(!dragSelection.value)return;const node=document.elementFromPoint(event.clientX,event.clientY);const cell=node instanceof Element?node.closest('td,th') as HTMLTableCellElement|null:null;if(!cell||cell.closest('table')!==dragSelection.value.table)return;didDragSelect=true;currentCell.value=cell;selectCellRange(dragSelection.value.start,cell);}
function onCellDragEnd():void {document.removeEventListener('pointermove',onCellDragMove);if(dragSelection.value){if(didDragSelect){currentCell.value=selectedCells.value[selectedCells.value.length-1]??dragSelection.value.start;paintCellSelection();}dragSelection.value=null;}}
function onEditorPointerMove(event:PointerEvent):void {
    if(!resizeState)return;
    const delta=(resizeState.axis==='width'?event.clientX:event.clientY)-resizeState.start;
    const px=Math.max(20,resizeState.initial+delta);
    if(resizeState.axis==='width') Array.from(resizeState.table.rows).forEach(row=>{const cell=row.cells[resizeState!.index];if(cell)cell.style.width=`${px}px`;});
    else Array.from(resizeState.table.rows[resizeState.index]?.cells??[]).forEach(cell=>{cell.style.height=`${px}px`;});
}
function onEditorPointerUp():void {
    document.removeEventListener('pointermove',onEditorPointerMove);
    if(resizeState){resizeState=null;commit();}
}
function onEditorClick(event: MouseEvent): void {
    const target = event.target;
    if (!(target instanceof Element) || !editor.value?.contains(target)) return;
    if(didDragSelect){didDragSelect=false;return;}
    editor.value?.querySelectorAll('.rte-selected').forEach(node=>node.classList.remove('rte-selected'));
    currentImage.value = target.closest('img') as HTMLImageElement | null;
    currentShape.value = target.closest('[data-editor-shape]') as HTMLElement | null;
    currentImage.value?.classList.add('rte-selected');currentShape.value?.classList.add('rte-selected');
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
function onEditorContextMenu(event: MouseEvent):void {const target=event.target;if(!(target instanceof Element)||!editor.value?.contains(target))return;event.preventDefault();const image=target.closest('img') as HTMLImageElement|null;const shape=target.closest('[data-editor-shape]') as HTMLElement|null;const table=target.closest('table');currentImage.value=image;currentShape.value=shape;currentCell.value=target.closest('td,th') as HTMLTableCellElement|null;contextMenu.value={x:Math.min(event.clientX,window.innerWidth-230),y:Math.min(event.clientY,window.innerHeight-300),kind:image?'picture':shape?'shape':table?'table':'text'};if(image){context.value={table:false,picture:true,shape:false};activeTab.value='picture';}else if(shape){context.value={table:false,picture:false,shape:true};activeTab.value='shape';}else if(table){context.value={table:true,picture:false,shape:false};activeTab.value='table';}}
function onEditorContextAction(action:string):void {contextMenu.value=null;if(action==='delete-image')removeImage();else if(action==='delete-shape')removeShape();else if(action==='rotate-left')setRotation(currentImage.value??currentShape.value,-90);else if(action==='rotate-right')setRotation(currentImage.value??currentShape.value,90);else if(action==='select-table')selectTablePart('table');else if(action==='copy')document.execCommand('copy');else if(action==='cut')document.execCommand('cut');else if(action==='paste')document.execCommand('paste');else if(action==='clear')clearFormatting();}
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
    document.addEventListener('pointerdown', closeColorMenus);
});
onBeforeUnmount(() => { window.removeEventListener('selectionchange', onSelectionChange); document.removeEventListener('pointerdown', closeColorMenus); document.removeEventListener('pointermove', onEditorPointerMove); document.removeEventListener('pointerup', onEditorPointerUp); document.removeEventListener('pointermove',onCellDragMove);document.removeEventListener('pointerup',onCellDragEnd); });
</script>

<template>
    <div ref="editorShell" class="overflow-hidden rounded-xl border border-slate-300 bg-white focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
        <div class="flex flex-wrap items-center gap-1 border-b border-slate-200 bg-slate-50 px-2 py-2" role="tablist" aria-label="ابزارهای ویرایش متن">
            <button v-for="tab in tabs" :key="tab.id" type="button" role="tab" :aria-selected="activeTab === tab.id" class="rounded-lg px-3 py-2 text-xs font-semibold" :class="activeTab === tab.id ? 'bg-indigo-600 text-white' : 'text-slate-700 hover:bg-slate-200'" @click="activeTab = tab.id">{{ tab.label }}</button>
        </div>

        <div v-if="activeTab === 'home'" class="rte-toolbar flex flex-wrap items-center gap-1.5 border-b border-slate-200 bg-white p-2.5">
            <select class="rte-font-family h-9 rounded-lg border-slate-300 text-xs" :value="fontFamily" aria-label="نوع قلم" @change="applyFontFamily(($event.target as HTMLSelectElement).value)">
                <option value="Vazirmatn">وزیرمتن</option><option value="Arial">Arial</option><option value="Tahoma">Tahoma</option><option value="B Nazanin">B Nazanin</option><option value="B Mitra">B Mitra</option><option value="IRANSans">IRANSans</option><option value="Times New Roman">Times New Roman</option>
            </select>
            <select class="rte-font-size h-9 rounded-lg border-slate-300 text-xs" :value="currentFontSize" aria-label="اندازه قلم متن انتخاب‌شده" @change="applyFontSize(($event.target as HTMLSelectElement).value)">
                <option value="" disabled>چند اندازه</option><option v-if="currentFontSize && !['10pt','11pt','12pt','14pt','16pt','18pt','24pt','32pt'].includes(currentFontSize)" :value="currentFontSize">{{ currentFontSize.replace('pt', '') }} (فعلی)</option><option value="10pt">۱۰ pt</option><option value="11pt">۱۱ pt</option><option value="12pt">۱۲ pt</option><option value="14pt">۱۴ pt</option><option value="16pt">۱۶ pt</option><option value="18pt">۱۸ pt</option><option value="24pt">۲۴ pt</option><option value="32pt">۳۲ pt</option>
            </select>
            <button type="button" class="rte-tool" title="پررنگ" aria-label="پررنگ" @click="applyFormat('strong')"><Bold :size="16" /></button>
            <button type="button" class="rte-tool" title="مورب" aria-label="مورب" @click="applyFormat('em')"><Italic :size="16" /></button>
            <button type="button" class="rte-tool" title="زیرخط" aria-label="زیرخط" @click="applyFormat('u')"><Underline :size="16" /></button>
            <button type="button" class="rte-tool" title="خط‌خورده" aria-label="خط‌خورده" @click="applyFormat('s')"><Strikethrough :size="16" /></button>
            <details class="rte-color-menu"><summary title="رنگ قلم"><span class="font-bold">A</span><span class="rte-color-preview" :style="{backgroundColor:displayedColor('text')}"/><Palette :size="14"/></summary><div class="rte-color-popover"><b>رنگ‌های قالب</b><div class="rte-theme-colors"><button v-for="color in themeColors" :key="'ft-'+color" type="button" :style="{backgroundColor:color}" @click.prevent="applyTextColor(color)"/></div><b>رنگ‌های استاندارد</b><div class="rte-swatches"><button v-for="color in colorPalette" :key="'fc-'+color" type="button" :style="{backgroundColor:color}" :title="color" @click.prevent="applyTextColor(color)"/></div><label class="rte-custom-color">رنگ دلخواه <input type="color" @input="applyTextColor(($event.target as HTMLInputElement).value)"/></label></div></details>
            <details class="rte-color-menu"><summary title="رنگ پس‌زمینه متن"><PaintBucket :size="15"/><span class="rte-color-preview" :style="{backgroundColor:displayedColor('highlight')}"/><Palette :size="14"/></summary><div class="rte-color-popover"><b>رنگ‌های قالب</b><div class="rte-theme-colors"><button v-for="color in themeColors" :key="'ht-'+color" type="button" :style="{backgroundColor:color}" @click.prevent="applyHighlight(color)"/></div><b>رنگ‌های استاندارد</b><div class="rte-swatches"><button v-for="color in colorPalette" :key="'hc-'+color" type="button" :style="{backgroundColor:color}" @click.prevent="applyHighlight(color)"/></div><label class="rte-custom-color">رنگ دلخواه <input type="color" @input="applyHighlight(($event.target as HTMLInputElement).value)"/></label><button type="button" class="rte-menu-action" @click.prevent="clearHighlight()">بدون رنگ</button></div></details>
            <span class="mx-1 h-6 w-px bg-slate-200"></span>
            <button type="button" class="rte-tool" title="راست‌چین" aria-label="راست‌چین" @click="align('right')"><AlignRight :size="16" /></button>
            <button type="button" class="rte-tool" title="وسط‌چین" aria-label="وسط‌چین" @click="align('center')"><AlignCenter :size="16" /></button>
            <button type="button" class="rte-tool" title="چپ‌چین" aria-label="چپ‌چین" @click="align('left')"><AlignLeft :size="16" /></button>
            <button type="button" class="rte-tool" title="تراز دوطرفه" aria-label="تراز دوطرفه" @click="align('justify')"><AlignJustify :size="16" /></button>
            <label class="text-xs">فاصله خطوط (ضریب) <input class="rte-number" type="number" min="1" max="3" step="0.05" value="1.9" aria-label="ضریب فاصله خطوط" @change="setLineHeight(($event.target as HTMLInputElement).value)" /></label>
            <label class="text-xs">فاصله بعد از پاراگراف (px) <input class="rte-number" type="number" min="0" max="60" step="1" value="12" aria-label="فاصله بعد از پاراگراف بر حسب پیکسل" @change="setParagraphSpacing(($event.target as HTMLInputElement).value)" /></label>
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
            <p class="w-full text-xs text-slate-500">تصویر به‌صورت امن داخل محتوای نامه ذخیره می‌شود؛ فرمت PNG، JPG یا WEBP و حداکثر ۳۵۰ کیلوبایت.</p>
        </div>

        <div v-else-if="activeTab === 'table'" class="rte-toolbar flex flex-wrap items-center gap-2 border-b border-slate-200 bg-white p-3">
            <button type="button" class="rte-tool" title="افزودن ردیف" @click="addTableRow"><Rows3 :size="16"/><span>+</span></button>
            <button type="button" class="rte-tool" title="افزودن ستون" @click="addTableColumn"><Columns3 :size="16"/><span>+</span></button>
            <button type="button" class="rte-tool" title="حذف ردیف" @click="deleteTableRow"><Rows3 :size="16"/><Minus :size="12"/></button>
            <button type="button" class="rte-tool" title="حذف ستون" @click="deleteTableColumn"><Columns3 :size="16"/><Minus :size="12"/></button>
            <button type="button" class="rte-tool" title="انتخاب جدول" @click="selectTablePart('table')"><Table2 :size="16"/></button>
            <button type="button" class="rte-tool" title="انتخاب ردیف" @click="selectTablePart('row')"><Rows3 :size="16"/></button>
            <button type="button" class="rte-tool" title="انتخاب ستون" @click="selectTablePart('column')"><Columns3 :size="16"/></button>
            <button type="button" class="rte-tool" title="ادغام سلول‌ها" @click="mergeSelectedCells"><Maximize2 :size="16"/></button>
            <button type="button" class="rte-tool" title="تقسیم سلول" @click="splitSelectedCell"><TableProperties :size="16"/></button>
            <button type="button" class="rte-tool text-rose-700" @click="deleteTable"><Trash2 :size="16" /> حذف جدول</button>
            <label class="text-xs">عرض جدول <input class="rte-number" type="number" min="10" max="210" :value="dimensionMm(selectedTable(),'width')" @change="setTableDimension('width',($event.target as HTMLInputElement).value,'mm')" /> mm</label>
            <label class="text-xs">ارتفاع جدول <input class="rte-number" type="number" min="1" :value="dimensionMm(selectedTable(),'height')" @change="setTableDimension('height',($event.target as HTMLInputElement).value,'mm')" /> mm</label>
            <button type="button" class="rte-tool" title="تراز جدول به راست" @click="setTableAlignment('right')"><AlignRight :size="16"/></button><button type="button" class="rte-tool" title="وسط‌چین کردن جدول" @click="setTableAlignment('center')"><AlignCenter :size="16"/></button><button type="button" class="rte-tool" title="تراز جدول به چپ" @click="setTableAlignment('left')"><AlignLeft :size="16"/></button>
            <label class="text-xs">عرض ستون/ارتفاع ردیف <input class="rte-number" type="number" min="1"  :value="dimensionMm(selectedCell(),'width')" @change="setCellDimension('width',($event.target as HTMLInputElement).value,'mm')" /> mm</label>
            <label class="text-xs">ارتفاع ردیف <input class="rte-number" type="number" min="1"  :value="dimensionMm(selectedCell(),'height')" @change="setCellDimension('height',($event.target as HTMLInputElement).value,'mm')" /> mm</label>
            <label class="text-xs">حاشیه داخلی <input class="rte-number" type="number" min="0" value="2" @change="setCellPadding(($event.target as HTMLInputElement).value,'mm')" /> mm</label>
            <label class="text-xs">اعمال حاشیه
                <select v-model="borderTarget" class="rte-select"><option value="all">همه اضلاع</option><option value="outer">کادر بیرونی</option><option value="inner">خطوط داخلی</option><option value="top">بالا</option><option value="bottom">پایین</option><option value="left">چپ</option><option value="right">راست</option></select>
            </label>
            <details class="rte-color-menu"><summary title="رنگ خطوط جدول"><Grid3X3 :size="15"/><span class="rte-color-preview" :style="{backgroundColor:displayedColor('cell')}"/><Palette :size="14"/></summary><div class="rte-color-popover"><b>رنگ‌های قالب و استاندارد</b><div class="rte-swatches"><button v-for="color in [...themeColors,...colorPalette]" :key="'tb-'+color" type="button" :style="{backgroundColor:color}" @click.prevent="setCellBorderColor(color)"/></div><label class="rte-custom-color">رنگ دلخواه <input type="color" @input="setCellBorderColor(($event.target as HTMLInputElement).value)"/></label><button type="button" class="rte-menu-action" @click.prevent="setCellBorderColor('transparent')">بدون رنگ</button></div></details>
            <label class="text-xs">ضخامت خط <input class="rte-number" type="number" min="0" max="10" step="0.25" value="0.25" @change="setCellBorderWidth(($event.target as HTMLInputElement).value,'mm')" /> mm</label>
            <label class="text-xs">نوع خط <select class="rte-select" @change="setCellBorderStyle(($event.target as HTMLSelectElement).value)"><option value="solid">پیوسته</option><option value="dashed">خط‌چین</option><option value="dotted">نقطه‌چین</option><option value="double">دوتایی</option><option value="none">بدون خط</option></select></label>
            <details class="rte-color-menu"><summary title="رنگ پس‌زمینه سلول"><PaintBucket :size="15"/><span class="rte-color-preview" :style="{backgroundColor:displayedColor('cell-fill')}"/><Palette :size="14"/></summary><div class="rte-color-popover"><b>رنگ‌های قالب</b><div class="rte-theme-colors"><button v-for="color in themeColors" :key="'cbt-'+color" type="button" :style="{backgroundColor:color}" @click.prevent="setCellBackground(color)"/></div><b>رنگ‌های استاندارد</b><div class="rte-swatches"><button v-for="color in colorPalette" :key="'cbc-'+color" type="button" :style="{backgroundColor:color}" @click.prevent="setCellBackground(color)"/></div><label class="rte-custom-color">رنگ دلخواه <input type="color" @input="setCellBackground(($event.target as HTMLInputElement).value)"/></label><button type="button" class="rte-menu-action" @click.prevent="setCellBackground('transparent')">بدون رنگ</button></div></details>
            <label class="text-xs">تراز افقی <select class="rte-select" @change="setCellAlignment(($event.target as HTMLSelectElement).value)"><option value="right">راست</option><option value="center">وسط</option><option value="left">چپ</option><option value="justify">دوطرفه</option></select></label>
            <label class="text-xs">تراز عمودی <select class="rte-select" @change="setCellVerticalAlignment(($event.target as HTMLSelectElement).value)"><option value="top">بالا</option><option value="middle">وسط</option><option value="bottom">پایین</option></select></label>
        </div>
        <div v-else-if="activeTab === 'picture'" class="rte-toolbar flex flex-wrap items-center gap-2 border-b border-slate-200 bg-white p-3">
            <label class="text-xs">عرض <input class="rte-number" type="number" min="1"  :value="dimensionMm(currentImage,'width')" @change="setImageDimension('width',($event.target as HTMLInputElement).value,'mm')" /> mm</label>
            <label class="text-xs">ارتفاع <input class="rte-number" type="number" min="1"  :value="dimensionMm(currentImage,'height')" @change="setImageDimension('height',($event.target as HTMLInputElement).value,'mm')" /> mm</label>
            <label class="flex items-center gap-1 text-xs"><input type="checkbox" v-model="keepImageRatio" /> حفظ تناسب ابعاد</label>
            <label class="text-xs">جایگاه <select class="rte-select" @change="setImageAlignment(($event.target as HTMLSelectElement).value)"><option value="right">راست</option><option value="center">وسط</option><option value="left">چپ</option></select></label>
            <label class="text-xs">قرارگیری نسبت به متن <select class="rte-select" @change="setImageWrap(($event.target as HTMLSelectElement).value as 'inline'|'block'|'left'|'right')"><option value="inline">همراه متن</option><option value="block">بالا و پایین متن</option><option value="right">شناور راست</option><option value="left">شناور چپ</option></select></label>
            <details class="rte-color-menu"><summary title="رنگ کادر تصویر"><Palette :size="15"/><span class="rte-color-preview" :style="{backgroundColor:displayedColor('image')}"/></summary><div class="rte-color-popover"><div class="rte-swatches"><button v-for="color in [...themeColors,...colorPalette]" :key="'im-'+color" type="button" :style="{backgroundColor:color}" @click.prevent="setImageBorder('1px solid '+color)"/></div><label class="rte-custom-color">رنگ دلخواه <input type="color" @input="setImageBorder('1px solid '+($event.target as HTMLInputElement).value)"/></label><button class="rte-menu-action" type="button" @click.prevent="setImageBorder('none')">بدون کادر</button></div></details>
            <button type="button" class="rte-tool" title="چرخش پادساعتگرد" @click="setRotation(currentImage,-90)"><RotateCw :size="16"/></button><button type="button" class="rte-tool" title="قرینه افقی" @click="toggleFlip(currentImage,'x')"><FlipHorizontal :size="16"/></button><button type="button" class="rte-tool" title="قرینه عمودی" @click="toggleFlip(currentImage,'y')"><FlipVertical :size="16"/></button>
            <button type="button" class="rte-tool" @click="chooseImage"><Image :size="16" /> جایگزینی</button>
            <button type="button" class="rte-tool text-rose-700" @click="removeImage"><Trash2 :size="16" /> حذف</button>
        </div>
        <div v-else-if="activeTab === 'shape'" class="rte-toolbar flex flex-wrap items-center gap-2 border-b border-slate-200 bg-white p-3">
            <details class="rte-color-menu"><summary title="رنگ زمینه شکل"><PaintBucket :size="15"/><span class="rte-color-preview" :style="{backgroundColor:displayedColor('shape-fill')}"/><Palette :size="14"/></summary><div class="rte-color-popover"><div class="rte-swatches"><button v-for="color in [...themeColors,...colorPalette]" :key="'sf-'+color" type="button" :style="{backgroundColor:color}" @click.prevent="setShapeColor(color)"/></div><label class="rte-custom-color">رنگ دلخواه <input type="color" @input="setShapeColor(($event.target as HTMLInputElement).value)"/></label><button class="rte-menu-action" type="button" @click.prevent="setShapeColor('transparent')">بدون رنگ</button></div></details>
            <details class="rte-color-menu"><summary title="رنگ خط شکل"><RectangleHorizontal :size="15"/><span class="rte-color-preview" :style="{backgroundColor:displayedColor('shape-line')}"/><Palette :size="14"/></summary><div class="rte-color-popover"><div class="rte-swatches"><button v-for="color in [...themeColors,...colorPalette]" :key="'sl-'+color" type="button" :style="{backgroundColor:color}" @click.prevent="setShapeBorderColor(color)"/></div><label class="rte-custom-color">رنگ دلخواه <input type="color" @input="setShapeBorderColor(($event.target as HTMLInputElement).value)"/></label><button type="button" class="rte-menu-action" @click.prevent="setShapeBorderColor('transparent')">بدون رنگ</button></div></details>
            <label class="text-xs">عرض <input class="rte-number" type="number" min="1"  :value="dimensionMm(currentShape,'width')" @change="setShapeDimension('width',($event.target as HTMLInputElement).value,'mm')" /> mm</label>
            <label class="text-xs">ارتفاع <input class="rte-number" type="number" min="1"  :value="dimensionMm(currentShape,'height')" @change="setShapeDimension('height',($event.target as HTMLInputElement).value,'mm')" /> mm</label>
            <label class="text-xs">ضخامت خط <input class="rte-number" type="number" min="0" step="0.25" value="0.5" @change="setShapeBorderWidth(($event.target as HTMLInputElement).value+'mm')" /> mm</label>
            <button type="button" class="rte-tool" title="چرخش پادساعتگرد" @click="setRotation(currentShape,-90)"><RotateCw :size="16"/></button><button type="button" class="rte-tool" title="قرینه افقی" @click="toggleFlip(currentShape,'x')"><FlipHorizontal :size="16"/></button><button type="button" class="rte-tool" title="قرینه عمودی" @click="toggleFlip(currentShape,'y')"><FlipVertical :size="16"/></button>
            <button type="button" class="rte-tool text-rose-700" @click="removeShape"><Trash2 :size="16" /> حذف شکل</button>
        </div>
        <div v-if="contextMenu" class="rte-context-menu" :style="{left:contextMenu.x+'px',top:contextMenu.y+'px'}" @pointerdown.prevent>
            <template v-if="contextMenu.kind==='picture'"><button @click="onEditorContextAction('rotate-left')"><RotateCw :size="15"/> چرخش ۹۰ درجه</button><button @click="onEditorContextAction('delete-image')"><Trash2 :size="15"/> حذف تصویر</button></template>
            <template v-else-if="contextMenu.kind==='shape'"><button @click="onEditorContextAction('rotate-right')"><RotateCw :size="15"/> چرخش ۹۰ درجه</button><button @click="onEditorContextAction('delete-shape')"><Trash2 :size="15"/> حذف شکل</button></template>
            <template v-else-if="contextMenu.kind==='table'"><button @click="onEditorContextAction('select-table')"><TableProperties :size="15"/> انتخاب جدول</button><button @click="onEditorContextAction('copy')"><Copy :size="15"/> کپی</button><button @click="onEditorContextAction('cut')"><Scissors :size="15"/> برش</button></template>
            <template v-else><button @click="onEditorContextAction('copy')"><Copy :size="15"/> کپی</button><button @click="onEditorContextAction('cut')"><Scissors :size="15"/> برش</button><button @click="onEditorContextAction('paste')"><Clipboard :size="15"/> چسباندن</button><button @click="onEditorContextAction('clear')"><Eraser :size="15"/> پاک‌کردن قالب‌بندی</button></template>
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
            @contextmenu="onEditorContextMenu"
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
.rte-select{height:32px;margin:0 4px;padding:3px 28px 3px 8px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;min-width:104px;max-width:180px;width:auto;box-sizing:border-box;direction:rtl;text-align:right;appearance:none;-webkit-appearance:none;-moz-appearance:none;background-color:#fff;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23475569' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:left 9px center;background-size:12px 12px}
.rte-toolbar select:not([multiple]){direction:rtl;text-align:right;appearance:none;-webkit-appearance:none;-moz-appearance:none;padding-left:28px;padding-right:9px;background-color:#fff;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23475569' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:left 9px center;background-size:12px 12px;box-sizing:border-box;max-width:100%}
.rte-toolbar select option{direction:rtl;text-align:right}
.rte-font-family{min-width:132px;width:150px;max-width:170px}
.rte-font-size{min-width:88px;width:88px}
.rte-content :deep(.rte-cell-selected){outline:2px solid #2563eb;outline-offset:-2px;background-image:linear-gradient(#dbeafe66,#dbeafe66)}
.rte-content{min-height:18rem;line-height:1.9;direction:rtl;text-align:right;overflow-wrap:anywhere}
.rte-content:empty:before{content:attr(data-placeholder);color:#94a3b8;pointer-events:none}
.rte-content :deep(table){width:100%;border-collapse:collapse;margin:8px 0}
.rte-content :deep(td),.rte-content :deep(th){border:1px solid #94a3b8;padding:6px;min-width:35px;position:relative;cursor:cell}
.rte-content :deep(td:hover),.rte-content :deep(th:hover){outline:1px dashed #94a3b8;outline-offset:-1px}
.rte-content :deep(td),.rte-content :deep(th){user-select:text}
.rte-content :deep(img){max-width:100%;height:auto;vertical-align:middle}
.rte-content :deep([data-editor-shape]){margin:3px;min-width:12px;min-height:2px;cursor:move;outline:1px dashed transparent}.rte-content :deep(img){cursor:pointer}.rte-content :deep(img:focus),.rte-content :deep(img.rte-selected),.rte-content :deep([data-editor-shape].rte-selected){outline:2px solid #2563eb;outline-offset:3px;box-shadow:0 0 0 4px #2563eb22}.rte-color-menu{position:relative;display:inline-flex}.rte-color-menu>summary{list-style:none;display:flex;align-items:center;gap:5px;min-height:34px;padding:5px 8px;border:1px solid #cbd5e1;border-radius:7px;cursor:pointer}.rte-color-menu>summary::-webkit-details-marker{display:none}.rte-color-preview{display:inline-block;width:17px;height:5px;border:1px solid #64748b;border-radius:2px}.rte-color-popover{position:absolute;z-index:50;top:calc(100% + 5px);right:0;width:245px;padding:10px;background:white;border:1px solid #cbd5e1;border-radius:9px;box-shadow:0 8px 24px #0f172a22;display:grid;gap:8px}.rte-theme-colors,.rte-swatches{display:grid;grid-template-columns:repeat(10,minmax(0,1fr));gap:4px}.rte-theme-colors button,.rte-swatches button{width:18px;height:18px;border:1px solid #94a3b8;border-radius:3px;cursor:pointer}.rte-custom-color{display:flex;align-items:center;justify-content:space-between;font-size:12px}.rte-menu-action{border:1px solid #cbd5e1;border-radius:5px;padding:5px;text-align:right;font-size:12px}.rte-context-menu{position:fixed;z-index:1000;min-width:190px;padding:5px;background:white;border:1px solid #cbd5e1;border-radius:8px;box-shadow:0 8px 28px #0f172a30;display:grid;gap:2px}.rte-context-menu button{display:flex;align-items:center;gap:8px;padding:7px 9px;border-radius:5px;text-align:right;font-size:12px}.rte-context-menu button:hover{background:#eff6ff}
</style>
