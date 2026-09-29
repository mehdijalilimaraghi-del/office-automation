<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Head, Link, useForm } from '@inertiajs/vue3';

interface Project {
    id: number;
    name: string;
    code: string;
    description: string | null;
    status: 'planning' | 'active' | 'completed' | 'archived';
    start_date: string | null;
    end_date: string | null;
    is_active: boolean;
}

const props = defineProps<{ project: Project }>();

const form = useForm({
    name: props.project.name,
    code: props.project.code,
    description: props.project.description ?? '',
    status: props.project.status,
    start_date: props.project.start_date ?? '',
    end_date: props.project.end_date ?? '',
    is_active: props.project.is_active,
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

        <div class="mx-auto max-w-3xl">
            <form class="space-y-6 rounded-lg border bg-card p-6" @submit.prevent="submit">
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="space-y-2">
                        <label for="name" class="text-sm font-medium">نام پروژه</label>
                        <input id="name" v-model="form.name" type="text" class="w-full rounded-md border bg-background px-3 py-2 text-sm" />
                        <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                    </div>

                    <div class="space-y-2">
                        <label for="code" class="text-sm font-medium">کد پروژه</label>
                        <input id="code" v-model="form.code" type="text" class="w-full rounded-md border bg-background px-3 py-2 text-sm" />
                        <p v-if="form.errors.code" class="text-sm text-destructive">{{ form.errors.code }}</p>
                    </div>

                    <div class="space-y-2">
                        <label for="status" class="text-sm font-medium">وضعیت</label>
                        <select id="status" v-model="form.status" class="w-full rounded-md border bg-background px-3 py-2 text-sm">
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
                        <input id="start_date" v-model="form.start_date" type="date" class="w-full rounded-md border bg-background px-3 py-2 text-sm" />
                        <p v-if="form.errors.start_date" class="text-sm text-destructive">{{ form.errors.start_date }}</p>
                    </div>

                    <div class="space-y-2">
                        <label for="end_date" class="text-sm font-medium">تاریخ پایان</label>
                        <input id="end_date" v-model="form.end_date" type="date" class="w-full rounded-md border bg-background px-3 py-2 text-sm" />
                        <p v-if="form.errors.end_date" class="text-sm text-destructive">{{ form.errors.end_date }}</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <label for="description" class="text-sm font-medium">توضیحات</label>
                    <textarea id="description" v-model="form.description" rows="5" class="w-full rounded-md border bg-background px-3 py-2 text-sm" />
                    <p v-if="form.errors.description" class="text-sm text-destructive">{{ form.errors.description }}</p>
                </div>

                <div v-if="form.errors.project" class="text-sm text-destructive">{{ form.errors.project }}</div>

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
