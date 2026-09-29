<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Head, Link, useForm } from '@inertiajs/vue3';

interface Manager {
    id: number;
    name: string;
}

const props = defineProps<{
    managers: Manager[];
}>();

const form = useForm({
    name: '',
    code: '',
    description: '',
    status: 'planning' as
        | 'planning'
        | 'active'
        | 'completed'
        | 'archived',
    start_date: '',
    end_date: '',
    manager_id: '',
    is_active: true,
});

const statusOptions = [
    { value: 'planning', label: 'در حال برنامه‌ریزی' },
    { value: 'active', label: 'فعال' },
    { value: 'completed', label: 'تکمیل‌شده' },
    { value: 'archived', label: 'بایگانی‌شده' },
] as const;

function submit(): void {
    form.post(route('projects.store'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="ایجاد پروژه" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold">
                    ایجاد پروژه
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    اطلاعات پروژه جدید را وارد کنید.
                </p>
            </div>
        </template>

        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <form
                class="space-y-6 rounded-lg border bg-card p-6 shadow-sm"
                @submit.prevent="submit"
            >
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label
                            for="name"
                            class="mb-2 block text-sm font-medium"
                        >
                            نام پروژه
                        </label>

                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            :disabled="form.processing"
                        />

                        <p
                            v-if="form.errors.name"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="code"
                            class="mb-2 block text-sm font-medium"
                        >
                            کد پروژه
                        </label>

                        <input
                            id="code"
                            v-model="form.code"
                            dir="ltr"
                            type="text"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-left text-sm"
                            :disabled="form.processing"
                        />

                        <p
                            v-if="form.errors.code"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ form.errors.code }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label
                            for="manager_id"
                            class="mb-2 block text-sm font-medium"
                        >
                            مدیر پروژه
                        </label>

                        <select
                            id="manager_id"
                            v-model="form.manager_id"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            :disabled="form.processing"
                        >
                            <option value="">
                                انتخاب مدیر پروژه
                            </option>

                            <option
                                v-for="manager in props.managers"
                                :key="manager.id"
                                :value="String(manager.id)"
                            >
                                {{ manager.name }}
                            </option>
                        </select>

                        <p
                            v-if="form.errors.manager_id"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ form.errors.manager_id }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="status"
                            class="mb-2 block text-sm font-medium"
                        >
                            وضعیت
                        </label>

                        <select
                            id="status"
                            v-model="form.status"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            :disabled="form.processing"
                        >
                            <option
                                v-for="option in statusOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>

                        <p
                            v-if="form.errors.status"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ form.errors.status }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label
                            for="start_date"
                            class="mb-2 block text-sm font-medium"
                        >
                            تاریخ شروع
                        </label>

                        <input
                            id="start_date"
                            v-model="form.start_date"
                            dir="ltr"
                            type="date"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-left text-sm"
                            :disabled="form.processing"
                        />

                        <p
                            v-if="form.errors.start_date"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ form.errors.start_date }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="end_date"
                            class="mb-2 block text-sm font-medium"
                        >
                            تاریخ پایان
                        </label>

                        <input
                            id="end_date"
                            v-model="form.end_date"
                            dir="ltr"
                            type="date"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-left text-sm"
                            :disabled="form.processing"
                        />

                        <p
                            v-if="form.errors.end_date"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ form.errors.end_date }}
                        </p>
                    </div>
                </div>

                <div>
                    <label
                        for="description"
                        class="mb-2 block text-sm font-medium"
                    >
                        توضیحات
                    </label>

                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="5"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        :disabled="form.processing"
                    />

                    <p
                        v-if="form.errors.description"
                        class="mt-1 text-xs text-destructive"
                    >
                        {{ form.errors.description }}
                    </p>
                </div>

                <label class="flex items-center gap-2">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-4 w-4 rounded border-input"
                        :disabled="form.processing"
                    />
                    <span class="text-sm font-medium">
                        پروژه فعال باشد
                    </span>
                </label>

                <p
                    v-if="form.errors.is_active"
                    class="text-xs text-destructive"
                >
                    {{ form.errors.is_active }}
                </p>

                <div
                    class="flex items-center justify-end gap-3 border-t pt-5"
                >
                    <Button
                        variant="outline"
                        type="button"
                        as-child
                    >
                        <Link :href="route('projects.index')">
                            انصراف
                        </Link>
                    </Button>

                    <Button
                        type="submit"
                        :disabled="form.processing"
                    >
                        {{
                            form.processing
                                ? 'در حال ذخیره...'
                                : 'ذخیره پروژه'
                        }}
                    </Button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
