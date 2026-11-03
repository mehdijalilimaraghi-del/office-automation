<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        align?: 'left' | 'right';
        width?: '48';
        contentClasses?: string;
    }>(),
    {
        align: 'right',
        width: '48',
        contentClasses: 'p-1.5 bg-white',
    },
);

const rootRef = ref<HTMLDivElement | null>(null);
const open = ref(false);

const closeOnEscape = (event: KeyboardEvent): void => {
    if (event.key === 'Escape' && open.value) open.value = false;
};

const closeOnOutsidePointer = (event: PointerEvent): void => {
    const target = event.target;
    if (target instanceof Node && !rootRef.value?.contains(target)) open.value = false;
};

onMounted(() => {
    document.addEventListener('pointerdown', closeOnOutsidePointer);
    document.addEventListener('keydown', closeOnEscape);
});

onUnmounted(() => {
    document.removeEventListener('pointerdown', closeOnOutsidePointer);
    document.removeEventListener('keydown', closeOnEscape);
});

const widthClass = computed(() => ({ 48: 'w-48' }[props.width.toString()]));

const alignmentClasses = computed(() => (
    props.align === 'left'
        ? 'ltr:origin-top-left rtl:origin-top-right start-0'
        : 'ltr:origin-top-right rtl:origin-top-left end-0'
));
</script>

<template>
    <div ref="rootRef" class="relative">
        <div @click="open = !open">
            <slot name="trigger" />
        </div>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="translate-y-1 scale-[0.98] opacity-0"
            enter-to-class="translate-y-0 scale-100 opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="translate-y-0 scale-100 opacity-100"
            leave-to-class="translate-y-1 scale-[0.98] opacity-0"
        >
            <div
                v-if="open"
                class="absolute z-[60] mt-2 overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-[0_18px_48px_rgba(15,23,42,0.16)] ring-1 ring-slate-900/5"
                :class="[widthClass, alignmentClasses]"
            >
                <div class="rounded-2xl" :class="contentClasses">
                    <slot name="content" />
                </div>
            </div>
        </Transition>
    </div>
</template>
