<script setup lang="ts">
const props = withDefaults(defineProps<{
    scope: string;
    departmentIds: number[];
    roleIds: number[];
    departments: { id: number; name: string }[];
    roles: { id: number; name: string }[];
    canBroaden?: boolean;
}>(), { canBroaden: true });

const emit = defineEmits<{
    'update:scope': [value: string];
    'update:departmentIds': [value: number[]];
    'update:roleIds': [value: number[]];
}>();

function toggle(type: 'department' | 'role', id: number, event: Event): void {
    const selected = type === 'department' ? props.departmentIds : props.roleIds;
    const checked = (event.target as HTMLInputElement).checked;
    const next = checked ? [...new Set([...selected, id])] : selected.filter(value => value !== id);
    if (type === 'department') {
        emit('update:departmentIds', next);
    } else {
        emit('update:roleIds', next);
    }
}
</script>

<template>
    <fieldset class="space-y-4">
        <legend class="text-sm font-semibold text-slate-900">چه کسانی این سند را ببینند؟</legend>
        <label class="grid gap-2 text-sm">
            <span class="sr-only">دامنه مشاهده سند</span>
            <select :value="scope" class="w-full rounded-lg border-slate-300" @change="emit('update:scope', ($event.target as HTMLSelectElement).value)">
                <option value="department">واحد مرتبط با سند</option>
                <option v-if="canBroaden" value="organization">همه کاربران فعال سازمان</option>
                <option v-if="canBroaden" value="selected">واحدها یا نقش‌های انتخاب‌شده</option>
                <option value="restricted">افراد مشخص</option>
            </select>
        </label>
        <p v-if="scope === 'department'" class="text-sm leading-6 text-slate-500">اعضای فعال واحد مرتبط و واحد نگهدارنده سند می‌توانند فایل را مشاهده و دریافت کنند.</p>
        <p v-else-if="scope === 'organization'" class="rounded-lg bg-amber-50 p-3 text-sm leading-6 text-amber-900">فایل این سند برای همه کاربران فعال سازمان قابل مشاهده و دریافت خواهد بود.</p>
        <p v-else-if="scope === 'restricted'" class="text-sm leading-6 text-slate-500">ثبت‌کننده و افراد دارای دسترسی مستقیم سند را می‌بینند. مسئول مجاز بایگانی می‌تواند افراد را در پرونده سند تعیین کند.</p>
        <div v-if="scope === 'selected'" class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border p-3">
                <h3 class="mb-3 text-sm font-semibold">واحدها</h3>
                <div class="max-h-56 space-y-2 overflow-y-auto">
                    <label v-for="department in departments" :key="department.id" class="flex items-center gap-2 rounded-lg px-2 py-1 text-sm hover:bg-slate-50">
                        <input type="checkbox" :checked="departmentIds.includes(Number(department.id))" class="rounded text-indigo-600" @change="toggle('department', Number(department.id), $event)" />
                        {{ department.name }}
                    </label>
                    <p v-if="!departments.length" class="text-sm text-slate-500">واحد فعالی تعریف نشده است.</p>
                </div>
            </div>
            <div class="rounded-xl border p-3">
                <h3 class="mb-3 text-sm font-semibold">نقش‌ها</h3>
                <div class="max-h-56 space-y-2 overflow-y-auto">
                    <label v-for="role in roles" :key="role.id" class="flex items-center gap-2 rounded-lg px-2 py-1 text-sm hover:bg-slate-50">
                        <input type="checkbox" :checked="roleIds.includes(Number(role.id))" class="rounded text-indigo-600" @change="toggle('role', Number(role.id), $event)" />
                        {{ role.name }}
                    </label>
                    <p v-if="!roles.length" class="text-sm text-slate-500">نقش فعالی تعریف نشده است.</p>
                </div>
            </div>
        </div>
        <p v-if="scope === 'selected'" class="text-sm leading-6 text-slate-500">عضویت فعال در یکی از واحدها یا نقش‌های انتخاب‌شده، اجازه مشاهده و دریافت فایل را می‌دهد.</p>
    </fieldset>
</template>
