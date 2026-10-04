<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

interface PositionUser {
    id: number;
    name: string;
    pivot?: { is_primary: boolean; is_active: boolean };
}
interface Position {
    id: number;
    name: string;
    code: string | null;
    description: string | null;
    is_active: boolean;
    users?: PositionUser[];
}
interface Department {
    id: number;
    parent_id: number | null;
    name: string;
    code: string | null;
    description: string | null;
    is_active: boolean;
    manager_id: number | null;
    manager?: { id: number; name: string } | null;
    parent?: { id: number; name: string } | null;
    positions?: Position[];
}
interface User { id: number; name: string; }

const props = defineProps<{ departments: Department[]; users: User[] }>();
const editingDepartment = ref<Department | null>(null);
const expandedDepartmentId = ref<number | null>(null);
const editingPosition = ref<Position | null>(null);

const form = useForm({
    parent_id: null as number | null,
    manager_id: null as number | null,
    name: '',
    code: '',
    description: '',
    is_active: true,
});
const positionForm = useForm({
    name: '',
    code: '',
    description: '',
    is_active: true,
});
const assignmentForm = useForm({
    user_id: null as number | null,
    is_primary: false,
});

const getDescendantIds = (id: number): number[] => {
    const result: number[] = [];
    const collect = (parentId: number) => {
        props.departments.forEach((d) => {
            if (d.parent_id === parentId) { result.push(d.id); collect(d.id); }
        });
    };
    collect(id);
    return result;
};

const parentOptions = computed(() => {
    if (!editingDepartment.value) return props.departments;
    const excluded = new Set([editingDepartment.value.id, ...getDescendantIds(editingDepartment.value.id)]);
    return props.departments.filter((d) => !excluded.has(d.id));
});

const stats = computed(() => ({
    total: props.departments.length,
    active: props.departments.filter((d) => d.is_active).length,
    managers: props.departments.filter((d) => d.manager_id).length,
    positions: props.departments.reduce((sum, d) => sum + (d.positions?.length ?? 0), 0),
}));

function resetDepartmentForm() {
    form.reset();
    form.is_active = true;
    form.clearErrors();
}
function openCreate() {
    editingDepartment.value = null;
    resetDepartmentForm();
}
function openEdit(department: Department) {
    editingDepartment.value = department;
    form.parent_id = department.parent_id;
    form.manager_id = department.manager_id;
    form.name = department.name;
    form.code = department.code ?? '';
    form.description = department.description ?? '';
    form.is_active = department.is_active;
    form.clearErrors();
}
function submitDepartment() {
    const options = { preserveScroll: true, onSuccess: () => { editingDepartment.value = null; resetDepartmentForm(); } };
    if (editingDepartment.value) form.put(route('departments.update', editingDepartment.value.id), options);
    else form.post(route('departments.store'), options);
}
function deleteDepartment(department: Department) {
    if (!confirm(`آیا از حذف «${department.name}» مطمئن هستید؟`)) return;
    form.delete(route('departments.destroy', department.id), { preserveScroll: true });
}
function toggleStatus(department: Department) {
    useForm({}).patch(route('departments.toggle-status', department.id), { preserveScroll: true });
}
function togglePositions(department: Department) {
    expandedDepartmentId.value = expandedDepartmentId.value === department.id ? null : department.id;
    editingPosition.value = null;
    positionForm.reset();
    positionForm.is_active = true;
}
function openPositionCreate() {
    editingPosition.value = null;
    positionForm.reset();
    positionForm.is_active = true;
    positionForm.clearErrors();
}
function openPositionEdit(position: Position) {
    editingPosition.value = position;
    positionForm.name = position.name;
    positionForm.code = position.code ?? '';
    positionForm.description = position.description ?? '';
    positionForm.is_active = position.is_active;
    positionForm.clearErrors();
}
function submitPosition(department: Department) {
    const options = { preserveScroll: true, onSuccess: () => openPositionCreate() };
    if (editingPosition.value) positionForm.put(route('departments.positions.update', [department.id, editingPosition.value.id]), options);
    else positionForm.post(route('departments.positions.store', department.id), options);
}
function deletePosition(department: Department, position: Position) {
    if (!confirm(`آیا از حذف سمت «${position.name}» مطمئن هستید؟`)) return;
    positionForm.delete(route('departments.positions.destroy', [department.id, position.id]), { preserveScroll: true });
}
function assignPosition(department: Department, position: Position) {
    if (!assignmentForm.user_id) return;
    assignmentForm.post(route('departments.positions.members.store', [department.id, position.id]), {
        preserveScroll: true,
        onSuccess: () => { assignmentForm.reset(); assignmentForm.is_primary = false; },
    });
}
function unassignPosition(department: Department, position: Position, user: PositionUser) {
    if (!confirm(`تخصیص «${user.name}» از این سمت حذف شود؟`)) return;
    assignmentForm.delete(route('departments.positions.members.destroy', [department.id, position.id, user.id]), { preserveScroll: true });
}
</script>

<template>
    <Head title="ساختار سازمانی" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="flex items-center gap-2 text-xs font-medium text-slate-500"><span class="h-2 w-2 rounded-full bg-teal-500"></span>مدیریت سازمان</div>
                    <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">ساختار سازمانی</h2>
                    <p class="mt-1 text-sm text-slate-500">واحدها، مدیران، اعضا و سمت‌های سازمانی را در یک ساختار منسجم مدیریت کنید.</p>
                </div>
                <button type="button" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800" @click="openCreate">+ واحد جدید</button>
            </div>
        </template>

        <div class="min-h-[calc(100vh-8rem)] bg-slate-50 py-6 md:py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs text-slate-500">کل واحدها</p><p class="mt-2 text-2xl font-bold text-slate-900">{{ stats.total }}</p></div>
                    <div class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-5 shadow-sm"><p class="text-xs text-emerald-700">واحدهای فعال</p><p class="mt-2 text-2xl font-bold text-emerald-900">{{ stats.active }}</p></div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs text-slate-500">دارای مدیر</p><p class="mt-2 text-2xl font-bold text-slate-900">{{ stats.managers }}</p></div>
                    <div class="rounded-2xl border border-teal-100 bg-teal-50/70 p-5 shadow-sm"><p class="text-xs text-teal-700">سمت‌های سازمانی</p><p class="mt-2 text-2xl font-bold text-teal-900">{{ stats.positions }}</p></div>
                </div>

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-5">
                        <h3 class="font-bold text-slate-900">نقشه سازمان</h3>
                        <p class="mt-1 text-xs text-slate-500">هر واحد می‌تواند چند عضو و چند سمت داشته باشد؛ عضویت و سمت دو مفهوم مستقل هستند.</p>
                    </div>
                    <div v-if="!props.departments.length" class="px-6 py-16 text-center text-sm text-slate-500">هنوز واحدی تعریف نشده است.</div>
                    <div v-else class="divide-y divide-slate-100">
                        <div v-for="department in props.departments" :key="department.id" class="p-5 transition hover:bg-slate-50/60">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div class="flex min-w-0 items-start gap-4">
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-teal-50 text-lg font-bold text-teal-700">{{ department.name.charAt(0) }}</div>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h4 class="font-bold text-slate-900">{{ department.name }}</h4>
                                            <span v-if="department.code" dir="ltr" class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-500">{{ department.code }}</span>
                                            <span :class="department.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'" class="rounded-full px-2.5 py-1 text-[11px] font-semibold">{{ department.is_active ? 'فعال' : 'غیرفعال' }}</span>
                                        </div>
                                        <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-500">
                                            <span>والد: {{ department.parent?.name ?? 'ریشه سازمان' }}</span>
                                            <span>مدیر: {{ department.manager?.name ?? 'تعیین نشده' }}</span>
                                            <span>{{ department.positions?.length ?? 0 }} سمت</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="rounded-lg border border-teal-200 bg-teal-50 px-3 py-2 text-xs font-semibold text-teal-700 hover:bg-teal-100" @click="togglePositions(department)">{{ expandedDepartmentId === department.id ? 'بستن سمت‌ها' : 'مدیریت سمت‌ها' }}</button>
                                    <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="openEdit(department)">ویرایش</button>
                                    <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="toggleStatus(department)">{{ department.is_active ? 'غیرفعال' : 'فعال' }}</button>
                                    <button type="button" class="rounded-lg border border-red-100 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50" @click="deleteDepartment(department)">حذف</button>
                                </div>
                            </div>

                            <div v-if="expandedDepartmentId === department.id" class="mt-5 rounded-2xl border border-teal-100 bg-slate-50/70 p-4">
                                <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between"><div><h5 class="font-bold text-slate-900">سمت‌های {{ department.name }}</h5><p class="mt-1 text-xs text-slate-500">برای هر سمت، افراد شاغل را مشخص کنید.</p></div><button type="button" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white" @click="openPositionCreate">+ سمت</button></div>
                                        <div v-if="!(department.positions?.length)" class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center text-xs text-slate-500">هنوز سمتی برای این واحد تعریف نشده است.</div>
                                        <div v-for="position in department.positions" :key="position.id" class="rounded-xl border border-slate-200 bg-white p-4">
                                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                                <div><div class="flex items-center gap-2"><span class="font-semibold text-slate-900">{{ position.name }}</span><span v-if="position.code" dir="ltr" class="text-[10px] text-slate-400">{{ position.code }}</span></div><p v-if="position.description" class="mt-1 text-xs text-slate-500">{{ position.description }}</p></div>
                                                <div class="flex gap-2"><button type="button" class="text-xs font-semibold text-slate-600 hover:text-teal-700" @click="openPositionEdit(position)">ویرایش</button><button type="button" class="text-xs font-semibold text-red-600" @click="deletePosition(department, position)">حذف</button></div>
                                            </div>
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                <span v-for="user in position.users" :key="user.id" class="inline-flex items-center gap-1 rounded-full bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-700">{{ user.name }}<span v-if="user.pivot?.is_primary"> · اصلی</span><button type="button" class="mr-1 text-teal-500 hover:text-red-600" @click="unassignPosition(department, position, user)">×</button></span>
                                                <span v-if="!position.users?.length" class="text-xs text-slate-400">بدون فرد</span>
                                            </div>
                                            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                                <select v-model="assignmentForm.user_id" class="min-w-0 flex-1 rounded-lg border-slate-200 text-xs focus:border-teal-500 focus:ring-teal-500"><option :value="null">انتخاب عضو واحد...</option><option v-for="user in props.users" :key="user.id" :value="user.id">{{ user.name }}</option></select>
                                                <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-600"><input v-model="assignmentForm.is_primary" type="checkbox" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500"> سمت اصلی</label>
                                                <button type="button" :disabled="assignmentForm.processing || !assignmentForm.user_id" class="rounded-lg bg-teal-600 px-3 py-2 text-xs font-semibold text-white disabled:opacity-40" @click="assignPosition(department, position)">تخصیص</button>
                                            </div>
                                        </div>
                                    </div>
                                    <form class="h-fit rounded-xl border border-slate-200 bg-white p-4" @submit.prevent="submitPosition(department)">
                                        <h5 class="font-bold text-slate-900">{{ editingPosition ? 'ویرایش سمت' : 'سمت جدید' }}</h5>
                                        <div class="mt-4 space-y-3">
                                            <input v-model="positionForm.name" required maxlength="150" placeholder="نام سمت" class="w-full rounded-lg border-slate-200 text-sm focus:border-teal-500 focus:ring-teal-500">
                                            <input v-model="positionForm.code" dir="ltr" maxlength="50" placeholder="کد سمت (اختیاری)" class="w-full rounded-lg border-slate-200 text-sm text-left focus:border-teal-500 focus:ring-teal-500">
                                            <textarea v-model="positionForm.description" rows="3" placeholder="شرح مختصر مسئولیت" class="w-full rounded-lg border-slate-200 text-sm focus:border-teal-500 focus:ring-teal-500"></textarea>
                                            <label class="flex items-center gap-2 text-xs font-medium text-slate-600"><input v-model="positionForm.is_active" type="checkbox" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500"> سمت فعال باشد</label>
                                            <p v-if="positionForm.errors.name" class="text-xs text-red-600">{{ positionForm.errors.name }}</p>
                                            <div class="flex gap-2 pt-2"><button type="submit" :disabled="positionForm.processing" class="flex-1 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50">{{ positionForm.processing ? 'در حال ذخیره...' : editingPosition ? 'ذخیره تغییرات' : 'ایجاد سمت' }}</button><button v-if="editingPosition" type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600" @click="openPositionCreate">انصراف</button></div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4"><h3 class="font-bold text-slate-900">{{ editingDepartment ? 'ویرایش واحد' : 'ایجاد واحد جدید' }}</h3><p class="mt-1 text-xs text-slate-500">مدیر واحد همیشه عضو همان واحد نیز خواهد بود.</p></div>
                    <form class="grid gap-4 p-5 md:grid-cols-2 lg:grid-cols-3" @submit.prevent="submitDepartment">
                        <div><label class="mb-1.5 block text-xs font-semibold text-slate-600">نام واحد</label><input v-model="form.name" required maxlength="150" class="w-full rounded-xl border-slate-200 bg-slate-50/50 text-sm focus:border-teal-500 focus:ring-teal-500"><p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p></div>
                        <div><label class="mb-1.5 block text-xs font-semibold text-slate-600">کد واحد</label><input v-model="form.code" dir="ltr" maxlength="50" class="w-full rounded-xl border-slate-200 bg-slate-50/50 text-left text-sm focus:border-teal-500 focus:ring-teal-500"></div>
                        <div><label class="mb-1.5 block text-xs font-semibold text-slate-600">واحد والد</label><select v-model="form.parent_id" class="w-full rounded-xl border-slate-200 text-sm focus:border-teal-500 focus:ring-teal-500"><option :value="null">ریشه سازمان</option><option v-for="d in parentOptions" :key="d.id" :value="d.id">{{ d.name }}</option></select></div>
                        <div><label class="mb-1.5 block text-xs font-semibold text-slate-600">مدیر واحد</label><select v-model="form.manager_id" class="w-full rounded-xl border-slate-200 text-sm focus:border-teal-500 focus:ring-teal-500"><option :value="null">بدون مدیر</option><option v-for="u in props.users" :key="u.id" :value="u.id">{{ u.name }}</option></select></div>
                        <div class="lg:col-span-2"><label class="mb-1.5 block text-xs font-semibold text-slate-600">توضیحات</label><textarea v-model="form.description" rows="2" class="w-full rounded-xl border-slate-200 bg-slate-50/50 text-sm focus:border-teal-500 focus:ring-teal-500"></textarea></div>
                        <div class="flex items-center gap-3"><label class="flex items-center gap-2 text-xs font-semibold text-slate-600"><input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500"> واحد فعال باشد</label><button type="submit" :disabled="form.processing" class="mr-auto rounded-xl bg-slate-900 px-5 py-2.5 text-xs font-semibold text-white disabled:opacity-50">{{ form.processing ? 'در حال ذخیره...' : editingDepartment ? 'ذخیره تغییرات' : 'ایجاد واحد' }}</button></div>
                    </form>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>