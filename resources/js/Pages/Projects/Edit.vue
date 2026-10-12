<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Head, Link, useForm } from '@inertiajs/vue3';
import JalaliDatePicker from '@/Components/JalaliDatePicker.vue';
import CustomFieldsForm from '@/Components/CustomFieldsForm.vue';

interface Project {
    id: number;
    name: string;
    code: string;
    description: string | null;
    status: 'planning' | 'active' | 'completed' | 'archived';
    start_date: string | null;
    end_date: string | null;
    is_active: boolean;
    is_system_default: boolean;
}

interface Department {
    id: number;
    name: string;
}

const props = defineProps<{
    project: Project;
    customFields: Array<{
        id: number; key: string; label: string;
        type: 'text' | 'textarea' | 'number' | 'date' | 'boolean' | 'select';
        is_required: boolean; options: string[] | null;
    }>;
    customFieldValues: Record<string, string | number | boolean | null>;
}>();

const form = useForm({
    name: props.project.name,
    description: props.project.description ?? '',
    status: props.project.status,
    start_date: props.project.start_date ?? '',
    end_date: props.project.end_date ?? '',
    is_active: props.project.is_active,
    custom_fields: props.customFieldValues ?? {},
});

const statusOptions = [
    { value: 'planning', label: 'در حال برنامه‌ریزی' },
    { value: 'active', label: 'فعال' },
    { value: 'completed', label: 'تکمیل‌شده' },
    { value: 'archived', label: 'بایگانی‌شده' },
] as const;

const submit = (): void => {
    form.put(route('projects.update', props.project.id));
};
</script>

<template>
    <Head title="ویرایش پروژه" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold">ویرایش پروژه</h2>
                <p class="mt-1 text-sm text-muted-foreground">{{ project.name }}</p>
            </div>
        </template>

        <div class="project-form mx-auto max-w-4xl px-4 py-6 sm:px-6 sm:py-8">
            <form class="space-y-6 rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-8" @submit.prevent="submit">
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="space-y-2">
                        <label for="name" class="text-sm font-medium">نام پروژه</label>
                        <input id="name" v-model="form.name" type="text" required class="w-full rounded-md border bg-background px-3 py-2 text-sm" />
                        <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                    </div>



                    <div class="space-y-2">
                        <label for="status" class="text-sm font-medium">وضعیت</label>
                        <select id="status" v-model="form.status" required class="w-full rounded-md border bg-background px-3 py-2 text-sm">
                            <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <p v-if="form.errors.status" class="text-sm text-destructive">{{ form.errors.status }}</p>
                    </div>

                    <div class="flex items-center gap-3 pt-7">
                        <input id="is_active" v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border" />
                        <label for="is_active" class="text-sm font-medium">پروژه فعال باشد</label>
                    </div>

                    <div class="space-y-2">
                        <label for="start_date" class="text-sm font-medium">تاریخ شروع</label>
                        <JalaliDatePicker v-model="form.start_date" placeholder="انتخاب تاریخ شروع" />
                        <p v-if="form.errors.start_date" class="text-sm text-destructive">{{ form.errors.start_date }}</p>
                    </div>

                    <div class="space-y-2">
                        <label for="end_date" class="text-sm font-medium">تاریخ پایان</label>
                        <JalaliDatePicker v-model="form.end_date" placeholder="انتخاب تاریخ پایان" />
                        <p v-if="form.errors.end_date" class="text-sm text-destructive">{{ form.errors.end_date }}</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <label for="description" class="text-sm font-medium">توضیحات</label>
                    <textarea id="description" v-model="form.description" rows="5" class="w-full rounded-md border bg-background px-3 py-2 text-sm" />
                    <p v-if="form.errors.description" class="text-sm text-destructive">{{ form.errors.description }}</p>
                </div>

                <CustomFieldsForm
                    v-if="props.customFields.length"
                    v-model="form.custom_fields"
                    :fields="props.customFields"
                    :errors="form.errors as Record<string, string>"
                    title="اطلاعات تکمیلی پروژه"
                    description="اطلاعات تکمیلی اختصاصی این پروژه را مدیریت کنید."
                />

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" as-child>
                        <Link :href="route('projects.show', project.id)">انصراف</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'در حال ذخیره...' : 'ذخیره تغییرات' }}
                    </Button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
