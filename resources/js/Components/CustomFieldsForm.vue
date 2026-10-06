<script setup lang="ts">
import JalaliDatePicker from '@/Components/JalaliDatePicker.vue';

type CustomValue = string | number | boolean | null;

interface CustomField {
    id: number;
    key: string;
    label: string;
    type: 'text' | 'textarea' | 'number' | 'date' | 'boolean' | 'select';
    is_required: boolean;
    options: string[] | null;
}

const props = withDefaults(defineProps<{
    fields: CustomField[];
    modelValue: Record<string, CustomValue>;
    errors?: Record<string, string>;
    title?: string;
    description?: string;
}>(), {
    errors: () => ({}),
    title: 'اطلاعات تکمیلی',
    description: 'اطلاعات اختصاصی تعریف‌شده توسط مدیر سامانه، در صورت نیاز در این بخش ثبت می‌شوند.',
});

const emit = defineEmits<{
    'update:modelValue': [value: Record<string, CustomValue>];
}>();

const valueOf = (key: string): CustomValue => props.modelValue[key] ?? null;

const setValue = (key: string, value: CustomValue): void => {
    emit('update:modelValue', {
        ...props.modelValue,
        [key]: value,
    });
};

const inputError = (key: string): string | undefined =>
    props.errors['custom_fields.' + key] ?? props.errors[key];

const onTextInput = (key: string, event: Event): void => {
    setValue(key, (event.target as HTMLInputElement).value);
};

const onNumberInput = (key: string, event: Event): void => {
    const raw = (event.target as HTMLInputElement).value;
    setValue(key, raw === '' ? null : Number(raw));
};

const onCheckboxChange = (key: string, event: Event): void => {
    setValue(key, (event.target as HTMLInputElement).checked);
};

const onSelectChange = (key: string, event: Event): void => {
    setValue(key, (event.target as HTMLSelectElement).value || null);
};
</script>

<template>
    <section
        v-if="props.fields.length"
        class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 sm:p-5"
        dir="rtl"
    >
        <div class="mb-5 flex items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-700">
                <span class="text-sm font-black">+</span>
            </div>
            <div>
                <h3 class="text-sm font-bold text-slate-900">
                    {{ props.title }}
                </h3>
                <p class="mt-1 text-xs leading-5 text-slate-500">
                    {{ props.description }}
                </p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div
                v-for="field in props.fields"
                :key="field.id"
                :class="field.type === 'textarea' ? 'md:col-span-2' : ''"
            >
                <label class="mb-1.5 block text-sm font-semibold text-slate-700">
                    {{ field.label }}
                    <span v-if="field.is_required" class="text-red-500"> *</span>
                </label>


                <textarea
                    v-if="field.type === 'textarea'"
                    :value="String(valueOf(field.key) ?? '')"
                    rows="3"
                    class="w-full rounded-xl border-slate-200 bg-white text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500"
                    @input="onTextInput(field.key, $event)"
                />

                <select
                    v-else-if="field.type === 'select'"
                    :value="valueOf(field.key)"
                    class="w-full rounded-xl border-slate-200 bg-white text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500"
                    @change="onSelectChange(field.key, $event)"
                >
                    <option value="">انتخاب کنید</option>
                    <option
                        v-for="option in field.options ?? []"
                        :key="option"
                        :value="option"
                    >
                        {{ option }}
                    </option>
                </select>

                <div
                    v-else-if="field.type === 'boolean'"
                    class="flex min-h-11 items-center rounded-xl border border-slate-200 bg-white px-3"
                >
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                        <input
                            :checked="Boolean(valueOf(field.key))"
                            type="checkbox"
                            class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                            @change="onCheckboxChange(field.key, $event)"
                        />
                        بله
                    </label>
                </div>

                <JalaliDatePicker
                    v-else-if="field.type === 'date'"
                    :model-value="String(valueOf(field.key) ?? '')"
                    @update:model-value="setValue(field.key, $event)"
                />

                <input
                    v-else-if="field.type === 'number'"
                    :value="valueOf(field.key) ?? ''"
                    type="number"
                    class="w-full rounded-xl border-slate-200 bg-white text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500"
                    @input="onNumberInput(field.key, $event)"
                />

                <input
                    v-else
                    :value="String(valueOf(field.key) ?? '')"
                    type="text"
                    class="w-full rounded-xl border-slate-200 bg-white text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500"
                    @input="onTextInput(field.key, $event)"
                />

                <p
                    v-if="inputError(field.key)"
                    class="mt-1.5 text-xs text-red-600"
                >
                    {{ inputError(field.key) }}
                </p>
            </div>
        </div>
    </section>
</template>
