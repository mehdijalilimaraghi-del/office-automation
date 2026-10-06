<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CustomFieldsForm from '@/Components/CustomFieldsForm.vue';

type CustomField = {
    id: number;
    key: string;
    label: string;
    type: 'text' | 'textarea' | 'number' | 'date' | 'boolean' | 'select';
    is_required: boolean;
    options: string[] | null;
};

type CustomFieldValue = {
    value: string;
    field: { key: string; type: string };
};

type Organization={id:number;name:string;code:string|null;phone:string|null;email:string|null;address:string|null;status:string;departments_count:number;contacts_count:number;customFieldValues?:CustomFieldValue[]};
type Department={id:number;external_organization_id:number;parent_id:number|null;name:string;code:string|null;phone:string|null;extension:string|null;email:string|null;status:string;organization?:{id:number;name:string};customFieldValues?:CustomFieldValue[]};
type Contact={id:number;external_organization_id:number;external_department_id:number|null;code:string|null;name:string;position:string|null;mobile:string|null;phone:string|null;extension:string|null;email:string|null;notes:string|null;status:string;organization?:{id:number;name:string};department?:{id:number;name:string};customFieldValues?:CustomFieldValue[]};

const props=defineProps<{
    organizations: Organization[];
    departments: Department[];
    contacts: Contact[];
    customFields: {
        organization: CustomField[];
        department: CustomField[];
        contact: CustomField[];
    };
}>();
const page=usePage();
const tab=ref<'organizations'|'departments'|'contacts'>('organizations');
const saving=ref(false);
const orgForm=reactive<any>({id:null,name:'',phone:'',email:'',address:'',status:'active',custom_fields:{}});
const depForm=reactive<any>({id:null,external_organization_id:'',parent_id:'',name:'',phone:'',extension:'',email:'',status:'active',custom_fields:{}});
const contactForm=reactive<any>({id:null,external_organization_id:'',external_department_id:'',name:'',position:'',mobile:'',phone:'',extension:'',email:'',notes:'',status:'active',custom_fields:{}});

const departmentsForContact=computed(()=>props.departments.filter(d=>String(d.external_organization_id)===String(contactForm.external_organization_id)));
const departmentsForEdit=computed(()=>props.departments.filter(d=>String(d.external_organization_id)===String(depForm.external_organization_id) && d.id!==depForm.id));

type DepartmentTreeRow = Department & { depth: number };

function buildDepartmentTree(items: Department[], parentId: number | null = null, depth = 0): DepartmentTreeRow[] {
    return items
        .filter((department) => {
            const departmentParentId = department.parent_id === null || department.parent_id === undefined
                ? null
                : Number(department.parent_id);

            return departmentParentId === parentId;
        })
        .sort((a, b) => a.name.localeCompare(b.name, 'fa'))
        .flatMap((department) => [
            { ...department, depth },
            ...buildDepartmentTree(items, department.id, depth + 1),
        ]);
}

const departmentTreeRows = computed<DepartmentTreeRow[]>(() => buildDepartmentTree(props.departments));

const departmentParentOptions = computed<DepartmentTreeRow[]>(() =>
    buildDepartmentTree(
        props.departments.filter(
            (department) =>
                String(department.external_organization_id) === String(depForm.external_organization_id) &&
                department.id !== depForm.id,
        ),
    ),
);
const activeOrgCount=computed(()=>props.organizations.filter(x=>x.status==='active').length);
const activeContactCount=computed(()=>props.contacts.filter(x=>x.status==='active').length);
const errors=computed(()=>page.props.errors as Record<string,string>);

function reset(f:any){
    for(const k of Object.keys(f)){
        f[k]=k==='status'?'active':k==='id'?null:k==='custom_fields'?{}:'';
    }
}

function customFieldValueMap(values?: CustomFieldValue[]): Record<string, string | number | boolean | null> {
    return Object.fromEntries(
        (values ?? []).map((item) => [
            item.field.key,
            item.field.type === 'number'
                ? Number(item.value)
                : item.field.type === 'boolean'
                    ? item.value === '1'
                    : item.value,
        ]),
    );
}

function edit(f:any,item:any){
    Object.assign(f,item);
    f.custom_fields = customFieldValueMap(item.customFieldValues);
    delete f.customFieldValues;
    delete f.code;
    if(f===depForm) f.parent_id=item.parent_id??'';
    if(f===contactForm) f.external_department_id=item.external_department_id??'';
    window.scrollTo({top:0,behavior:'smooth'});
}
function submit(f:any,base:string){
    saving.value=true;
    const id=f.id; const payload={...f}; delete payload.id;
    router[id?'put':'post'](id?route(base+'.update',id):route(base+'.store'),payload,{preserveScroll:true,onFinish:()=>saving.value=false,onSuccess:()=>reset(f)});
}
function destroy(base:string,id:number){if(confirm('این مورد حذف شود؟')) router.delete(route(base,id),{preserveScroll:true})}
function goBack(){if(window.history.length>1){window.history.back()}else{router.visit(route('dashboard'))}}
const tabs=[['organizations','سازمان‌ها'],['departments','واحدها'],['contacts','افراد']] as const;
</script>

<template>
    <Head title="سازمان‌های بیرونی" />

    <AuthenticatedLayout>
        <template #header>
          <div class="flex items-center justify-between gap-4">
            <div>
              <h2 class="text-xl font-semibold">سازمان‌های بیرونی</h2>
              <p class="mt-1 text-sm text-muted-foreground">مدیریت سازمان‌ها، واحدها و افراد طرف تعامل</p>
            </div>
            <button
              type="button"
              @click="goBack"
              class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50"
            >
              بازگشت
            </button>
          </div>
        </template>

<div class="min-h-screen bg-slate-50/70 p-4 sm:p-6 lg:p-8" dir="rtl">
  <div class="mx-auto max-w-7xl space-y-6">
    <section class="overflow-hidden rounded-3xl bg-gradient-to-l from-slate-950 via-slate-900 to-teal-950 p-6 text-white shadow-xl sm:p-8">
      <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-bold text-teal-200">ارتباطات سازمانی</p>
          <h1 class="mt-2 text-2xl font-black sm:text-3xl">برون‌سازمانی</h1>
          <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">سازمان‌ها، واحدها و افراد طرف تعامل را یکپارچه مدیریت کنید.</p>
        </div>
        <div class="grid grid-cols-3 gap-2 sm:gap-3">
          <div class="rounded-2xl bg-white/10 px-4 py-3 text-center ring-1 ring-white/10"><div class="text-xl font-black">{{ organizations.length }}</div><div class="text-[11px] text-slate-300">سازمان</div></div>
          <div class="rounded-2xl bg-white/10 px-4 py-3 text-center ring-1 ring-white/10"><div class="text-xl font-black">{{ departments.length }}</div><div class="text-[11px] text-slate-300">واحد</div></div>
          <div class="rounded-2xl bg-white/10 px-4 py-3 text-center ring-1 ring-white/10"><div class="text-xl font-black">{{ activeContactCount }}</div><div class="text-[11px] text-slate-300">فرد فعال</div></div>
        </div>
      </div>
    </section>

    <div class="grid gap-3 sm:grid-cols-3">
      <button v-for="x in tabs" :key="x[0]" @click="tab=x[0]" class="rounded-2xl border px-4 py-4 text-right transition" :class="tab===x[0]?'border-teal-200 bg-teal-50 text-teal-800 shadow-sm':'border-slate-200 bg-white text-slate-600 hover:border-slate-300'">
        <span class="text-sm font-bold">{{ x[1] }}</span>
        <span class="mt-1 block text-xs text-slate-400">{{ x[0]==='organizations'?organizations.length:x[0]==='departments'?departments.length:contacts.length }} مورد</span>
      </button>
    </div>

    <section v-if="tab==='organizations'" class="grid gap-6 lg:grid-cols-[360px_1fr]">
      <form @submit.prevent="submit(orgForm,'external-parties.organizations')" class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="mb-5"><h2 class="font-black text-slate-900">{{ orgForm.id?'ویرایش سازمان':'سازمان جدید' }}</h2><p class="mt-1 text-xs text-slate-500">اطلاعات پایه طرف خارجی</p></div>
        <div class="space-y-3">
          <label class="block text-sm font-semibold text-slate-700">نام سازمان</label>
          <input v-model="orgForm.name" required placeholder="نام سازمان" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 outline-none focus:border-teal-500">
          
          <input v-model="orgForm.phone" placeholder="تلفن" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 outline-none focus:border-teal-500" dir="ltr">
          <input v-model="orgForm.email" type="email" placeholder="ایمیل" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 outline-none focus:border-teal-500" dir="ltr">
          <textarea v-model="orgForm.address" rows="3" placeholder="نشانی" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 outline-none focus:border-teal-500"></textarea>
          <p v-if="errors.name" class="text-xs text-red-600">{{ errors.name }}</p>
        </div>
                <CustomFieldsForm
          v-if="props.customFields.organization.length"
          v-model="orgForm.custom_fields"
          :fields="props.customFields.organization"
          :errors="errors"
          title="اطلاعات تکمیلی سازمان"
          description="اطلاعات اختصاصی تعریف‌شده برای این سازمان را در این بخش ثبت کنید."
        />
        <div class="mt-5 flex gap-2"><button :disabled="saving" class="flex-1 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50">{{saving?'در حال ذخیره…':orgForm.id?'ذخیره تغییرات':'ثبت سازمان'}}</button><button v-if="orgForm.id" type="button" @click="reset(orgForm)" class="rounded-xl border px-4 py-2.5 text-sm">انصراف</button></div>
      </form>
      <div class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="border-b p-5"><div class="flex items-center justify-between"><h2 class="font-black">فهرست سازمان‌ها</h2><span class="text-xs text-slate-400">{{ activeOrgCount }} فعال</span></div></div>
        <div v-if="!organizations.length" class="p-12 text-center text-sm text-slate-400">هنوز سازمانی ثبت نشده است.</div>
        <div v-else class="divide-y divide-slate-100">
          <div v-for="o in organizations" :key="o.id" class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0"><div class="flex items-center gap-2"><h3 class="truncate font-bold text-slate-900">{{o.name}}</h3><span class="rounded-full px-2 py-0.5 text-[10px] font-bold" :class="o.status==='active'?'bg-emerald-50 text-emerald-700':'bg-slate-100 text-slate-500'">{{o.status==='active'?'فعال':'غیرفعال'}}</span></div><p class="mt-1 text-xs text-slate-500">{{o.phone||'بدون تلفن'}} · {{o.departments_count}} واحد · {{o.contacts_count}} فرد</p><p class="mt-1 font-mono text-[10px] text-slate-400" dir="ltr">کد سیستمی: {{o.code||'—'}}</p></div>
            <div class="flex gap-2"><button @click="edit(orgForm,o)" class="rounded-lg px-3 py-2 text-xs font-bold text-teal-700 hover:bg-teal-50">ویرایش</button><button @click="destroy('external-parties.organizations.destroy',o.id)" class="rounded-lg px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50">حذف</button></div>
          </div>
        </div>
      </div>
    </section>

    <section v-if="tab==='departments'" class="grid gap-6 lg:grid-cols-[360px_1fr]">
      <form @submit.prevent="submit(depForm,'external-parties.departments')" class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="mb-5"><h2 class="font-black text-slate-900">{{depForm.id?'ویرایش واحد':'واحد جدید'}}</h2></div>
        <div class="space-y-3">
          <label class="block text-sm font-semibold text-slate-700">سازمان</label>
          <select v-model="depForm.external_organization_id" required class="w-full rounded-xl border border-slate-200 px-3 py-2.5"><option value="">سازمان *</option><option v-for="o in organizations" :key="o.id" :value="o.id">{{o.name}}</option></select>
          <label class="block text-sm font-semibold text-slate-700">واحد بالادست</label>
          <select v-model="depForm.parent_id" class="w-full rounded-xl border border-slate-200 px-3 py-2.5">
            <option value="">بدون واحد بالادست (واحد اصلی)</option>
            <option v-for="d in departmentParentOptions" :key="d.id" :value="d.id">
              {{ '　'.repeat(d.depth) }}{{ d.depth ? '└─ ' : '' }}{{ d.name }}
            </option>
          </select>
          <label class="block text-sm font-semibold text-slate-700">نام واحد</label>
          <input v-model="depForm.name" required placeholder="نام واحد" class="w-full rounded-xl border border-slate-200 px-3 py-2.5">
          
          <div class="grid grid-cols-2 gap-3"><input v-model="depForm.phone" placeholder="تلفن" class="rounded-xl border border-slate-200 px-3 py-2.5" dir="ltr"><input v-model="depForm.extension" placeholder="داخلی" class="rounded-xl border border-slate-200 px-3 py-2.5" dir="ltr"></div>
          <input v-model="depForm.email" type="email" placeholder="ایمیل" class="w-full rounded-xl border border-slate-200 px-3 py-2.5" dir="ltr">
        </div>
                <CustomFieldsForm
          v-if="props.customFields.department.length"
          v-model="depForm.custom_fields"
          :fields="props.customFields.department"
          :errors="errors"
          title="اطلاعات تکمیلی واحد"
          description="اطلاعات اختصاصی تعریف‌شده برای این واحد را در این بخش ثبت کنید."
        />
        <div class="mt-5 flex gap-2"><button :disabled="saving" class="flex-1 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50">{{saving?'در حال ذخیره…':depForm.id?'ذخیره تغییرات':'ثبت واحد'}}</button><button v-if="depForm.id" type="button" @click="reset(depForm)" class="rounded-xl border px-4 py-2.5 text-sm">انصراف</button></div>
      </form>
      <div class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="border-b p-5"><h2 class="font-black">واحدهای ثبت‌شده</h2></div>
        <div v-if="!departments.length" class="p-12 text-center text-sm text-slate-400">هنوز واحدی ثبت نشده است.</div>
        <div v-else class="divide-y divide-slate-100">
          <div v-for="d in departmentTreeRows" :key="d.id" class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between" :class="d.depth ? 'bg-slate-50/60' : 'bg-white'">
            <div class="min-w-0" :style="{ paddingRight: `${d.depth * 24}px` }">
              <div class="flex items-center gap-2">
                <span v-if="d.depth" class="text-slate-300">└─</span>
                <h3 class="font-bold text-slate-900">{{d.name}}</h3>
              </div>
              <p class="mt-1 text-xs text-slate-500">{{d.organization?.name||'—'}}<span v-if="d.parent_id"> · زیرمجموعه واحد بالادست</span> · {{d.phone||'بدون تلفن'}}<span v-if="d.extension"> · داخلی {{d.extension}}</span></p>
              <p class="mt-1 font-mono text-[10px] text-slate-400" dir="ltr">کد سیستمی: {{d.code||'—'}}</p>
            </div>
            <div class="flex gap-2"><button @click="edit(depForm,d)" class="rounded-lg px-3 py-2 text-xs font-bold text-teal-700 hover:bg-teal-50">ویرایش</button><button @click="destroy('external-parties.departments.destroy',d.id)" class="rounded-lg px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50">حذف</button></div>
          </div>
        </div>
      </div>
    </section>

    <section v-if="tab==='contacts'" class="grid gap-6 lg:grid-cols-[360px_1fr]">
      <form @submit.prevent="submit(contactForm,'external-parties.contacts')" class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="mb-5"><h2 class="font-black text-slate-900">{{contactForm.id?'ویرایش فرد':'فرد جدید'}}</h2></div>
        <div class="space-y-3">
          <label class="block text-sm font-semibold text-slate-700">سازمان</label>
          <select v-model="contactForm.external_organization_id" required class="w-full rounded-xl border border-slate-200 px-3 py-2.5"><option value="">سازمان *</option><option v-for="o in organizations" :key="o.id" :value="o.id">{{o.name}}</option></select>
          <select v-model="contactForm.external_department_id" class="w-full rounded-xl border border-slate-200 px-3 py-2.5"><option value="">واحد اختیاری</option><option v-for="d in departmentsForContact" :key="d.id" :value="d.id">{{d.name}}</option></select>
          <label class="block text-sm font-semibold text-slate-700">نام و نام خانوادگی</label>
          <input v-model="contactForm.name" required placeholder="نام و نام خانوادگی" class="w-full rounded-xl border border-slate-200 px-3 py-2.5">
          <input v-model="contactForm.position" placeholder="سمت" class="w-full rounded-xl border border-slate-200 px-3 py-2.5">
          <div class="grid grid-cols-2 gap-3"><input v-model="contactForm.mobile" placeholder="موبایل" class="rounded-xl border border-slate-200 px-3 py-2.5" dir="ltr"><input v-model="contactForm.phone" placeholder="تلفن ثابت" class="rounded-xl border border-slate-200 px-3 py-2.5" dir="ltr"></div>
          <input v-model="contactForm.email" type="email" placeholder="ایمیل" class="w-full rounded-xl border border-slate-200 px-3 py-2.5" dir="ltr">
          <textarea v-model="contactForm.notes" rows="3" placeholder="یادداشت" class="w-full rounded-xl border border-slate-200 px-3 py-2.5"></textarea>
        </div>
                <CustomFieldsForm
          v-if="props.customFields.contact.length"
          v-model="contactForm.custom_fields"
          :fields="props.customFields.contact"
          :errors="errors"
          title="اطلاعات تکمیلی مخاطب"
          description="اطلاعات اختصاصی تعریف‌شده برای این مخاطب را در این بخش ثبت کنید."
        />
        <div class="mt-5 flex gap-2"><button :disabled="saving" class="flex-1 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50">{{saving?'در حال ذخیره…':contactForm.id?'ذخیره تغییرات':'ثبت فرد'}}</button><button v-if="contactForm.id" type="button" @click="reset(contactForm)" class="rounded-xl border px-4 py-2.5 text-sm">انصراف</button></div>
      </form>
      <div class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="border-b p-5"><h2 class="font-black">افراد طرف تعامل</h2></div>
        <div v-if="!contacts.length" class="p-12 text-center text-sm text-slate-400">هنوز فردی ثبت نشده است.</div>
        <div v-else class="divide-y divide-slate-100">
          <div v-for="c in contacts" :key="c.id" class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between"><div class="min-w-0"><h3 class="font-bold text-slate-900">{{c.name}}</h3><p class="mt-1 text-xs text-slate-500">{{c.position||'بدون سمت'}} · {{c.organization?.name||'—'}}{{c.department?.name?' · '+c.department.name:''}}</p><p class="mt-1 text-xs text-slate-400" dir="ltr">{{c.mobile||c.phone||'بدون شماره'}}{{c.email?' · '+c.email:''}}</p><p class="mt-1 font-mono text-[10px] text-slate-400" dir="ltr">کد سیستمی: {{c.code||'—'}}</p></div><div class="flex gap-2"><button @click="edit(contactForm,c)" class="rounded-lg px-3 py-2 text-xs font-bold text-teal-700 hover:bg-teal-50">ویرایش</button><button @click="destroy('external-parties.contacts.destroy',c.id)" class="rounded-lg px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50">حذف</button></div></div>
        </div>
      </div>
    </section>
  </div>
</div>
    </AuthenticatedLayout>
</template>