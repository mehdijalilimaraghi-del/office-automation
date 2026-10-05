<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Field {
    id: number;
    entity_type: string;
    key: string;
    label: string;
    type: string;
    is_required: boolean;
    is_active: boolean;
    sort_order: number;
    options: string[] | null;
}

const props = defineProps<{
    fields: Field[];
    entityTypes: { value: string; label: string }[];
    types: string[];
}>();

const editing = ref<Field | null>(null);
const show = ref(false);

const form = useForm({
    entity_type: props.entityTypes[0]?.value ?? '',
    key: '',
    label: '',
    type: 'text',
    is_required: false,
    is_active: true,
    sort_order: 0,
    options: [] as string[],
});

const typeLabels: Record<string, string> = {
    text: 'متن کوتاه',
    textarea: 'متن چندخطی',
    number: 'عدد',
    date: 'تاریخ',
    boolean: 'بله/خیر',
    select: 'انتخابی',
};

function openCreate() {
    editing.value = null;
    form.reset();
    form.entity_type = props.entityTypes[0]?.value ?? '';
    form.type = 'text';
    form.is_active = true;
    form.options = [];
    form.clearErrors();
    show.value = true;
}

function openEdit(field: Field) {
    editing.value = field;

    Object.assign(form, {
        entity_type: field.entity_type,
        key: field.key,
        label: field.label,
        type: field.type,
        is_required: field.is_required,
        is_active: field.is_active,
        sort_order: field.sort_order,
        options: [...(field.options ?? [])],
    });

    form.clearErrors();
    show.value = true;
}

function close() {
    show.value = false;
    editing.value = null;
    form.reset();
    form.clearErrors();
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: close,
    };

    if (editing.value) {
        form.put(route('custom-fields.update', editing.value.id), options);
        return;
    }

    form.post(route('custom-fields.store'), options);
}

function remove(field: Field) {
    if (
        confirm(
            'آیا از حذف «' + field.label + '» مطمئن هستید؟',
        )
    ) {
        useForm({}).delete(
            route('custom-fields.destroy', field.id),
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <Head title="فیلدهای سفارشی" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-teal-600">مدیریت سازمان</p>
                    <h2 class="mt-1 text-2xl font-bold text-slate-900">
                        فیلدهای سفارشی کاربران
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        اطلاعات تکمیلی موردنیاز برای پروفایل کاربران سازمان را تعریف کنید.
                    </p>
                </div>

                <button
                    type="button"
                    class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white"
                    @click="openCreate"
                >
                    + فیلد جدید
                </button>
            </div>
        </template>

        <div class="min-h-[calc(100vh-8rem)] bg-slate-50 py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b p-5">
                        <h3 class="font-bold">فیلدهای تعریف‌شده</h3>
                        <p class="mt-1 text-xs text-slate-500">
                            این فیلدها در فرم ایجاد و ویرایش کاربر نمایش داده می‌شوند.
                        </p>
                    </div>

                    <div
                        v-if="!props.fields.length"
                        class="p-16 text-center text-sm text-slate-500"
                    >
                        هنوز فیلدی تعریف نشده است.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[800px] text-right">
                            <thead class="bg-slate-50 text-xs text-slate-500">
                                <tr>
                                    <th class="p-4">عنوان</th>
                                    <th class="p-4">کلید</th>
                                    <th class="p-4">موجودیت</th>
                                    <th class="p-4">نوع</th>
                                    <th class="p-4">وضعیت</th>
                                    <th class="p-4">عملیات</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y">
                                <tr v-for="field in props.fields" :key="field.id">
                                    <td class="p-4">
                                        <div class="font-semibold">{{ field.label }}</div>
                                        <span
                                            v-if="field.is_required"
                                            class="mt-1 inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700"
                                        >
                                            الزامی
                                        </span>
                                    </td>

                                    <td
                                        dir="ltr"
                                        class="p-4 text-sm text-slate-500"
                                    >
                                        {{ field.key }}
                                    </td>

                                    <td class="p-4 text-sm">
                                        {{
                                            props.entityTypes.find(
                                                (entity) => entity.value === field.entity_type,
                                            )?.label ?? field.entity_type
                                        }}
                                    </td>

                                    <td class="p-4 text-sm">
                                        {{ typeLabels[field.type] ?? field.type }}
                                    </td>

                                    <td class="p-4">
                                        <span
                                            class="rounded-full px-2.5 py-1 text-xs"
                                            :class="
                                                field.is_active
                                                    ? 'bg-emerald-50 text-emerald-700'
                                                    : 'bg-slate-100 text-slate-500'
                                            "
                                        >
                                            {{ field.is_active ? 'فعال' : 'غیرفعال' }}
                                        </span>
                                    </td>

                                    <td class="p-4">
                                        <button
                                            type="button"
                                            class="ml-2 rounded-lg border px-3 py-1.5 text-xs"
                                            @click="openEdit(field)"
                                        >
                                            ویرایش
                                        </button>

                                        <button
                                            type="button"
                                            class="rounded-lg border border-red-100 px-3 py-1.5 text-xs text-red-600"
                                            @click="remove(field)"
                                        >
                                            حذف
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>

        <div
            v-if="show"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"
        >
            <div class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between border-b pb-4">
                    <div>
                        <h3 class="text-lg font-bold">
                            {{ editing ? 'ویرایش فیلد' : 'ایجاد فیلد' }}
                        </h3>
                        <p class="mt-1 text-xs text-slate-500">
                            مشخصات فیلد را تعریف کنید؛ توضیح زیر هر گزینه کاربرد آن را مشخص می‌کند.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-lg p-1 text-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                        aria-label="بستن"
                        @click="close"
                    >
                        ×
                    </button>
                </div>

                <form class="mt-5 space-y-5" @submit.prevent="submit">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-semibold text-slate-700">
                                موجودیت
                            </label>
                            <p class="mt-1 text-xs leading-5 text-slate-500">
                                مشخص می‌کند این فیلد به چه نوع اطلاعاتی تعلق دارد؛ در حال حاضر فقط «کاربر» پشتیبانی می‌شود.
                            </p>

                            <select
                                v-model="form.entity_type"
                                class="mt-2 w-full rounded-xl border-slate-200 bg-white"
                            >
                                <option
                                    v-for="entity in props.entityTypes"
                                    :key="entity.value"
                                    :value="entity.value"
                                >
                                    {{ entity.label }}
                                </option>
                            </select>

                            <p
                                v-if="form.errors.entity_type"
                                class="mt-1.5 text-xs text-red-600"
                            >
                                {{ form.errors.entity_type }}
                            </p>
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-slate-700">
                                کلید
                            </label>
                            <p class="mt-1 text-xs leading-5 text-slate-500">
                                نام فنی و یکتای فیلد در سامانه است و برای ذخیره و بازیابی مقدار آن استفاده می‌شود؛ بهتر است انگلیسی و بدون فاصله باشد.
                            </p>

                            <input
                                v-model="form.key"
                                dir="ltr"
                                type="text"
                                class="mt-2 w-full rounded-xl border-slate-200 bg-white text-left"
                                placeholder="مثلاً employee_code"
                            />

                            <p
                                v-if="form.errors.key"
                                class="mt-1.5 text-xs text-red-600"
                            >
                                {{ form.errors.key }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-slate-700">
                            عنوان
                        </label>
                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            نامی است که کاربران در فرم ایجاد و ویرایش کاربر مشاهده خواهند کرد.
                        </p>

                        <input
                            v-model="form.label"
                            type="text"
                            class="mt-2 w-full rounded-xl border-slate-200 bg-white"
                            placeholder="مثلاً شماره پرسنلی قدیمی"
                        />

                        <p
                            v-if="form.errors.label"
                            class="mt-1.5 text-xs text-red-600"
                        >
                            {{ form.errors.label }}
                        </p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <label class="text-sm font-semibold text-slate-700">
                                نوع فیلد
                            </label>
                            <p class="mt-1 text-xs leading-5 text-slate-500">
                                نوع داده و کنترل ورودی این فیلد را مشخص می‌کند.
                            </p>

                            <select
                                v-model="form.type"
                                class="mt-2 w-full rounded-xl border-slate-200 bg-white"
                            >
                                <option
                                    v-for="type in props.types"
                                    :key="type"
                                    :value="type"
                                >
                                    {{ typeLabels[type] ?? type }}
                                </option>
                            </select>

                            <p
                                v-if="form.errors.type"
                                class="mt-1.5 text-xs text-red-600"
                            >
                                {{ form.errors.type }}
                            </p>
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-slate-700">
                                ترتیب نمایش
                            </label>
                            <p class="mt-1 text-xs leading-5 text-slate-500">
                                عدد کمتر باعث می‌شود فیلد بالاتر نمایش داده شود.
                            </p>

                            <input
                                v-model.number="form.sort_order"
                                type="number"
                                min="0"
                                class="mt-2 w-full rounded-xl border-slate-200 bg-white"
                            />

                            <p
                                v-if="form.errors.sort_order"
                                class="mt-1.5 text-xs text-red-600"
                            >
                                {{ form.errors.sort_order }}
                            </p>
                        </div>

                        <div class="flex items-end">
                            <div class="w-full rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                                <label class="flex items-center gap-2 text-sm text-slate-700">
                                    <input
                                        v-model="form.is_required"
                                        type="checkbox"
                                        class="rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                                    />
                                    این فیلد الزامی است
                                </label>

                                <p class="mt-1 text-xs leading-5 text-slate-500">
                                    کاربر برای ذخیره اطلاعات باید مقدار این فیلد را وارد کند.
                                </p>

                                <label class="mt-3 flex items-center gap-2 text-sm text-slate-700">
                                    <input
                                        v-model="form.is_active"
                                        type="checkbox"
                                        class="rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                                    />
                                    فیلد فعال باشد
                                </label>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="form.type === 'select'"
                        class="rounded-xl border border-slate-200 bg-slate-50/60 p-4"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-sm font-semibold text-slate-700">
                                    گزینه‌های انتخاب
                                </span>
                                <p class="mt-1 text-xs text-slate-500">
                                    گزینه‌هایی که کاربر هنگام ثبت اطلاعات می‌تواند انتخاب کند.
                                </p>
                            </div>

                            <button
                                type="button"
                                class="text-sm font-semibold text-teal-700 hover:text-teal-800"
                                @click="form.options.push('')"
                            >
                                + افزودن گزینه
                            </button>
                        </div>

                        <div
                            v-for="(_, index) in form.options"
                            :key="index"
                            class="mt-3 flex gap-2"
                        >
                            <input
                                v-model="form.options[index]"
                                type="text"
                                class="flex-1 rounded-xl border-slate-200 bg-white"
                                :placeholder="'گزینه ' + (index + 1)"
                            />

                            <button
                                type="button"
                                class="rounded-xl border border-slate-200 px-3 text-slate-500 hover:bg-white hover:text-red-600"
                                :aria-label="'حذف گزینه ' + (index + 1)"
                                @click="form.options.splice(index, 1)"
                            >
                                ×
                            </button>
                        </div>

                        <p
                            v-if="form.errors.options"
                            class="mt-2 text-xs text-red-600"
                        >
                            {{ form.errors.options }}
                        </p>
                    </div>

                    <div class="flex justify-end gap-3 border-t pt-5">
                        <button
                            type="button"
                            class="rounded-xl border px-4 py-2"
                            @click="close"
                        >
                            انصراف
                        </button>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-xl bg-slate-900 px-5 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{ form.processing ? 'در حال ذخیره...' : 'ذخیره' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
