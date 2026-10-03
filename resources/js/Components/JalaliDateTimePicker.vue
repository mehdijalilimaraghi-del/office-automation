<script setup lang="ts">
import '@jalali-js/vue/date-picker.css';
import { DatePicker } from '@jalali-js/vue';
import { fromGregorian } from 'jalali-js';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: string | null;
        placeholder?: string;
    }>(),
    {
        placeholder: 'انتخاب تاریخ و زمان',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string | null];
}>();

const defaultDate = computed(() => {
    if (!props.modelValue) {
        return null;
    }

    const normalized = props.modelValue.replace('T', ' ');
    const [datePart, timePart = '00:00'] = normalized.split(' ');
    const match = datePart.slice(0, 10).match(/^(\d{4})-(\d{2})-(\d{2})$/);

    if (!match) {
        return null;
    }

    const [hour = 0, minute = 0] = timePart.slice(0, 5).split(':').map(Number);
    const gregorian = {
        year: Number(match[1]),
        month: Number(match[2]),
        day: Number(match[3]),
    };
    const jalali = fromGregorian(gregorian, 'jalali');

    return {
        precision: 'datetime' as const,
        system: 'jalali' as const,
        ...jalali,
        hour: Number.isFinite(hour) ? hour : 0,
        minute: Number.isFinite(minute) ? minute : 0,
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
        precision="datetime"
        value-format="gregorian-iso"
        :default-date="defaultDate"
        :placeholder="placeholder"
        class="jalali-date-time-picker"
    />
</template>

<style>
.jalali-date-time-picker,
.jalali-date-time-picker * {
    font-family: 'Vazirmatn', Tahoma, Arial, sans-serif !important;
}
</style>
