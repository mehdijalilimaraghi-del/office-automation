<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Role = 'meta'|'heading'|'page_number'|'issuer'|'header'|'footer'|'repeat'|'body';
type Kind = 'text'|'stamp'|'signature';
type PageFormat = 'number_of'|'slash'|'short'|'page';
interface ElementBox { id:string; kind:Kind; role:Role; label:string; content:string; x:number; y:number; width:number; spacing:number; font_size:number; font_family:string; page_format:PageFormat; bold:boolean; align:'right'|'center'|'left'; visible:boolean; }
interface Token { key:string; label:string; }
const props = defineProps<{ elements:ElementBox[]; pageLayout:{top:number;right:number;bottom:number;left:number;header_height:number;footer_height:number}; tokens:Token[]; letterheadDataUri:string|null; stampDataUri:string|null; defaultFontFamily:string; previewValues:Record<string,string>; previewBody:string }>();
const form = useForm({ elements: props.elements.map(item => ({ ...item, role: item.role ?? 'repeat', spacing: item.spacing ?? 0, font_family: item.font_family ?? 'default', page_format: item.page_format ?? 'number_of' })), page_layout: { ...props.pageLayout } });
const selectedId = ref(form.elements[0]?.id ?? '');
const previewMode = ref(false);
const selected = computed(() => form.elements.find(item => item.id === selectedId.value) ?? null);
const page = ref<HTMLElement|null>(null);
const dragging = ref<{id:string; lastX:number; lastY:number}|null>(null);
const roleLabels:Record<Role,string> = { meta:'شماره و تاریخ', heading:'خطاب و موضوع', page_number:'شماره صفحه', issuer:'تنظیم‌کننده و امضا', header:'سربرگ تکرارشونده', footer:'پاصفحه تکرارشونده', repeat:'سربرگ/پاصفحه قدیمی', body:'متن اصلی نامه' };
const fontOptions = [{value:'default',label:'فونت پیش‌فرض تنظیمات'},{value:'Vazirmatn',label:'وزیرمتن'},{value:'Tahoma',label:'Tahoma'},{value:'Arial',label:'Arial'},{value:'B Nazanin',label:'B Nazanin'},{value:'B Mitra',label:'B Mitra'},{value:'IRANSans',label:'IRANSans'},{value:'Times New Roman',label:'Times New Roman'}];
const roleTokens:Record<Role,string[]> = {
 meta:['{{letter_number}}','{{registration_number}}','{{date}}','{{attachment_pages}}','{{letter_type}}'],
 heading:['{{recipient_name}}','{{recipient_organization}}','{{recipient_unit}}','{{recipient_position}}','{{recipient_title}}','{{subject}}'],
 page_number:[],
 issuer:['{{creator_name}}','{{creator_unit_title}}','{{creator_department_name}}','{{manager_name}}','{{manager_position}}','{{manager_title}}'],
 header:['{{organization_name}}','{{short_name}}','{{address}}','{{tel}}','{{email}}','{{website}}'],
 footer:['{{organization_name}}','{{short_name}}','{{address}}','{{tel}}','{{email}}','{{website}}'],
 repeat:['{{organization_name}}','{{short_name}}','{{address}}','{{tel}}','{{email}}','{{website}}'],
 body:[]
};
const availableTokens = computed(() => props.tokens.filter(token => selected.value && roleTokens[selected.value.role].includes(token.key)));
const flowMeta = computed(() => form.elements.filter(item => item.visible && item.role === 'meta'));
const flowHeading = computed(() => form.elements.filter(item => item.visible && item.role === 'heading'));
const flowIssuer = computed(() => form.elements.filter(item => item.visible && item.role === 'issuer'));
const flowBody = computed(() => form.elements.find(item => item.role === 'body' && item.visible) ?? null);
const repeating = computed(() => form.elements.filter(item => item.visible && ['header','footer','repeat'].includes(item.role)));
const pageNumberEnabled = computed(() => form.elements.some(item => item.visible && item.role === 'page_number'));
const safeAreaStyle = computed(() => ({
 top: (Math.max(form.page_layout.top,form.page_layout.header_height??0) / 297 * 100) + '%',
 right: (form.page_layout.right / 210 * 100) + '%',
 bottom: (Math.max(form.page_layout.bottom,form.page_layout.footer_height??0) / 297 * 100) + '%',
 left: (form.page_layout.left / 210 * 100) + '%',
}));
const contentPreviewStyle = computed(() => ({
 top: Math.max(form.page_layout.top,form.page_layout.header_height??0) + 'mm',
 right: form.page_layout.right + 'mm',
 left: form.page_layout.left + 'mm',
 bottom: Math.max(form.page_layout.bottom,form.page_layout.footer_height??0) + 'mm',
}));
function addRole(role:string):void { if (role in roleLabels) addBox(role as Role); }
function addBox(role:Role, kind:Kind='text'):void {
 const id='item_'+Math.random().toString(36).slice(2,9);
 const labels:Record<Role,string> = roleLabels;
 form.elements.push({id,kind,role,label:kind==='stamp'?'مهر سازمان':kind==='signature'?'امضای مدیر واحد':labels[role],content:role==='page_number'||role==='body'?'':role==='meta'?'شماره: {{letter_number}}\nتاریخ: {{date}}\n{{attachment_pages}}':role==='issuer'?'{{creator_unit_title}}\n{{creator_name}}':'',x:8,y:role==='header'?8:role==='footer'?92:role==='repeat'?8:0,width:84,spacing:role==='body'?42:role==='issuer'?16:0,font_size:role==='meta'?10:12,font_family:'default',page_format:'number_of',bold:role==='heading',align:'right',visible:true});
 selectedId.value=id;
}
function removeBox():void { if(!selected.value)return; form.elements=form.elements.filter(item=>item.id!==selectedId.value);selectedId.value=form.elements[0]?.id??''; }
function insertToken(token:string):void { if(selected.value)selected.value.content+=token; }
function pointerDown(event:PointerEvent,item:ElementBox):void {
 if(!page.value || item.role==='body')return;event.preventDefault();selectedId.value=item.id;
 dragging.value={id:item.id,lastX:event.clientX,lastY:event.clientY};
 (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
}
function roundCoordinate(value:number):number { return Math.round(value*100)/100; }
function normalizeSelectedBounds():void {
 if(!selected.value)return;
 selected.value.width=Math.max(5,Math.min(100,roundCoordinate(Number(selected.value.width)||5)));
 selected.value.x=Math.max(0,Math.min(100-selected.value.width,roundCoordinate(Number(selected.value.x)||0)));
 selected.value.y=Math.max(0,Math.min(100,roundCoordinate(Number(selected.value.y)||0)));
}
function pointerMove(event:PointerEvent):void {
 if(!dragging.value||!page.value)return;
 const rect=page.value.getBoundingClientRect();
 if(rect.width<=0||rect.height<=0)return;
 const dx=(event.clientX-dragging.value.lastX)/rect.width*100;
 const dy=(event.clientY-dragging.value.lastY)/rect.height*100;
 const item=form.elements.find(entry=>entry.id===dragging.value?.id);if(!item)return;
 dragging.value.lastX=event.clientX;
 dragging.value.lastY=event.clientY;
 item.x=roundCoordinate(Math.max(0,Math.min(100-item.width,item.x-dx)));
 item.y=roundCoordinate(Math.max(0,Math.min(100,item.y+dy)));
}
function pointerUp():void{dragging.value=null;}
function resolvePreviewText(content:string):string {
 return content.replace(/\{\{([a-z_]+)\}\}/g,(_token,key:string)=>props.previewValues[key]??'');
}
function displayText(item:ElementBox):string {
 if(item.kind==='stamp')return props.stampDataUri?'': 'مهر سازمان';
 if(item.kind==='signature')return 'محل امضای مدیر واحد';
 if(item.role==='heading'&&!item.content.trim())return [props.previewValues.recipient_title,props.previewValues.recipient_name,props.previewValues.recipient_organization,props.previewValues.recipient_unit,'موضوع: '+props.previewValues.subject].filter(Boolean).join('\n');
 return resolvePreviewText(item.content||item.label);
}
function flowStyle(item:ElementBox):Record<string,string> { return { width:item.width+'%', marginTop:item.spacing+'mm', fontSize:item.font_size+'px', fontWeight:item.bold?'700':'400', textAlign:item.align }; }
function resolvedFont(item:ElementBox):string { return item.font_family==='default'?props.defaultFontFamily:item.font_family; }
function elementStyle(item:ElementBox):Record<string,string> { return { right:item.x+'%', top:item.y+'%', width:item.width+'%', fontSize:item.font_size+'px', fontFamily:resolvedFont(item), fontWeight:item.bold?'700':'400', textAlign:item.align }; }
function bodyPreviewStyle():Record<string,string> { const item=flowBody.value; return { width:(item?.width??84)+'%', marginTop:'0mm', fontSize:(item?.font_size??12)+'px', fontFamily:item?resolvedFont(item):props.defaultFontFamily, fontWeight:item?.bold?'700':'400', textAlign:item?.align??'right' }; }
function issuerPreviewStyle(item:ElementBox):Record<string,string> { return { width:item.width+'%', marginTop:item.spacing+'mm', marginRight:`calc(${item.x*2.1}mm - ${props.pageLayout.right}mm)`, fontSize:item.font_size+'px', fontFamily:resolvedFont(item), fontWeight:item.bold?'700':'400', textAlign:item.align }; }
function pageNumberPreview(item:ElementBox):string { const format=item.page_format??'number_of'; if(format==='slash')return '۱/۵'; if(format==='short')return 'ص۱ از ۵'; if(format==='page')return 'صفحه ۱ از ۵'; return '۱ از ۵'; }
</script>

<template>
 <Head title="طراح قالب نامه"/>
 <AuthenticatedLayout>
  <template #header><div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-xs font-semibold text-teal-700">مکاتبات</p><h2 class="mt-1 text-2xl font-bold text-slate-900">طراح قالب نامه</h2><p class="mt-1 text-sm text-slate-500">نوع هر بخش را مشخص کنید؛ متن اصلی نامه خودکار و با قالب ثبت‌شده درج می‌شود.</p></div><Link :href="route('settings.organization.edit')" class="text-sm font-semibold text-slate-600">بازگشت به تنظیمات سازمان</Link></div></template>
  <div class="bg-slate-50 py-6" dir="rtl">
   <div class="mx-auto grid max-w-[1500px] gap-5 px-4" :class="previewMode ? 'grid-cols-1' : 'xl:grid-cols-[minmax(0,1fr)_340px]'">
    <section class="overflow-auto rounded-2xl border border-slate-200 bg-slate-200 p-4">
     <div class="mx-auto mb-3 flex max-w-[210mm] flex-wrap items-center justify-between gap-2 text-xs text-slate-600"><span>{{ previewMode ? 'پیش‌نمایش نمونه با اطلاعات تنظیمات سازمان' : 'پیش‌نمایش A4 · سربرگ به‌صورت پس‌زمینه' }}</span><button type="button" class="rounded-lg bg-slate-900 px-3 py-2 font-semibold text-white" @click="previewMode = !previewMode">{{ previewMode ? 'بازگشت به ویرایش' : 'پیش‌نمایش قالب' }}</button><div v-if="!previewMode" class="flex flex-wrap gap-2"><button v-for="(label,role) in roleLabels" :key="role" type="button" :disabled="role==='body' && form.elements.some(item=>item.role==='body')" class="rounded-lg bg-white px-3 py-2 font-semibold disabled:cursor-not-allowed disabled:opacity-40" @click="addRole(role)">{{label}} +</button><button type="button" class="rounded-lg bg-white px-3 py-2" @click="addBox('issuer','stamp')">مهر +</button><button type="button" class="rounded-lg bg-white px-3 py-2" @click="addBox('issuer','signature')">امضا +</button></div></div>
     <div ref="page" class="relative mx-auto aspect-[210/297] w-full max-w-[210mm] overflow-hidden bg-white shadow-xl" :style="letterheadDataUri?{backgroundImage:'url('+letterheadDataUri+')',backgroundSize:'100% 100%',backgroundPosition:'center'}:{}" @pointermove="pointerMove" @pointerup="pointerUp" @pointercancel="pointerUp">
      <div v-if="!previewMode" class="pointer-events-none absolute border-y border-dashed border-slate-300" :style="safeAreaStyle"></div>
      <div class="absolute whitespace-pre-wrap text-right" :style="contentPreviewStyle">
       <div v-if="flowBody" class="whitespace-pre-wrap p-3 text-sm leading-8" :class="previewMode?'text-slate-900':'border border-dashed border-slate-300 bg-white/70 text-slate-500'" :style="bodyPreviewStyle()">
        <p v-if="!previewMode" class="mb-2 font-semibold">متن اصلی نامه — درج خودکار</p>
        <p>{{ previewMode ? previewBody : 'متن نامه از این قسمت آغاز می‌شود و در صورت نیاز به صفحات بعد ادامه می‌یابد.' }}</p>
       </div>
       <div v-for="item in flowIssuer" :key="item.id" class="whitespace-pre-wrap p-2 text-slate-900" :class="previewMode?'':(selectedId===item.id?'border border-dashed border-teal-600 bg-teal-50/80 ring-1 ring-teal-500':'border border-dashed border-slate-300 bg-white/70')" :style="issuerPreviewStyle(item)" @click="selectedId=item.id"><img v-if="item.kind==='stamp'&&stampDataUri" :src="stampDataUri" alt="مهر سازمان" class="pointer-events-none block h-auto w-full max-w-none object-contain"/><span v-else class="pointer-events-none">{{displayText(item)}}</span></div>
      </div>
      <div v-for="item in form.elements.filter(entry=>entry.visible && entry.role!=='body' && entry.role!=='issuer')" :key="item.id" class="absolute whitespace-pre-wrap p-2 text-slate-900" :class="previewMode?'':(selectedId===item.id?'border border-dashed border-teal-600 bg-teal-50/80 ring-1 ring-teal-500':'border border-dashed border-slate-300 bg-white/70')" :style="elementStyle(item)" @pointerdown="!previewMode&&pointerDown($event,item)"><span class="pointer-events-none"><bdi v-if="item.role==='page_number'" dir="ltr">{{pageNumberPreview(item)}}</bdi><template v-else>{{displayText(item)}}</template></span></div>
     </div>
    </section>
    <aside v-if="!previewMode" class="space-y-4">
     <section class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
      <h3 class="font-bold">محدوده چاپ A4</h3>
      <p class="text-xs leading-5 text-slate-500">این تنظیمات فضای امن متن نامه را از لبه‌های کاغذ مشخص می‌کنند. فضای سربرگ و پاصفحه جداگانه رزرو می‌شود تا متن وارد محدوده آن‌ها نشود.</p>
      <div class="grid grid-cols-2 gap-2">
       <label class="text-xs">فاصله متن از بالا (mm)<input v-model.number="form.page_layout.top" type="number" min="5" max="60" class="mt-1 w-full rounded-lg border-slate-300"/></label>
       <label class="text-xs">فاصله متن از پایین (mm)<input v-model.number="form.page_layout.bottom" type="number" min="5" max="60" class="mt-1 w-full rounded-lg border-slate-300"/></label>
       <label class="text-xs">فاصله متن از راست (mm)<input v-model.number="form.page_layout.right" type="number" min="5" max="50" class="mt-1 w-full rounded-lg border-slate-300"/></label>
       <label class="text-xs">فاصله متن از چپ (mm)<input v-model.number="form.page_layout.left" type="number" min="5" max="50" class="mt-1 w-full rounded-lg border-slate-300"/></label>
       <label class="text-xs">فضای رزروشده سربرگ (mm)<input v-model.number="form.page_layout.header_height" type="number" min="0" max="100" class="mt-1 w-full rounded-lg border-slate-300"/></label>
       <label class="text-xs">فضای رزروشده پاصفحه (mm)<input v-model.number="form.page_layout.footer_height" type="number" min="0" max="70" class="mt-1 w-full rounded-lg border-slate-300"/></label>
      </div>
     </section>
     <section class="rounded-2xl border border-slate-200 bg-white p-4"><div class="mb-3 flex items-center justify-between"><h3 class="font-bold">بخش‌های قالب</h3><button type="button" class="text-xs text-rose-600" @click="removeBox">حذف انتخابی</button></div><div class="space-y-1"><button v-for="item in form.elements" :key="item.id" type="button" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm" :class="selectedId===item.id?'bg-teal-50 text-teal-800':'hover:bg-slate-50'" @click="selectedId=item.id"><span>{{item.label}}</span><span class="text-xs text-slate-400">{{roleLabels[item.role]}}</span></button></div></section>
     <section v-if="selected" class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
      <h3 class="font-bold">تنظیمات بخش</h3>
      <label class="block text-xs font-semibold">نوع بخش<select v-model="selected.role" class="mt-1 w-full rounded-lg border-slate-300 text-sm"><option v-for="(label,role) in roleLabels" :key="role" :value="role">{{label}}</option></select></label>
      <label class="block text-xs font-semibold">عنوان<input v-model="selected.label" class="mt-1 w-full rounded-lg border-slate-300 text-sm"/></label>
      <label v-if="selected.kind==='text' && !['page_number','body'].includes(selected.role)" class="block text-xs font-semibold">متن / الگو<textarea v-model="selected.content" rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm"/></label>
      <p v-if="selected.role==='page_number'" class="rounded-lg bg-slate-50 p-2 text-xs leading-5">قالب شماره صفحه در چاپ با شمارنده واقعی صفحات مرورگر ساخته می‌شود. جایگاه چاپی از نزدیک‌ترین ناحیه حاشیه‌ای (بالا/پایین و راست/وسط/چپ) متناظر با مختصات المان انتخاب می‌شود؛ برای جای‌گذاری دقیق‌تر، آن را داخل حاشیه بالا یا پایین قرار دهید. ترتیب اعداد و عبارت «از» مستقل از راست‌به‌چپ بودن متن تثبیت شده است.</p>
      <p v-if="selected.role==='body'" class="rounded-lg bg-slate-50 p-2 text-xs leading-5">این المان محتوای نامه را کنترل نمی‌کند؛ فاصله شروع متن، عرض و قلم بدنه را تنظیم می‌کند. متن واقعی به‌صورت خودکار از نامه خوانده می‌شود.</p>
      <p v-if="selected.role==='issuer'" class="rounded-lg bg-slate-50 p-2 text-xs leading-5">این بخش پس از متن نامه قرار می‌گیرد. برای المان متنی این بخش، متغیرهای نام تنظیم‌کننده، عنوان و نام واحد ایجادکننده، نام مدیر واحد و سمت/عنوان سازمانی مدیر در دسترس است. فاصله منفی باعث هم‌پوشانی با بخش قبلی می‌شود؛ برای قرار گرفتن مهر یا امضا روی نام تنظیم‌کننده از مقدار منفی استفاده کنید.</p>
      <p v-if="selected.role==='repeat'" class="rounded-lg bg-slate-50 p-2 text-xs leading-5">این بخش در مختصات تعیین‌شده در تمام صفحات تکرار می‌شود؛ برای هدر آن را نزدیک بالای صفحه و برای فوتر نزدیک پایین صفحه قرار دهید. محدوده بدنه چاپ با حاشیه‌های قابل تنظیم در بخش «محدوده چاپ A4» محافظت می‌شود.</p>
      <div class="grid grid-cols-2 gap-2">
       <label class="text-xs">عرض (%)<input v-model.number="selected.width" type="number" min="5" :max="100-selected.x" @change="normalizeSelectedBounds" class="mt-1 w-full rounded-lg border-slate-300"/></label>
       <label v-if="selected.role==='body' || selected.role==='issuer'" class="text-xs">فاصله از بخش قبل (mm)<input v-model.number="selected.spacing" type="number" min="-80" max="80" class="mt-1 w-full rounded-lg border-slate-300"/></label>
       <label v-if="['meta','heading','repeat','page_number'].includes(selected.role)" class="text-xs">فاصله از بالای صفحه (%)<input v-model.number="selected.y" type="number" min="0" max="100" class="mt-1 w-full rounded-lg border-slate-300"/></label>
       <label v-if="selected.role!=='body'" class="text-xs">فاصله از راست (%)<input v-model.number="selected.x" type="number" min="0" :max="100-selected.width" @change="normalizeSelectedBounds" class="mt-1 w-full rounded-lg border-slate-300"/></label>
       <label v-if="selected.kind==='text' && selected.role!=='page_number'" class="text-xs">اندازه قلم<input v-model.number="selected.font_size" type="number" min="7" max="48" class="mt-1 w-full rounded-lg border-slate-300"/></label>
       <label v-if="selected.kind==='text' && selected.role!=='page_number'" class="text-xs">نوع قلم<select v-model="selected.font_family" class="mt-1 w-full rounded-lg border-slate-300"><option v-for="font in fontOptions" :key="font.value" :value="font.value">{{font.label}}</option></select></label>
       <label v-if="selected.role==='page_number'" class="col-span-2 text-xs">قالب شماره صفحه<select v-model="selected.page_format" class="mt-1 w-full rounded-lg border-slate-300"><option value="number_of">۱ از ۵</option><option value="slash">۱/۵</option><option value="short">ص۱ از ۵</option><option value="page">صفحه ۱ از ۵</option></select></label>
       <label class="text-xs">تراز<select v-model="selected.align" class="mt-1 w-full rounded-lg border-slate-300"><option value="right">راست</option><option value="center">وسط</option><option value="left">چپ</option></select></label>
      </div>
      <label v-if="selected.kind==='text' && selected.role!=='page_number'" class="flex items-center gap-2 text-sm"><input v-model="selected.bold" type="checkbox"/> قلم ضخیم</label>
      <label class="flex items-center gap-2 text-sm"><input v-model="selected.visible" type="checkbox"/> نمایش در خروجی</label>
      <div v-if="selected.kind==='text' && availableTokens.length" class="border-t pt-3"><p class="mb-2 text-xs font-bold">متغیرهای مرتبط با این بخش</p><div class="flex flex-wrap gap-1.5"><button v-for="token in availableTokens" :key="token.key" type="button" class="rounded-md bg-slate-100 px-2 py-1 text-xs hover:bg-teal-50" :title="token.key" @click="insertToken(token.key)">{{token.label}}</button></div></div>
     </section>
     <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-6 text-amber-900">محتوای نامه به‌صورت خودکار از متن ثبت‌شده نامه درج می‌شود؛ المان «متن اصلی نامه» فقط محل شروع، عرض و ظاهر بدنه را کنترل می‌کند. فاصله منفی امکان هم‌پوشانی مهر و امضا با نام تنظیم‌کننده را فراهم می‌کند. اجزای تکرارشونده (هدر و فوتر) و شماره صفحه در همه صفحات چاپ نمایش داده می‌شوند. حاشیه‌های چاپ A4 از بخش «محدوده چاپ A4» قابل تنظیم هستند؛ فاصله اولیه بدنه فقط در شروع متن اعمال می‌شود و در صفحه‌های بعد تکرار نمی‌شود.</div>
     <PrimaryButton class="w-full justify-center" :disabled="form.processing" @click="form.put(route('correspondence.template.update'))">ذخیره قالب نامه</PrimaryButton><InputError :message="form.errors.elements"/>
    </aside>
   </div>
  </div>
 </AuthenticatedLayout>
</template>