<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Head, Link, useForm } from '@inertiajs/vue3';
import JalaliDatePicker from '@/Components/JalaliDatePicker.vue';
import CustomFieldsForm from '@/Components/CustomFieldsForm.vue';

interface Manager {
    id: number;
    name: string;
}

interface Department {
    id: number;
    name: string;
}

interface SectionForm {
    name: string;
    department_id: string;
    description: string;
    manager_can_create_tasks: boolean;
    documents: File[];
}

const props = defineProps<{
    managers: Manager[];
    departments: Department[];
    canUploadDocuments: boolean;
    customFields: Array<{
        id: number; key: string; label: string;
        type: 'text' | 'textarea' | 'number' | 'date' | 'boolean' | 'select';
        is_required: boolean; options: string[] | null;
    }>;
}>();

const form = useForm({
    department_id: '',
    name: '',
    description: '',
    status: 'planning' as
        | 'planning'
        | 'active'
        | 'completed'
        | 'archived',
    start_date: null as string | null,
    end_date: null as string | null,
    manager_id: '',
    is_active: true,
    custom_fields: {} as Record<string, string | number | boolean | null>,
    project_documents: [] as File[],
    sections: [] as SectionForm[],
});

function addSection(): void {
    form.sections.push({ name: '', department_id: '', description: '', manager_can_create_tasks: true, documents: [] });
}

function updateProjectDocuments(event: Event): void {
    const input = event.target as HTMLInputElement;
    form.project_documents = Array.from(input.files ?? []);
}

function updateSectionDocuments(index: number, event: Event): void {
    const input = event.target as HTMLInputElement;
    form.sections[index].documents = Array.from(input.files ?? []);
}

function removeSection(index: number): void {
    form.sections.splice(index, 1);
}

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
                <div class="grid gap-5">
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
                            required
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


                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div class="hidden">
                        <label for="department_id" class="mb-2 block text-sm font-medium">
                            واحد
                        </label>
                        <select
                            id="department_id"
                            v-model="form.department_id"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            :disabled="form.processing"
                        >
                            <option value="">بدون واحد</option>
                            <option
                                v-for="department in props.departments"
                                :key="department.id"
                                :value="String(department.id)"
                            >
                                {{ department.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.department_id" class="mt-1 text-xs text-destructive">
                            {{ form.errors.department_id }}
                        </p>
                    </div>

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
                            required
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

                        <JalaliDatePicker
                            v-model="form.start_date"
                            placeholder="انتخاب تاریخ شروع"
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

                        <JalaliDatePicker
                            v-model="form.end_date"
                            placeholder="انتخاب تاریخ پایان"
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

                <section v-if="canUploadDocuments" class="space-y-3 rounded-xl border border-border p-4 sm:p-5">
                    <div>
                        <h3 class="font-semibold">مدارک عمومی پروژه</h3>
                        <p class="mt-1 text-sm text-muted-foreground">فایل‌هایی که برای همه واحدهای درگیر در پروژه کاربرد دارند.</p>
                    </div>
                    <input type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.txt,.csv,.zip" class="block w-full rounded-md border border-input bg-background px-3 py-2 text-sm" @change="updateProjectDocuments" />
                    <p v-if="form.project_documents.length" class="text-xs text-muted-foreground">{{ form.project_documents.length }} فایل برای پیوست انتخاب شده است.</p>
                    <p v-if="form.errors.project_documents" class="text-xs text-destructive">{{ form.errors.project_documents }}</p>
                </section>

                <section class="space-y-4 rounded-xl border border-border p-4 sm:p-5">
                    <p v-if="form.errors.sections" class="text-sm text-destructive">{{ form.errors.sections }}</p>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="font-semibold">بخش‌های پروژه</h3>
                            <p class="mt-1 text-sm text-muted-foreground">برای هر واحد مسئول، یک بخش مستقل تعریف کنید.</p>
                        </div>
                        <Button type="button" variant="outline" @click="addSection">
                            + افزودن بخش جدید
                        </Button>
                    </div>

                    <div v-if="form.sections.length === 0" class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        هنوز بخشی اضافه نشده است. برای تعریف کارهای واحدها، بخش جدید ایجاد کنید.
                    </div>

                    <div v-for="(section, index) in form.sections" :key="index" class="space-y-4 rounded-lg border bg-muted/20 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <h4 class="font-medium">بخش {{ index + 1 }}</h4>
                            <Button type="button" variant="outline" size="sm" @click="removeSection(index)">حذف بخش</Button>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <label :for="'section-name-' + index" class="text-sm font-medium">نام بخش</label>
                                <input :id="'section-name-' + index" v-model="section.name" required maxlength="150" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" placeholder="مثلاً طراحی محصول" />
                                <p v-if="form.errors['sections.' + index + '.name']" class="text-xs text-destructive">{{ form.errors['sections.' + index + '.name'] }}</p>
                            </div>
                            <div class="space-y-2">
                                <label :for="'section-department-' + index" class="text-sm font-medium">واحد انجام‌دهنده</label>
                                <select :id="'section-department-' + index" v-model="section.department_id" required class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                    <option value="">انتخاب واحد مسئول</option>
                                    <option v-for="department in props.departments" :key="department.id" :value="String(department.id)">{{ department.name }}</option>
                                </select>
                                <p v-if="form.errors['sections.' + index + '.department_id']" class="text-xs text-destructive">{{ form.errors['sections.' + index + '.department_id'] }}</p>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label :for="'section-description-' + index" class="text-sm font-medium">توضیحات و اطلاعات دریافتی برای واحد</label>
                            <textarea :id="'section-description-' + index" v-model="section.description" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" placeholder="شرح محدوده کار، خروجی مورد انتظار و نکات اجرایی" />
                        </div>
                        <div v-if="canUploadDocuments" class="space-y-2">
                            <label :for="'section-documents-' + index" class="text-sm font-medium">مدارک اختصاصی این واحد</label>
                            <input :id="'section-documents-' + index" type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.txt,.csv,.zip" class="block w-full rounded-md border border-input bg-background px-3 py-2 text-sm" @change="updateSectionDocuments(index, $event)" />
                            <p v-if="section.documents.length" class="text-xs text-muted-foreground">{{ section.documents.length }} فایل انتخاب شده است.</p>
                            <p v-if="form.errors['sections.' + index + '.documents']" class="text-xs text-destructive">{{ form.errors['sections.' + index + '.documents'] }}</p>
                        </div>
                        <label class="flex items-start gap-3 rounded-lg border bg-background p-3">
                            <input v-model="section.manager_can_create_tasks" type="checkbox" class="mt-1 h-4 w-4 rounded border-input" />
                            <span class="space-y-1">
                                <span class="block text-sm font-medium">مدیر واحد بتواند تسک تعریف کند</span>
                                <span class="block text-xs text-muted-foreground">در صورت فعال بودن، مدیر همین واحد می‌تواند تسک‌های این بخش را تعریف و به کارکنان ارجاع دهد. ادمین پروژه در هر دو حالت امکان تعریف تسک دارد.</span>
                            </span>
                        </label>
                    </div>
                </section>

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
