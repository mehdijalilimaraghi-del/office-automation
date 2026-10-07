<script setup lang="ts">
import '@jalali-js/vue/date-picker.css';
import { DatePicker } from '@jalali-js/vue';
import { computed, ref, watch } from 'vue';

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

const isOpen = ref(false);
const draftDate = ref<string | null>(null);
const draftHour = ref('09');
const draftMinute = ref('00');

const hours = Array.from({ length: 24 }, (_, index) => String(index).padStart(2, '0'));
const minutes = Array.from({ length: 60 }, (_, index) => String(index).padStart(2, '0'));

const selectedDateLabel = computed(() => {
    if (!draftDate.value) {
        return 'هنوز انتخاب نشده';
    }

    const [year, month, day] = draftDate.value.slice(0, 10).split('-').map(Number);

    if (!year || !month || !day) {
        return 'هنوز انتخاب نشده';
    }

    return new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    }).format(new Date(year, month - 1, day));
});

const displayValue = computed(() => {
    if (!props.modelValue) {
        return '';
    }

    const normalized = props.modelValue.replace('T', ' ');
    const [datePart, timePart = '00:00'] = normalized.split(' ');
    const [year, month, day] = datePart.slice(0, 10).split('-').map(Number);
    const [hour = 0, minute = 0] = timePart.slice(0, 5).split(':').map(Number);

    if (!year || !month || !day) {
        return '';
    }

    const dateLabel = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date(year, month - 1, day));

    return `${dateLabel} — ${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
});

const dateValue = computed<string | undefined>({
    get: () => draftDate.value || undefined,
    set: (nextValue) => {
        draftDate.value = nextValue ? nextValue.slice(0, 10) : null;
    },
});

const open = (): void => {
    const current = props.modelValue?.replace('T', ' ') ?? '';
    const [datePart, timePart = '09:00'] = current.split(' ');

    draftDate.value = datePart?.slice(0, 10) || null;
    draftHour.value = timePart.slice(0, 2).padStart(2, '0');
    draftMinute.value = timePart.slice(3, 5).padStart(2, '0');
    isOpen.value = true;
};

const confirm = (): void => {
    if (!draftDate.value) {
        return;
    }

    emit('update:modelValue', `${draftDate.value}T${draftHour.value}:${draftMinute.value}:00`);
    isOpen.value = false;
};

const cancel = (): void => {
    isOpen.value = false;
};

watch(() => props.modelValue, (value) => {
    if (!value && !isOpen.value) {
        draftDate.value = null;
        draftHour.value = '09';
        draftMinute.value = '00';
    }
});
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="flex min-h-11 w-full items-center justify-between gap-3 rounded-lg border bg-background px-3 text-right text-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-200"
            @click="open"
        >
            <span :class="displayValue ? 'text-gray-900' : 'text-muted-foreground'">
                {{ displayValue || placeholder }}
            </span>
            <span class="shrink-0 text-xs text-muted-foreground">📅</span>
        </button>

        <div v-if="isOpen" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/30 p-4" @click.self="cancel">
            <section class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b px-5 py-4">
                    <div>
                        <h4 class="font-semibold text-gray-900">انتخاب تاریخ و ساعت</h4>
                        <p class="mt-1 text-xs text-muted-foreground">ابتدا تاریخ، سپس ساعت و دقیقه را انتخاب کنید.</p>
                    </div>
                    <button type="button" class="text-xl text-gray-500 hover:text-gray-900" @click="cancel">×</button>
                </div>

                <div class="space-y-5 p-5">
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <label class="text-sm font-medium text-gray-700">۱. تاریخ</label>
                            <span class="text-xs text-muted-foreground">{{ selectedDateLabel }}</span>
                        </div>
                        <DatePicker
                            v-model="dateValue"
                            system="jalali"
                            locale="fa"
                            value-format="gregorian-iso"
                            :placeholder="placeholder"
                            class="jalali-date-time-calendar w-full"
                        />
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2" dir="ltr">
                        <div dir="rtl">
                            <label class="mb-2 block text-sm font-medium text-gray-700">۲. ساعت</label>
                            <select v-model="draftHour" :disabled="!draftDate" class="h-11 w-full rounded-lg border bg-background px-3 text-right text-sm disabled:cursor-not-allowed disabled:bg-gray-100">
                                <option v-for="hour in hours" :key="hour" :value="hour">{{ hour }}</option>
                            </select>
                        </div>
                        <div dir="rtl">
                            <label class="mb-2 block text-sm font-medium text-gray-700">۳. دقیقه</label>
                            <select v-model="draftMinute" :disabled="!draftDate" class="h-11 w-full rounded-lg border bg-background px-3 text-right text-sm disabled:cursor-not-allowed disabled:bg-gray-100">
                                <option v-for="minute in minutes" :key="minute" :value="minute">{{ minute }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-700">
                        <span class="font-medium">زمان انتخاب‌شده:</span>
                        {{ draftDate ? `${selectedDateLabel} — ${draftHour}:${draftMinute}` : 'ابتدا تاریخ را انتخاب کنید.' }}
                    </div>

                    <div class="flex justify-end gap-2 border-t pt-4">
                        <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="cancel">انصراف</button>
                        <button type="button" :disabled="!draftDate" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-40" @click="confirm">تأیید تاریخ و ساعت</button>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

<style>
.jalali-date-time-calendar,
.jalali-date-time-calendar * {
    font-family: 'Vazirmatn', Tahoma, Arial, sans-serif !important;
}
</style>
