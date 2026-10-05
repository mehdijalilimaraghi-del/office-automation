<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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

interface EntityType {
    value: string;
    label: string;
}

const props = defineProps<{
    fields: Field[];
    entityTypes: EntityType[];
    types: string[];
}>();

const editing = ref<Field | null>(null);
const show = ref(false);
const entityFilter = ref('');

const form = useForm({
    entity_type: props.entityTypes[0]?.value ?? '',
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
    boolean: 'بله / خیر',
    select: 'انتخاب از فهرست',
};

const filteredFields = computed(() =>
    entityFilter.value
        ? props.fields.filter((field) => field.entity_type === entityFilter.value)
        : props.fields,
);

const entityCount = (entityType: string): number =>
    props.fields.filter((field) => field.entity_type === entityType).length;

const entityLabel = (entityType: string): string =>
    props.entityTypes.find((entity) => entity.value === entityType)?.label ?? entityType;

const resetForm = (): void => {
    form.reset();
    form.entity_type = props.entityTypes[0]?.value ?? '';
    form.type = 'text';
    form.is_required = false;
    form.is_active = true;
    form.sort_order = props.fields.length;
    form.options = [];
    form.clearErrors();
};

const openCreate = (): void => {
    editing.value = null;
    resetForm();
    show.value = true;
};

const openEdit = (field: Field): void => {
    editing.value = field;
    form.entity_type = field.entity_type;
    form.label = field.label;
    form.type = field.type;
    form.is_required = field.is_required;
    form.is_active = field.is_active;
    form.sort_order = field.sort_order;
    form.options = [...(field.options ?? [])];
    form.clearErrors();
    show.value = true;
};

const close = (): void => {
    if (form.processing) {
        return;
    }

    show.value = false;
    editing.value = null;
    form.reset();
    form.clearErrors();
};

const submit = (): void => {
    const options = {
        preserveScroll: true,
        onSuccess: close,
    };

    if (editing.value) {
        form.put(route('custom-fields.update', editing.value.id), options);
        return;
    }

    form.post(route('custom-fields.store'), options);
};

const remove = (field: Field): void => {
    if (!window.confirm('آیا از حذف فیلد «' + field.label + '» مطمئن هستید؟')) {
        return;
    }

    useForm({}).delete(route('custom-fields.destroy', field.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="فیلدهای سفارشی" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-bold text-teal-700">تنظیمات سازمان</p>
                    <h2 class="mt-1 text-2xl font-black tracking-tight text-slate-950">
                        فیلدهای سفارشی
                    </h2>
                    <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
                        اطلاعات اضافی موردنیاز برای کاربران، واحدها، سمت‌ها، نقش‌ها، پروژه‌ها و تسک‌ها را بدون تغییر ساختار اصلی سامانه تعریف کنید.
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="openCreate"
                >
                    <span class="text-lg leading-none">+</span>
                    ایجاد فیلد
                </button>
            </div>
        </template>

        <div class="min-h-[calc(100vh-8rem)] bg-slate-50 py-6 md:py-8" dir="rtl">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="grid gap-px bg-slate-200 md:grid-cols-3">
                        <div class="bg-white p-5 sm:p-6">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-50 text-sm font-black text-teal-700">۱</div>
                            <h3 class="mt-4 text-sm font-bold text-slate-900">متعلق به یک موجودیت</h3>
                            <p class="mt-1 text-xs leading-5 text-slate-500">مشخص می‌کنید فیلد در فرم کدام نوع رکورد نمایش داده شود؛ مثل کاربر یا پروژه.</p>
                        </div>
                        <div class="bg-white p-5 sm:p-6">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-50 text-sm font-black text-sky-700">۲</div>
                            <h3 class="mt-4 text-sm font-bold text-slate-900">عنوان برای کاربر</h3>
                            <p class="mt-1 text-xs leading-5 text-slate-500">کاربر فقط عنوان و مقدار فیلد را می‌بیند. شناسه فنی فیلد توسط سامانه مدیریت می‌شود.</p>
                        </div>
                        <div class="bg-white p-5 sm:p-6">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-sm font-black text-violet-700">۳</div>
                            <h3 class="mt-4 text-sm font-bold text-slate-900">بدون تغییر دیتابیس اصلی</h3>
                            <p class="mt-1 text-xs leading-5 text-slate-500">فیلدهای جدید در لایه سفارشی ذخیره می‌شوند و ساختار اصلی موجودیت‌ها را دست‌کاری نمی‌کنند.</p>
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-5 sm:px-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h3 class="font-black text-slate-900">فهرست فیلدهای تعریف‌شده</h3>
                                <p class="mt-1 text-xs leading-5 text-slate-500">
                                    هر فیلد پس از فعال شدن، در فرم موجودیت مربوطه قابل استفاده خواهد بود.
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="rounded-full px-3 py-1.5 text-xs font-bold transition"
                                    :class="entityFilter === '' ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                    @click="entityFilter = ''"
                                >
                                    همه
                                    <span class="mr-1 opacity-70">{{ props.fields.length }}</span>
                                </button>

                                <button
                                    v-for="entity in props.entityTypes"
                                    :key="entity.value"
                                    type="button"
                                    class="rounded-full px-3 py-1.5 text-xs font-bold transition"
                                    :class="entityFilter === entity.value ? 'bg-teal-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                    @click="entityFilter = entity.value"
                                >
                                    {{ entity.label }}
                                    <span class="mr-1 opacity-70">{{ entityCount(entity.value) }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="filteredFields.length === 0"
                        class="px-6 py-16 text-center"
                    >
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-xl text-slate-400">+</div>
                        <h3 class="mt-4 font-bold text-slate-800">
                            {{ entityFilter ? 'برای این بخش فیلدی تعریف نشده است.' : 'هنوز فیلدی تعریف نشده است.' }}
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">
                            یک فیلد سفارشی ایجاد کنید تا در فرم مربوطه نمایش داده شود.
                        </p>
                        <button
                            type="button"
                            class="mt-5 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-800"
                            @click="openCreate"
                        >
                            ایجاد اولین فیلد
                        </button>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[900px] text-right">
                            <thead class="bg-slate-50 text-[11px] font-bold text-slate-500">
                                <tr>
                                    <th class="px-5 py-4">عنوان فیلد</th>
                                    <th class="px-5 py-4">مربوط به</th>
                                    <th class="px-5 py-4">نوع</th>
                                    <th class="px-5 py-4">الزام</th>
                                    <th class="px-5 py-4">ترتیب</th>
                                    <th class="px-5 py-4">وضعیت</th>
                                    <th class="px-5 py-4 text-left">عملیات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="field in filteredFields"
                                    :key="field.id"
                                    class="transition hover:bg-slate-50/70"
                                >
                                    <td class="px-5 py-4">
                                        <div class="font-bold text-slate-900">{{ field.label }}</div>
                                        <div class="mt-1 text-[11px] text-slate-400">شناسه فنی توسط سامانه مدیریت می‌شود</div>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-slate-700">
                                        {{ entityLabel(field.entity_type) }}
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-700">
                                            {{ typeLabels[field.type] ?? field.type }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span
                                            v-if="field.is_required"
                                            class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-700"
                                        >
                                            الزامی
                                        </span>
                                        <span v-else class="text-xs text-slate-400">اختیاری</span>
                                    </td>
                                    <td class="px-5 py-4 text-sm font-semibold text-slate-700">{{ field.sort_order }}</td>
                                    <td class="px-5 py-4">
                                        <span
                                            class="rounded-full px-2.5 py-1 text-[11px] font-bold"
                                            :class="field.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                        >
                                            {{ field.is_active ? 'فعال' : 'غیرفعال' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-left">
                                        <div class="flex justify-end gap-2">
                                            <button
                                                type="button"
                                                class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-white hover:border-slate-300"
                                                @click="openEdit(field)"
                                            >
                                                ویرایش
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-xl bg-red-50 px-3 py-2 text-xs font-bold text-red-700 transition hover:bg-red-100"
                                                @click="remove(field)"
                                            >
                                                حذف
                                            </button>
                                        </div>
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
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/55 p-4 backdrop-blur-sm"
            @keydown.esc="close"
        >
            <div class="flex min-h-full items-center justify-center">
                <div class="w-full max-w-3xl overflow-hidden rounded-3xl bg-white shadow-2xl">
                    <div class="border-b border-slate-100 px-5 py-5 sm:px-7">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold text-teal-700">
                                    {{ editing ? 'ویرایش فیلد سفارشی' : 'ایجاد فیلد سفارشی' }}
                                </p>
                                <h3 class="mt-1 text-xl font-black text-slate-950">
                                    {{ editing ? editing.label : 'اطلاعات تکمیلی جدید' }}
                                </h3>
                                <p class="mt-1 text-sm leading-6 text-slate-500">
                                    اطلاعاتی را تعریف کنید که در کنار اطلاعات اصلی این نوع رکورد نگهداری و نمایش داده شود.
                                </p>
                            </div>
                            <button
                                type="button"
                                class="rounded-xl p-2 text-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                                aria-label="بستن"
                                @click="close"
                            >
                                ×
                            </button>
                        </div>
                    </div>

                    <form class="space-y-6 p-5 sm:p-7" @submit.prevent="submit">
                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-bold text-slate-700">موجودیت</label>
                                <p class="mb-2 text-xs leading-5 text-slate-500">
                                    مشخص می‌کند این فیلد در فرم کدام نوع رکورد نمایش داده شود.
                                </p>
                                <select
                                    v-model="form.entity_type"
                                    class="w-full rounded-xl border-slate-200 bg-slate-50/50 px-3 py-3 text-sm focus:border-teal-500 focus:ring-teal-500"
                                >
                                    <option
                                        v-for="entity in props.entityTypes"
                                        :key="entity.value"
                                        :value="entity.value"
                                    >
                                        {{ entity.label }}
                                    </option>
                                </select>
                                <p v-if="form.errors.entity_type" class="mt-1.5 text-xs text-red-600">{{ form.errors.entity_type }}</p>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-bold text-slate-700">عنوان فیلد</label>
                                <p class="mb-2 text-xs leading-5 text-slate-500">
                                    نامی است که کاربر در فرم می‌بیند؛ شناسه فنی آن را سامانه خودکار ایجاد می‌کند.
                                </p>
                                <input
                                    v-model="form.label"
                                    type="text"
                                    maxlength="150"
                                    class="w-full rounded-xl border-slate-200 bg-slate-50/50 px-3 py-3 text-sm focus:border-teal-500 focus:ring-teal-500"
                                    placeholder="مثلاً کد پروژه قدیمی"
                                />
                                <p v-if="form.errors.label" class="mt-1.5 text-xs text-red-600">{{ form.errors.label }}</p>
                            </div>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-bold text-slate-700">نوع فیلد</label>
                                <p class="mb-2 text-xs leading-5 text-slate-500">
                                    شکل ورود اطلاعات در فرم را مشخص می‌کند.
                                </p>
                                <select
                                    v-model="form.type"
                                    class="w-full rounded-xl border-slate-200 bg-slate-50/50 px-3 py-3 text-sm focus:border-teal-500 focus:ring-teal-500"
                                >
                                    <option
                                        v-for="type in props.types"
                                        :key="type"
                                        :value="type"
                                    >
                                        {{ typeLabels[type] ?? type }}
                                    </option>
                                </select>
                                <p v-if="form.errors.type" class="mt-1.5 text-xs text-red-600">{{ form.errors.type }}</p>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-bold text-slate-700">ترتیب نمایش</label>
                                <p class="mb-2 text-xs leading-5 text-slate-500">
                                    عدد کمتر باعث می‌شود این فیلد بالاتر از فیلدهای دیگر نمایش داده شود.
                                </p>
                                <input
                                    v-model.number="form.sort_order"
                                    type="number"
                                    min="0"
                                    class="w-full rounded-xl border-slate-200 bg-slate-50/50 px-3 py-3 text-sm focus:border-teal-500 focus:ring-teal-500"
                                />
                                <p v-if="form.errors.sort_order" class="mt-1.5 text-xs text-red-600">{{ form.errors.sort_order }}</p>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
                                <span class="flex items-start gap-3">
                                    <input
                                        v-model="form.is_required"
                                        type="checkbox"
                                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                                    />
                                    <span>
                                        <span class="block text-sm font-bold text-slate-800">فیلد الزامی باشد</span>
                                        <span class="mt-1 block text-xs leading-5 text-slate-500">کاربر برای ذخیره فرم باید برای این فیلد مقدار وارد کند.</span>
                                    </span>
                                </span>
                            </label>

                            <label class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
                                <span class="flex items-start gap-3">
                                    <input
                                        v-model="form.is_active"
                                        type="checkbox"
                                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                                    />
                                    <span>
                                        <span class="block text-sm font-bold text-slate-800">فیلد فعال باشد</span>
                                        <span class="mt-1 block text-xs leading-5 text-slate-500">فقط فیلدهای فعال در فرم‌های ایجاد و ویرایش نمایش داده می‌شوند.</span>
                                    </span>
                                </span>
                            </label>
                        </div>

                        <div
                            v-if="form.type === 'select'"
                            class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 sm:p-5"
                        >
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h4 class="text-sm font-bold text-slate-800">گزینه‌های فهرست</h4>
                                    <p class="mt-1 text-xs leading-5 text-slate-500">مواردی که کاربر از میان آن‌ها یک گزینه را انتخاب خواهد کرد.</p>
                                </div>
                                <button
                                    type="button"
                                    class="rounded-xl bg-teal-50 px-3 py-2 text-xs font-bold text-teal-700 hover:bg-teal-100"
                                    @click="form.options.push('')"
                                >
                                    + افزودن گزینه
                                </button>
                            </div>

                            <div v-if="form.options.length" class="mt-4 space-y-2">
                                <div
                                    v-for="(_, index) in form.options"
                                    :key="index"
                                    class="flex gap-2"
                                >
                                    <input
                                        v-model="form.options[index]"
                                        type="text"
                                        class="flex-1 rounded-xl border-slate-200 bg-white text-sm focus:border-teal-500 focus:ring-teal-500"
                                        :placeholder="'گزینه ' + (index + 1)"
                                    />
                                    <button
                                        type="button"
                                        class="rounded-xl border border-slate-200 px-3 text-slate-400 hover:border-red-200 hover:bg-red-50 hover:text-red-600"
                                        :aria-label="'حذف گزینه ' + (index + 1)"
                                        @click="form.options.splice(index, 1)"
                                    >
                                        ×
                                    </button>
                                </div>
                            </div>

                            <div v-else class="mt-4 rounded-xl border border-dashed border-slate-300 bg-white px-4 py-5 text-center text-xs text-slate-400">
                                هنوز گزینه‌ای اضافه نشده است.
                            </div>
                        </div>

                        <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
                            <button
                                type="button"
                                class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                                :disabled="form.processing"
                                @click="close"
                            >
                                انصراف
                            </button>
                            <button
                                type="submit"
                                class="rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="form.processing"
                            >
                                {{ form.processing ? 'در حال ذخیره...' : editing ? 'ذخیره تغییرات' : 'ایجاد فیلد' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
