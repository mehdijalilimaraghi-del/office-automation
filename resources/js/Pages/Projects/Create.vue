<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Head, Link, useForm } from '@inertiajs/vue3';
import JalaliDatePicker from '@/Components/JalaliDatePicker.vue';
import CustomFieldsForm from '@/Components/CustomFieldsForm.vue';
import { ref } from 'vue';

interface Manager {
    id: number;
    name: string;
}

interface Department {
    id: number;
    name: string;
    tree_label: string;
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

const showManagerHelp = ref(false);
const expandedSectionIndex = ref<number | null>(0);

const form = useForm({
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
    sections: [{ name: '', department_id: '', description: '', manager_can_create_tasks: true, documents: [] }] as SectionForm[],
});

function addSection(): void {
    form.sections.push({ name: '', department_id: '', description: '', manager_can_create_tasks: true, documents: [] });
    expandedSectionIndex.value = form.sections.length - 1;
}

function toggleSection(index: number): void {
    expandedSectionIndex.value = expandedSectionIndex.value === index ? null : index;
}

function updateProjectDocuments(event: Event): void {
    const input = event.target as HTMLInputElement;
    const selectedFiles = Array.from(input.files ?? []);
    form.project_documents = [...form.project_documents, ...selectedFiles.filter(
        (file) => !form.project_documents.some(
            (existing) => existing.name === file.name && existing.size === file.size && existing.lastModified === file.lastModified,
        ),
    )];
    input.value = '';
}

function removeProjectDocument(index: number): void {
    form.project_documents.splice(index, 1);
}

function updateSectionDocuments(index: number, event: Event): void {
    const input = event.target as HTMLInputElement;
    const selectedFiles = Array.from(input.files ?? []);
    const existingFiles = form.sections[index].documents;
    form.sections[index].documents = [...existingFiles, ...selectedFiles.filter(
        (file) => !existingFiles.some(
            (existing) => existing.name === file.name && existing.size === file.size && existing.lastModified === file.lastModified,
        ),
    )];
    input.value = '';
}

function removeSectionDocument(sectionIndex: number, fileIndex: number): void {
    form.sections[sectionIndex].documents.splice(fileIndex, 1);
}

function formatFileSize(size: number): string {
    if (size < 1024) return size + ' B';
    if (size < 1024 * 1024) return (size / 1024).toFixed(1) + ' KB';
    return (size / (1024 * 1024)).toFixed(1) + ' MB';
}

function removeSection(index: number): void {
    form.sections.splice(index, 1);

    if (expandedSectionIndex.value === index) {
        expandedSectionIndex.value = form.sections.length > 0
            ? Math.min(index, form.sections.length - 1)
            : null;
    } else if (expandedSectionIndex.value !== null && expandedSectionIndex.value > index) {
        expandedSectionIndex.value -= 1;
    }
}

function sectionError(key: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[key];
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

        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
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
                    <div>
                        <div class="mb-2 flex items-center gap-2">
                            <label
                                for="manager_id"
                                class="text-sm font-medium"
                            >
                                مدیر پروژه <span class="text-destructive">*</span>
                            </label>
                            <div class="relative">
                                <button
                                    type="button"
                                    class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-primary/30 bg-primary/5 text-xs font-bold text-primary transition hover:bg-primary/10 focus:outline-none focus:ring-2 focus:ring-primary/30"
                                    aria-label="راهنمای انتخاب مدیر پروژه"
                                    :aria-expanded="showManagerHelp"
                                    aria-controls="manager-help-popover"
                                    @click="showManagerHelp = !showManagerHelp"
                                >
                                    ?
                                </button>
                                <div
                                    v-if="showManagerHelp"
                                    id="manager-help-popover"
                                    role="dialog"
                                    aria-label="راهنمای مدیر پروژه"
                                    class="absolute right-0 top-7 z-50 w-72 rounded-xl border border-slate-200 bg-white p-4 text-slate-900 shadow-2xl ring-1 ring-black/10 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                >
                                    <div class="mb-2 flex items-center justify-between gap-3">
                                        <h4 class="text-sm font-semibold">راهنمای انتخاب مدیر پروژه</h4>
                                        <button
                                            type="button"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                            aria-label="بستن راهنما"
                                            @click="showManagerHelp = false"
                                        >
                                            ×
                                        </button>
                                    </div>
                                    <p class="text-xs leading-6 text-muted-foreground">
                                        برای نمایش کاربر در این فهرست، ابتدا باید او را عضو واحد «مدیریت پروژه» کنید.
                                    </p>
                                    <div class="absolute -top-1.5 right-2 h-3 w-3 rotate-45 border-l border-t border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"></div>
                                </div>
                            </div>
                        </div>

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
                    <ul v-if="form.project_documents.length" class="space-y-2">
                        <li v-for="(file, fileIndex) in form.project_documents" :key="file.name + '-' + file.size + '-' + file.lastModified" class="flex items-center gap-3 rounded-lg border border-border bg-background px-3 py-2">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 shrink-0 text-muted-foreground"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                            <div class="min-w-0 flex-1">
                                <p class="break-all text-sm font-medium">{{ file.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ formatFileSize(file.size) }}</p>
                            </div>
                            <button type="button" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-destructive transition hover:bg-destructive/10 focus:outline-none focus:ring-2 focus:ring-destructive/30" :aria-label="'حذف فایل ' + file.name" :title="'حذف فایل ' + file.name" @click="removeProjectDocument(fileIndex)">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="m19 6-1 14H6L5 6"/><path d="M10 11v5M14 11v5"/></svg>
                            </button>
                        </li>
                    </ul>
                    <p v-if="form.errors['project_documents.0']" class="text-xs text-destructive">{{ form.errors['project_documents.0'] }}</p>
                    <p v-if="form.errors.project_documents" class="text-xs text-destructive">{{ form.errors.project_documents }}</p>
                </section>

                <section class="space-y-4 rounded-xl border border-border p-4 sm:p-5">
                    <p v-if="form.errors.sections" class="text-sm text-destructive">{{ form.errors.sections }}</p>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="font-semibold">بخش‌های پروژه <span class="text-destructive">*</span></h3>
                            <p class="mt-1 text-sm text-muted-foreground">هر پروژه باید حداقل یک بخش داشته باشد تا بتوان برای آن تسک تعریف کرد.</p>
                        </div>
                        <Button type="button" variant="outline" @click="addSection">
                            + افزودن بخش جدید
                        </Button>
                    </div>

                    <div v-if="form.sections.length === 0" class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        هنوز بخشی اضافه نشده است. برای تعریف کارهای واحدها، بخش جدید ایجاد کنید.
                    </div>

                    <div v-for="(section, index) in form.sections" :key="index" class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                        <div class="flex items-center gap-3 bg-muted/40 px-4 py-3 sm:px-5">
                            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border bg-background text-sm font-semibold text-foreground">{{ index + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold">{{ section.name.trim() || ('بخش ' + (index + 1)) }}</p>
                                <p class="mt-0.5 truncate text-xs text-muted-foreground">{{ props.departments.find((department) => String(department.id) === section.department_id)?.name || 'واحد انجام‌دهنده انتخاب نشده' }}</p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-border bg-background text-muted-foreground transition hover:bg-muted hover:text-foreground focus:outline-none focus:ring-2 focus:ring-primary/30"
                                :aria-expanded="expandedSectionIndex === index"
                                :aria-label="expandedSectionIndex === index ? 'جمع کردن بخش' : 'باز کردن بخش'"
                                :title="expandedSectionIndex === index ? 'جمع کردن بخش' : 'باز کردن بخش'"
                                @click="toggleSection(index)"
                            >
                                <svg v-if="expandedSectionIndex === index" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                                    <path d="m18 15-6-6-6 6" />
                                </svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                                    <path d="m6 9 6 6 6-6" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-destructive/30 bg-background text-destructive transition hover:bg-destructive/10 focus:outline-none focus:ring-2 focus:ring-destructive/30"
                                aria-label="حذف بخش"
                                title="حذف بخش"
                                @click="removeSection(index)"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                                    <path d="M3 6h18" />
                                    <path d="M8 6V4h8v2" />
                                    <path d="m19 6-1 14H6L5 6" />
                                    <path d="M10 11v5M14 11v5" />
                                </svg>
                            </button>
                        </div>
                        <div v-if="expandedSectionIndex === index" class="space-y-4 border-t p-4 sm:p-5">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <label :for="'section-name-' + index" class="text-sm font-medium">نام بخش</label>
                                    <input :id="'section-name-' + index" v-model="section.name" required maxlength="150" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" placeholder="مثلاً طراحی محصول" />
                                    <p v-if="sectionError('sections.' + index + '.name')" class="text-xs text-destructive">{{ sectionError('sections.' + index + '.name') }}</p>
                                </div>
                                <div class="space-y-2">
                                    <label :for="'section-department-' + index" class="text-sm font-medium">واحد انجام‌دهنده</label>
                                    <select :id="'section-department-' + index" v-model="section.department_id" required class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                        <option value="">انتخاب واحد مسئول</option>
                                        <option v-for="department in props.departments" :key="department.id" :value="String(department.id)">{{ department.tree_label || department.name }}</option>
                                    </select>
                                    <p v-if="sectionError('sections.' + index + '.department_id')" class="text-xs text-destructive">{{ sectionError('sections.' + index + '.department_id') }}</p>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label :for="'section-description-' + index" class="text-sm font-medium">توضیحات و اطلاعات دریافتی برای واحد</label>
                                <textarea :id="'section-description-' + index" v-model="section.description" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" placeholder="شرح محدوده کار، خروجی مورد انتظار و نکات اجرایی" />
                            </div>
                            <div v-if="canUploadDocuments" class="space-y-2">
                                <label :for="'section-documents-' + index" class="text-sm font-medium">مدارک اختصاصی این واحد</label>
                                <input :id="'section-documents-' + index" type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.txt,.csv,.zip" class="block w-full rounded-md border border-input bg-background px-3 py-2 text-sm" @change="updateSectionDocuments(index, $event)" />
                                <ul v-if="section.documents.length" class="space-y-2">
                                    <li v-for="(file, fileIndex) in section.documents" :key="file.name + '-' + file.size + '-' + file.lastModified" class="flex items-center gap-3 rounded-lg border border-border bg-background px-3 py-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 shrink-0 text-muted-foreground"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                                        <div class="min-w-0 flex-1">
                                            <p class="break-all text-sm font-medium">{{ file.name }}</p>
                                            <p class="text-xs text-muted-foreground">{{ formatFileSize(file.size) }}</p>
                                        </div>
                                        <button type="button" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-destructive transition hover:bg-destructive/10 focus:outline-none focus:ring-2 focus:ring-destructive/30" :aria-label="'حذف فایل ' + file.name" :title="'حذف فایل ' + file.name" @click="removeSectionDocument(index, fileIndex)">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="m19 6-1 14H6L5 6"/><path d="M10 11v5M14 11v5"/></svg>
                                        </button>
                                    </li>
                                </ul>
                                <p v-if="sectionError('sections.' + index + '.documents') || sectionError('sections.' + index + '.documents.0')" class="text-xs text-destructive">{{ sectionError('sections.' + index + '.documents') || sectionError('sections.' + index + '.documents.0') }}</p>
                            </div>
                            <label class="flex items-start gap-3 rounded-lg border bg-background p-3">
                                <input v-model="section.manager_can_create_tasks" type="checkbox" class="mt-1 h-4 w-4 rounded border-input" />
                                <span class="space-y-1">
                                    <span class="block text-sm font-medium">مدیر واحد بتواند تسک تعریف کند</span>
                                    <span class="block text-xs text-muted-foreground">در صورت فعال بودن، مدیر همین واحد می‌تواند تسک‌های این بخش را تعریف و به کارکنان ارجاع دهد. ادمین پروژه در هر دو حالت امکان تعریف تسک دارد.</span>
                                </span>
                            </label>
                        </div>
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
