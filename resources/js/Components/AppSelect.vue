<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useAttrs, useId, watch, type VNode } from 'vue';

defineOptions({ inheritAttrs: false });

type SelectOption = {
    key: string | number;
    value: unknown;
    label: string;
    disabled: boolean;
    selected: boolean;
};

const props = defineProps<{
    modelValue?: unknown;
    value?: unknown;
    placeholder?: string;
}>();

const emit = defineEmits<{
    (event: 'update:modelValue', value: unknown): void;
    (event: 'change', value: { target: { value: string } }): void;
}>();

const attrs = useAttrs();
const slots = defineSlots<{ default?: () => unknown[] }>();
const triggerRef = ref<HTMLButtonElement | null>(null);
const panelRef = ref<HTMLDivElement | null>(null);
const searchRef = ref<HTMLInputElement | null>(null);
const isOpen = ref(false);
const internalValue = ref<unknown>(undefined);
const searchQuery = ref('');
const activeIndex = ref(0);
const panelStyle = ref<Record<string, string>>({});
const listboxId = `app-select-${useId()}`;

function textFromChildren(children: unknown): string {
    if (typeof children === 'string' || typeof children === 'number') return String(children);
    if (Array.isArray(children)) return children.map(textFromChildren).join('');
    if (children && typeof children === 'object' && 'children' in children) {
        return textFromChildren((children as VNode).children);
    }
    return '';
}

function collectOptions(nodes: VNode[], result: SelectOption[] = []): SelectOption[] {
    for (const node of nodes) {
        if (typeof node.type === 'symbol' && Array.isArray(node.children)) {
            collectOptions(node.children as VNode[], result);
            continue;
        }

        if (node.type !== 'option') continue;

        const optionProps = node.props ?? {};
        const label = textFromChildren(node.children).trim();
        const hasValue = Object.prototype.hasOwnProperty.call(optionProps, 'value');
        const value = hasValue ? optionProps.value : label;

        result.push({
            key: (optionProps.key as string | number | undefined) ?? `${String(value)}-${result.length}`,
            value,
            label,
            disabled: Boolean(optionProps.disabled),
            selected: Boolean(optionProps.selected),
        });
    }

    return result;
}

const options = computed(() => collectOptions((slots.default?.() ?? []) as VNode[]));
const searchable = computed(() => options.value.length > 8);
const filteredOptions = computed(() => {
    const query = searchQuery.value.trim().toLocaleLowerCase();
    return query
        ? options.value.filter((option) => option.label.toLocaleLowerCase().includes(query))
        : options.value;
});

function sameValue(left: unknown, right: unknown): boolean {
    if (left === right) return true;
    if (left == null || right == null) return false;
    return String(left) === String(right);
}

const currentValue = computed(() => props.modelValue !== undefined ? props.modelValue : props.value !== undefined ? props.value : internalValue.value);

const selectedOption = computed(() => {
    const selected = options.value.find((option) => sameValue(option.value, currentValue.value));
    if (selected) return selected;
    return options.value.find((option) => option.selected)
        ?? (props.modelValue === undefined ? options.value[0] : undefined);
});

function isOptionSelected(option: SelectOption): boolean {
    if (currentValue.value !== undefined) return sameValue(option.value, currentValue.value);
    return selectedOption.value?.key === option.key;
}

const isDisabled = computed(() => Boolean(attrs.disabled));
const isRequired = computed(() => Boolean(attrs.required));
const triggerClasses = computed(() => [
    'app-select-trigger flex min-h-11 w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-right text-sm text-slate-700 shadow-sm transition hover:border-slate-300 focus:border-violet-400 focus:outline-none focus:ring-4 focus:ring-violet-100 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400',
    attrs.class,
]);

async function updatePosition(): Promise<void> {
    const trigger = triggerRef.value;
    if (!trigger) return;

    const rect = trigger.getBoundingClientRect();
    const viewportPadding = 8;
    const width = Math.min(Math.max(rect.width, 260), window.innerWidth - viewportPadding * 2);
    const availableBelow = Math.max(0, window.innerHeight - rect.bottom - viewportPadding);
    const availableAbove = Math.max(0, rect.top - viewportPadding);
    const left = Math.min(
        Math.max(viewportPadding, rect.right - width),
        window.innerWidth - width - viewportPadding,
    );

    // Render once with a safe viewport bound, then position using the panel's
    // measured content height rather than the available space as a proxy.
    panelStyle.value = {
        position: 'fixed',
        top: `${viewportPadding}px`,
        left: `${left}px`,
        width: `${width}px`,
        maxHeight: `${Math.max(80, Math.min(320, Math.max(availableAbove, availableBelow)))}px`,
        zIndex: '10000',
    };
    await nextTick();

    const panel = panelRef.value;
    if (!panel) return;

    const desiredHeight = Math.max(80, Math.min(320, panel.scrollHeight));
    const openAbove = availableBelow < desiredHeight && availableAbove > availableBelow;
    const available = openAbove ? availableAbove : availableBelow;
    const maxHeight = Math.max(80, Math.min(desiredHeight, available));
    const top = openAbove
        ? Math.max(viewportPadding, rect.top - maxHeight - 6)
        : Math.min(rect.bottom + 6, window.innerHeight - maxHeight - viewportPadding);

    panelStyle.value = {
        position: 'fixed',
        top: `${top}px`,
        left: `${left}px`,
        width: `${width}px`,
        maxHeight: `${maxHeight}px`,
        zIndex: '10000',
    };
}

async function openDropdown(): Promise<void> {
    if (isDisabled.value || isOpen.value) return;
    isOpen.value = true;
    searchQuery.value = '';
    activeIndex.value = Math.max(0, filteredOptions.value.findIndex((option) => !option.disabled));
    await nextTick();
    await updatePosition();
    if (searchable.value) searchRef.value?.focus();
}

function closeDropdown(restoreFocus = false): void {
    if (!isOpen.value) return;
    isOpen.value = false;
    searchQuery.value = '';
    if (restoreFocus) nextTick(() => triggerRef.value?.focus());
}

function chooseOption(option: SelectOption): void {
    if (option.disabled) return;
    if (props.modelValue === undefined && props.value === undefined) internalValue.value = option.value;
    emit('update:modelValue', option.value);
    emit('change', { target: { value: option.value == null ? '' : String(option.value) } });
    closeDropdown(true);
}

function moveActive(direction: number): void {
    const enabled = filteredOptions.value
        .map((option, index) => ({ option, index }))
        .filter(({ option }) => !option.disabled);
    if (!enabled.length) return;

    const current = enabled.findIndex(({ index }) => index === activeIndex.value);
    const next = (current + direction + enabled.length) % enabled.length;
    activeIndex.value = enabled[next].index;
}

function handleTriggerKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp' || event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        if (!isOpen.value) {
            void openDropdown();
        } else if (event.key === 'Enter' || event.key === ' ') {
            const option = filteredOptions.value[activeIndex.value];
            if (option) chooseOption(option);
        } else {
            moveActive(event.key === 'ArrowUp' ? -1 : 1);
        }
    } else if (event.key === 'Escape') {
        closeDropdown();
    }
}

function handlePanelKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        moveActive(event.key === 'ArrowDown' ? 1 : -1);
    } else if (event.key === 'Enter') {
        event.preventDefault();
        const option = filteredOptions.value[activeIndex.value];
        if (option) chooseOption(option);
    } else if (event.key === 'Escape') {
        event.preventDefault();
        closeDropdown(true);
    } else if (event.key === 'Tab') {
        closeDropdown();
    }
}

function handleOutsidePointer(event: PointerEvent): void {
    const target = event.target as Node | null;
    if (!target || triggerRef.value?.contains(target) || panelRef.value?.contains(target)) return;
    closeDropdown();
}

function handleViewportChange(): void {
    if (isOpen.value) void updatePosition();
}

watch(filteredOptions, (next) => {
    const firstEnabled = next.findIndex((option) => !option.disabled);
    activeIndex.value = firstEnabled < 0 ? 0 : firstEnabled;
});

onMounted(() => {
    document.addEventListener('pointerdown', handleOutsidePointer);
    window.addEventListener('resize', handleViewportChange);
    window.addEventListener('scroll', handleViewportChange, true);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', handleOutsidePointer);
    window.removeEventListener('resize', handleViewportChange);
    window.removeEventListener('scroll', handleViewportChange, true);
});
</script>

<template>
    <div class="app-select relative min-w-0 w-full" :data-required="isRequired ? '' : undefined">
        <button
            ref="triggerRef"
            v-bind="attrs"
            :class="triggerClasses"
            type="button"
            role="combobox"
            aria-haspopup="listbox"
            :aria-expanded="isOpen"
            :aria-controls="listboxId"
            :aria-required="isRequired ? 'true' : undefined"
            :disabled="isDisabled"
            @click="isOpen ? closeDropdown() : openDropdown()"
            @keydown="handleTriggerKeydown"
        >
            <span class="min-w-0 flex-1 truncate">{{ selectedOption?.label ?? props.placeholder ?? 'انتخاب کنید' }}</span>
            <svg
                class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200"
                :class="{ 'rotate-180 text-violet-600': isOpen }"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="m7 10 5 5 5-5" />
            </svg>
        </button>

        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="translate-y-1 opacity-0 scale-[0.98]"
                enter-to-class="translate-y-0 opacity-100 scale-100"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="translate-y-0 opacity-100 scale-100"
                leave-to-class="translate-y-1 opacity-0 scale-[0.98]"
            >
                <div
                    v-if="isOpen"
                    :id="listboxId"
                    ref="panelRef"
                    class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white p-1.5 shadow-[0_16px_48px_rgba(15,23,42,0.16)] ring-1 ring-slate-900/5"
                    :style="panelStyle"
                    role="listbox"
                    @keydown="handlePanelKeydown"
                >
                    <div v-if="searchable" class="border-b border-slate-100 p-1.5">
                        <input
                            ref="searchRef"
                            v-model="searchQuery"
                            type="search"
                            autocomplete="off"
                            placeholder="جستجوی گزینه..."
                            class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-right text-xs text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-violet-300 focus:bg-white focus:ring-2 focus:ring-violet-100"
                        />
                    </div>

                    <div class="max-h-[inherit] overflow-y-auto overscroll-contain py-1">
                        <button
                            v-for="(option, index) in filteredOptions"
                            :key="option.key"
                            type="button"
                            role="option"
                            :aria-selected="isOptionSelected(option)"
                            :disabled="option.disabled"
                            class="flex min-h-10 w-full items-center justify-between gap-3 rounded-xl px-3 py-2 text-right text-sm transition"
                            :class="[
                                option.disabled
                                    ? 'cursor-not-allowed text-slate-300'
                                    : index === activeIndex
                                      ? 'bg-violet-50 text-violet-800'
                                      : 'text-slate-700 hover:bg-slate-50',
                                isOptionSelected(option) ? 'font-semibold text-violet-700' : '',
                            ]"
                            @mouseenter="activeIndex = index"
                            @click="chooseOption(option)"
                        >
                            <span class="min-w-0 flex-1 break-words">{{ option.label }}</span>
                            <svg
                                v-if="isOptionSelected(option)"
                                class="h-4 w-4 shrink-0 text-violet-600"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="m5 12 4 4L19 6" />
                            </svg>
                        </button>

                        <p v-if="filteredOptions.length === 0" class="px-3 py-5 text-center text-xs text-slate-400">
                            گزینه‌ای پیدا نشد
                        </p>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
