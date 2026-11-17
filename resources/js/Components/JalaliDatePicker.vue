<script setup lang="ts">
import '@jalali-js/vue/date-picker.css';
import { DatePicker } from '@jalali-js/vue';
import { fromGregorian } from 'jalali-js';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: string | null;
        placeholder?: string;
        required?: boolean;
        disabled?: boolean;
    }>(),
    {
        placeholder: 'انتخاب تاریخ',
        required: false,
        disabled: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string | null];
}>();

const defaultDate = computed(() => {
    if (!props.modelValue) {
        return null;
    }

    const match = props.modelValue.slice(0, 10).match(/^(\d{4})-(\d{2})-(\d{2})$/);

    if (!match) {
        return null;
    }

    const gregorian = {
        year: Number(match[1]),
        month: Number(match[2]),
        day: Number(match[3]),
    };

    const jalali = fromGregorian(gregorian, 'jalali');

    return {
        precision: 'date' as const,
        system: 'jalali' as const,
        ...jalali,
    };
});

const pickerKey = computed(() => props.modelValue || 'empty');

const value = computed<string | undefined>({
    get: () => props.modelValue || undefined,
    set: (nextValue) => emit('update:modelValue', nextValue || null),
});
</script>

<template>
    <DatePicker
        :key="pickerKey"
        v-model="value"
        system="jalali"
        locale="fa"
        value-format="gregorian-iso"
        :default-date="defaultDate"
        :placeholder="placeholder"
        :required="required"
        :disabled="disabled"
        class="jalali-date-picker"
    />
</template>

<style>
.jalali-date-picker,
.jalali-date-picker * {
    font-family: 'Vazirmatn', Tahoma, Arial, sans-serif !important;
}
</style>
