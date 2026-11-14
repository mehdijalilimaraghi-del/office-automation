<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch, nextTick } from 'vue';

type Role = 'meta'|'heading'|'page_number'|'issuer'|'header'|'footer'|'repeat'|'body';
type Kind = 'text'|'stamp'|'signature';
type PageFormat = 'number_of'|'slash'|'short'|'page';
interface ElementBox { id:string; kind:Kind; role:Role; label:string; content:string; x:number; y:number; width:number; spacing:number; font_size:number; font_family:string; page_format:PageFormat; bold:boolean; align:'right'|'center'|'left'; visible:boolean; }
interface Token { key:string; label:string; }
const props = defineProps<{ elements:ElementBox[]; pageLayout:{top:number;right:number;bottom:number;left:number;header_height:number;footer_height:number}; pageSize:'A4'|'A5'; tokens:Token[]; letterheadDataUri:string|null; stampDataUri:string|null; defaultFontFamily:string; previewValues:Record<string,string>; previewBody:string }>();
const pageWidthMm = computed(() => props.pageSize === 'A5' ? 148 : 210);
const pageHeightMm = computed(() => props.pageSize === 'A5' ? 210 : 297);
const form = useForm({ elements: props.elements.map(item => ({ ...item, role: item.role ?? 'repeat', spacing: item.spacing ?? 0, font_family: item.font_family ?? 'default', page_format: item.page_format ?? 'number_of' })), page_layout: { ...props.pageLayout } });
const selectedId = ref(form.elements[0]?.id ?? '');
const previewMode = ref(false);
const showPageLayoutDialog = ref(false);
const selected = computed(() => form.elements.find(item => item.id === selectedId.value) ?? null);
const page = ref<HTMLElement|null>(null);
const measuredBodyOffsetMm = ref(0);
let previewLayoutObserver:ResizeObserver|null=null;
const dragging = ref<{id:string; lastX:number; lastY:number}|null>(null);
const roleLabels:Record<Role,string> = { meta:'شماره و تاریخ', heading:'خطاب و موضوع', page_number:'شماره صفحه', issuer:'تنظیم‌کننده و امضا', header:'سربرگ تکرارشونده', footer:'پاصفحه تکرارشونده', repeat:'سربرگ/پاصفحه قدیمی', body:'متن اصلی نامه' };
const addableRoles = computed(() => Object.fromEntries(Object.entries(roleLabels).filter(([role]) => role !== 'repeat')) as Partial<Record<Role,string>>);
const fontOptions = [{value:'default',label:'فونت پیش‌فرض تنظیمات'},{value:'Vazirmatn',label:'وزیرمتن'},{value:'Tahoma',label:'Tahoma'},{value:'Arial',label:'Arial'},{value:'B Nazanin',label:'B Nazanin'},{value:'B Mitra',label:'B Mitra'},{value:'IRANSans',label:'IRANSans'},{value:'Times New Roman',label:'Times New Roman'}];
const roleTokens:Record<Role,string[]> = {
 meta:['{{letter_number}}','{{registration_number}}','{{date}}','{{attachment_pages}}','{{letter_type}}'],
 heading:['{{recipient_name}}','{{recipient_organization}}','{{recipient_unit}}','{{recipient_position}}','{{recipient_organizational_title_prefix}}','{{recipient_personal_title_prefix}}','{{recipient_title}}','{{subject}}'],
 page_number:[],
 issuer:['{{creator_name}}','{{creator_unit_title}}','{{creator_department_name}}','{{manager_name}}','{{manager_position}}','{{manager_title}}'],
 header:['{{organization_name}}','{{short_name}}','{{address}}','{{postal_code}}','{{tel}}','{{email}}','{{website}}'],
 footer:['{{organization_name}}','{{short_name}}','{{address}}','{{postal_code}}','{{tel}}','{{email}}','{{website}}'],
 repeat:['{{organization_name}}','{{short_name}}','{{address}}','{{postal_code}}','{{tel}}','{{email}}','{{website}}'],
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
 top: (Math.max(form.page_layout.top,form.page_layout.header_height??0) / pageHeightMm.value * 100) + '%',
 right: (form.page_layout.right / pageWidthMm.value * 100) + '%',
 bottom: (Math.max(form.page_layout.bottom,form.page_layout.footer_height??0) / pageHeightMm.value * 100) + '%',
 left: (form.page_layout.left / pageWidthMm.value * 100) + '%',
}));
const headerZoneStyle = computed(() => ({ height: Math.max(form.page_layout.top,form.page_layout.header_height??0)+'mm' }));
const footerZoneStyle = computed(() => ({ height: Math.max(form.page_layout.bottom,form.page_layout.footer_height??0)+'mm' }));
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
 form.elements.push({id,kind,role,label:kind==='stamp'?'مهر سازمان':kind==='signature'?'امضای مدیر واحد':labels[role],content:role==='page_number'||role==='body'?'':role==='meta'?'شماره: {{letter_number}}\nتاریخ: {{date}}\n{{attachment_pages}}':role==='issuer'?'{{creator_unit_title}}\n{{creator_name}}':'',x:8,y:role==='header'?8:role==='footer'?92:role==='repeat'?8:0,width:84,spacing:role==='issuer'?16:role==='body'?12:0,font_size:role==='meta'?10:12,font_family:'default',page_format:'number_of',bold:role==='heading',align:'right',visible:true});
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
function coordinateMm(field:'x'|'y'|'width'):number {
 if(!selected.value)return 0;
 return Math.round(selected.value[field]*(field==='y'?pageHeightMm.value:pageWidthMm.value)/100*10)/10;
}
function updateCoordinateMm(field:'x'|'y'|'width',event:Event):void {
 if(!selected.value)return;
 const value=Number((event.target as HTMLInputElement).value);
 if(!Number.isFinite(value))return;
 selected.value[field]=roundCoordinate(value/(field==='y'?pageHeightMm.value:pageWidthMm.value)*100);
 normalizeSelectedBounds();
}
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
function updateBodyPreviewOffset():void {
 if(!page.value)return;
 const rect=page.value.getBoundingClientRect();
 const pxPerMm=rect.height/pageHeightMm.value;
 if(pxPerMm<=0)return;
 const safeTop=rect.top+Math.max(form.page_layout.top,form.page_layout.header_height??0)*pxPerMm;
 const preceding=Array.from(page.value.querySelectorAll<HTMLElement>('.template-flow-previous'));
 const lastBottom=preceding.reduce((bottom,element)=>Math.max(bottom,element.getBoundingClientRect().bottom),safeTop);
 measuredBodyOffsetMm.value=Math.max(0,(lastBottom-safeTop)/pxPerMm);
}
async function observePreviewLayout():Promise<void> {
 await nextTick();
 updateBodyPreviewOffset();
 previewLayoutObserver?.disconnect();
 if(typeof ResizeObserver!=='undefined'&&page.value){
  previewLayoutObserver=new ResizeObserver(()=>updateBodyPreviewOffset());
  previewLayoutObserver.observe(page.value);
  page.value.querySelectorAll<HTMLElement>('.template-flow-previous').forEach(element=>previewLayoutObserver?.observe(element));
 }
}
function bodyPreviewStyle():Record<string,string> { const item=flowBody.value; return { width:(item?.width??84)+'%', marginTop:((item?.spacing??0)+measuredBodyOffsetMm.value)+'mm', fontSize:(item?.font_size??12)+'px', fontFamily:item?resolvedFont(item):props.defaultFontFamily, fontWeight:item?.bold?'700':'400', textAlign:item?.align??'right' }; }
onMounted(()=>{void observePreviewLayout();});
watch(()=>form.elements.map(item=>[item.role,item.content,item.x,item.y,item.width,item.font_size,item.visible]),()=>{void observePreviewLayout();},{deep:true});
onBeforeUnmount(()=>previewLayoutObserver?.disconnect());
function issuerPreviewStyle(item:ElementBox):Record<string,string> { return { width:item.width+'%', marginTop:item.spacing+'mm', marginRight:`calc(${item.x*pageWidthMm.value/100}mm - ${props.pageLayout.right}mm)`, fontSize:item.font_size+'px', fontFamily:resolvedFont(item), fontWeight:item.bold?'700':'400', textAlign:item.align }; }
function pageNumberPreview(item:ElementBox):string { const format=item.page_format??'number_of'; if(format==='slash')return '۱/۵'; if(format==='short')return 'ص۱ از ۵'; if(format==='page')return 'صفحه ۱ از ۵'; return '۱ از ۵'; }
function pageNumberElementStyle(item:ElementBox):Record<string,string> {
 const topEdge=Math.max(form.page_layout.top,form.page_layout.header_height??0);
 const requestedY=item.y*pageHeightMm.value/100;
 const mappedY=requestedY;
 const anchorX=item.align==='right'?item.x:item.align==='left'?item.x+item.width:item.x+(item.width/2);
 const slot=anchorX<33.333?'right':anchorX>66.667?'left':'center';
 const slotRight=slot==='right'?0:slot==='center'?33.333:66.667;
 const slotLeft=slotRight+33.333;
 const style:Record<string,string>={right:slotRight+'%',top:(mappedY/pageHeightMm.value*100)+'%',width:'33.333%',fontSize:item.font_size+'pt',fontFamily:resolvedFont(item),fontWeight:item.bold?'700':'400',textAlign:item.align,direction:item.page_format==='slash'?'ltr':'rtl',padding:'0',whiteSpace:'nowrap'};
 if(item.align==='right')style.paddingRight=Math.max(0,anchorX-slotRight)*pageWidthMm.value/100+'mm';
 if(item.align==='left')style.paddingLeft=Math.max(0,slotLeft-anchorX)*pageWidthMm.value/100+'mm';
 if(item.align==='center')style.transform='translateX('+((slotRight+16.6665-anchorX)*pageWidthMm.value/100)+'mm)';
 return style;
}
</script>

<template>
 <Head title="طراح قالب نامه"/>
 <AuthenticatedLayout>
  <template #header><div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-xs font-semibold text-teal-700">مکاتبات</p><h2 class="mt-1 text-2xl font-bold text-slate-900">طراح قالب نامه</h2><p class="mt-1 text-sm text-slate-500">نوع هر بخش را مشخص کنید؛ متن اصلی نامه خودکار و با قالب ثبت‌شده درج می‌شود.</p></div><Link :href="route('settings.organization.edit')" class="text-sm font-semibold text-slate-600">بازگشت به تنظیمات سازمان</Link></div></template>
  <div class="bg-slate-50 py-6" dir="rtl">
   <div class="mx-auto grid max-w-[1500px] gap-5 px-4" :class="previewMode ? 'grid-cols-1' : 'xl:grid-cols-[minmax(0,1fr)_340px]'">
    <section class="overflow-auto rounded-2xl border border-slate-200 bg-slate-200 p-4">
     <div class="mx-auto mb-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600" :style="{maxWidth:pageWidthMm+'mm'}"><span>{{ previewMode ? 'پیش‌نمایش نمونه با اطلاعات تنظیمات سازمان' : 'پیش‌نمایش '+pageSize+' · محدوده هدر، فوتر و متن' }}</span><button v-if="!previewMode" type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-2 font-semibold" @click="showPageLayoutDialog = true">تنظیمات محدوده چاپ</button><button type="button" class="rounded-lg bg-slate-900 px-3 py-2 font-semibold text-white" @click="previewMode = !previewMode">{{ previewMode ? 'بازگشت به ویرایش' : 'پیش‌نمایش قالب' }}</button><div v-if="!previewMode" class="flex flex-wrap gap-2"><button v-for="(label,role) in addableRoles" :key="role" type="button" :disabled="role==='body' && form.elements.some(item=>item.role==='body')" class="rounded-lg bg-white px-3 py-2 font-semibold disabled:cursor-not-allowed disabled:opacity-40" @click="addRole(role)">{{label}} +</button><button type="button" class="rounded-lg bg-white px-3 py-2" @click="addBox('issuer','stamp')">مهر +</button><button type="button" class="rounded-lg bg-white px-3 py-2" @click="addBox('issuer','signature')">امضا +</button></div></div>
     <div ref="page" class="relative mx-auto w-full overflow-hidden bg-white shadow-xl" :style="[{aspectRatio:pageWidthMm+'/'+pageHeightMm,maxWidth:pageWidthMm+'mm'},letterheadDataUri?{backgroundImage:'url('+letterheadDataUri+')',backgroundSize:'100% 100%',backgroundPosition:'center'}:{}]" @pointermove="pointerMove" @pointerup="pointerUp" @pointercancel="pointerUp">
      <div class="pointer-events-none absolute left-0 right-0 top-0 z-0 flex items-end justify-center border-b border-dashed border-sky-400 bg-sky-100/35 text-[10px] font-semibold text-sky-800" :style="headerZoneStyle"><span class="mb-1 rounded bg-white/80 px-2 py-0.5">ناحیه رزروشده سربرگ</span></div>
      <div class="pointer-events-none absolute bottom-0 left-0 right-0 z-0 flex items-start justify-center border-t border-dashed border-amber-400 bg-amber-100/35 text-[10px] font-semibold text-amber-800" :style="footerZoneStyle"><span class="mt-1 rounded bg-white/80 px-2 py-0.5">ناحیه رزروشده پاصفحه</span></div>
      <div class="pointer-events-none absolute z-20 border border-dashed border-slate-400" :style="safeAreaStyle"><span class="absolute -top-5 right-0 rounded bg-white/90 px-1 text-[10px] text-slate-600">محدوده امن متن</span></div>
      <div class="absolute z-10 whitespace-pre-wrap text-right" :style="contentPreviewStyle">
       <div v-if="flowBody" class="whitespace-pre-wrap p-3 text-sm leading-8" :class="previewMode?'text-slate-900':'border border-dashed border-slate-300 bg-white/70 text-slate-500'" :style="bodyPreviewStyle()">
        <p v-if="!previewMode" class="mb-2 font-semibold">متن اصلی نامه — درج خودکار</p>
        <p>{{ previewMode ? previewBody : 'متن نامه از این قسمت آغاز می‌شود و در صورت نیاز به صفحات بعد ادامه می‌یابد.' }}</p>
       </div>
       <div v-for="item in flowIssuer" :key="item.id" class="whitespace-pre-wrap p-2 text-slate-900" :class="previewMode?'':(selectedId===item.id?'border border-dashed border-teal-600 bg-teal-50/80 ring-1 ring-teal-500':'border border-dashed border-slate-300 bg-white/70')" :style="issuerPreviewStyle(item)" @click="selectedId=item.id"><img v-if="item.kind==='stamp'&&stampDataUri" :src="stampDataUri" alt="مهر سازمان" class="pointer-events-none block h-auto w-full max-w-none object-contain"/><span v-else class="pointer-events-none">{{displayText(item)}}</span></div>
      </div>
      <div v-for="item in form.elements.filter(entry=>entry.visible && entry.role!=='body' && entry.role!=='issuer')" :key="item.id" class="absolute z-10 whitespace-pre-wrap p-2 text-slate-900" :class="[previewMode?'':(selectedId===item.id?'border border-dashed border-teal-600 bg-teal-50/80 ring-1 ring-teal-500':'border border-dashed border-slate-300 bg-white/70'),['meta','heading'].includes(item.role)?'template-flow-previous':'']" :style="item.role==='page_number'?pageNumberElementStyle(item):elementStyle(item)" @pointerdown="!previewMode&&pointerDown($event,item)"><span class="pointer-events-none"><bdi v-if="item.role==='page_number'" :dir="item.page_format==='slash'?'ltr':'rtl'">{{pageNumberPreview(item)}}</bdi><template v-else>{{displayText(item)}}</template></span></div>
     </div>
    </section>
    <aside v-if="!previewMode" class="space-y-4">
     
     <section class="rounded-2xl border border-slate-200 bg-white p-4"><div class="mb-3 flex items-center justify-between"><h3 class="font-bold">بخش‌های قالب</h3><button type="button" class="text-xs text-rose-600" @click="removeBox">حذف انتخابی</button></div><div class="space-y-1"><button v-for="item in form.elements" :key="item.id" type="button" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm" :class="selectedId===item.id?'bg-teal-50 text-teal-800':'hover:bg-slate-50'" @click="selectedId=item.id"><span>{{item.label}}</span><span class="text-xs text-slate-400">{{roleLabels[item.role]}}</span></button></div></section>
     <section v-if="selected" class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
      <h3 class="font-bold">تنظیمات بخش</h3>
      <label class="block text-xs font-semibold">نوع بخش<select v-model="selected.role" class="mt-1 w-full rounded-lg border-slate-300 text-sm"><option v-for="(label,role) in roleLabels" :key="role" :value="role">{{label}}</option></select></label>
      <label class="block text-xs font-semibold">عنوان<input v-model="selected.label" class="mt-1 w-full rounded-lg border-slate-300 text-sm"/></label>
      <label v-if="selected.kind==='text' && !['page_number','body'].includes(selected.role)" class="block text-xs font-semibold">متن / الگو<textarea v-model="selected.content" rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm"/></label>
      <p v-if="selected.role==='page_number'" class="rounded-lg bg-slate-50 p-2 text-xs leading-5">قالب شماره صفحه در چاپ با شمارنده واقعی صفحات مرورگر ساخته می‌شود. این شمارنده فقط در حاشیه چاپ بالا یا پایین قابل رندر است؛ اگر مختصات آن داخل محدوده متن باشد، به نزدیک‌ترین مرز حاشیه منتقل می‌شود. پیش‌نمایش طراح و پیش‌نمایش نامه همین جایگاه نهایی را نمایش می‌دهند. ترتیب اعداد و عبارت «از» مستقل از راست‌به‌چپ بودن متن تثبیت شده است.</p>
      <p v-if="selected.role==='body'" class="rounded-lg bg-slate-50 p-2 text-xs leading-5">این المان محتوای نامه را کنترل نمی‌کند؛ فاصله از بخش قبلی، عرض و قلم بدنه را تنظیم می‌کند. متن واقعی به‌صورت خودکار از نامه خوانده می‌شود.</p>
      <p v-if="selected.role==='issuer'" class="rounded-lg bg-slate-50 p-2 text-xs leading-5">این بخش پس از متن نامه قرار می‌گیرد. برای المان متنی این بخش، متغیرهای نام تنظیم‌کننده، عنوان و نام واحد ایجادکننده، نام مدیر واحد و سمت/عنوان سازمانی مدیر در دسترس است. فاصله منفی باعث هم‌پوشانی با بخش قبلی می‌شود؛ برای قرار گرفتن مهر یا امضا روی نام تنظیم‌کننده از مقدار منفی استفاده کنید.</p>
      <p v-if="selected.role==='repeat'" class="rounded-lg bg-slate-50 p-2 text-xs leading-5">این بخش در مختصات تعیین‌شده در تمام صفحات تکرار می‌شود؛ برای هدر آن را نزدیک بالای صفحه و برای فوتر نزدیک پایین صفحه قرار دهید. محدوده بدنه چاپ با حاشیه‌ها و ناحیه‌های رزروشده سربرگ و پاصفحه محافظت می‌شود.</p>
      <div class="grid grid-cols-2 gap-2">
       <label class="text-xs">عرض (mm)<input :value="coordinateMm('width')" type="number" min="10" :max="pageWidthMm-coordinateMm('x')" step="0.5" @change="updateCoordinateMm('width',$event)" class="mt-1 w-full rounded-lg border-slate-300"/></label>
       <label v-if="selected.role==='issuer' || selected.role==='body'" class="text-xs">فاصله از بخش قبل (mm)<input v-model.number="selected.spacing" type="number" :min="selected.role==='body'?0:-80" max="80" step="0.5" class="mt-1 w-full rounded-lg border-slate-300"/></label>
       <label v-if="['meta','heading','header','footer','repeat','page_number'].includes(selected.role)" class="text-xs">موقعیت از بالای صفحه (mm)<input :value="coordinateMm('y')" type="number" min="0" :max="pageHeightMm" step="0.5" @change="updateCoordinateMm('y',$event)" class="mt-1 w-full rounded-lg border-slate-300"/></label>
       <label v-if="selected.role!=='body'" class="text-xs">موقعیت از راست صفحه (mm)<input :value="coordinateMm('x')" type="number" min="0" :max="pageWidthMm-coordinateMm('width')" step="0.5" @change="updateCoordinateMm('x',$event)" class="mt-1 w-full rounded-lg border-slate-300"/></label>
       <label v-if="selected.kind==='text' && selected.role!=='page_number'" class="text-xs">اندازه قلم<input v-model.number="selected.font_size" type="number" min="7" max="48" class="mt-1 w-full rounded-lg border-slate-300"/></label>
       <label v-if="selected.kind==='text' && selected.role!=='page_number'" class="text-xs">نوع قلم<select v-model="selected.font_family" class="mt-1 w-full rounded-lg border-slate-300"><option v-for="font in fontOptions" :key="font.value" :value="font.value">{{font.label}}</option></select></label>
       <label v-if="selected.role==='page_number'" class="col-span-2 text-xs">قالب شماره صفحه<select v-model="selected.page_format" class="mt-1 w-full rounded-lg border-slate-300"><option value="number_of">۱ از ۵</option><option value="slash">۱/۵</option><option value="short">ص۱ از ۵</option><option value="page">صفحه ۱ از ۵</option></select></label>
       <label class="text-xs">تراز<select v-model="selected.align" class="mt-1 w-full rounded-lg border-slate-300"><option value="right">راست</option><option value="center">وسط</option><option value="left">چپ</option></select></label>
      </div>
      <label v-if="selected.kind==='text' && selected.role!=='page_number'" class="flex items-center gap-2 text-sm"><input v-model="selected.bold" type="checkbox"/> قلم ضخیم</label>
      <label class="flex items-center gap-2 text-sm"><input v-model="selected.visible" type="checkbox"/> نمایش در خروجی</label>
      <div v-if="selected.kind==='text' && availableTokens.length" class="border-t pt-3"><p class="mb-2 text-xs font-bold">متغیرهای مرتبط با این بخش</p><div class="flex flex-wrap gap-1.5"><button v-for="token in availableTokens" :key="token.key" type="button" class="rounded-md bg-slate-100 px-2 py-1 text-xs hover:bg-teal-50" :title="token.key" @click="insertToken(token.key)">{{token.label}}</button></div></div>
     </section>
     <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-6 text-amber-900">محتوای نامه به‌صورت خودکار از متن ثبت‌شده نامه درج می‌شود؛ المان «متن اصلی نامه» فقط محل شروع، عرض و ظاهر بدنه را کنترل می‌کند. فاصله منفی امکان هم‌پوشانی مهر و امضا با نام تنظیم‌کننده را فراهم می‌کند. اجزای تکرارشونده (هدر و فوتر) و شماره صفحه در همه صفحات چاپ نمایش داده می‌شوند. تنظیمات چاپ {{pageSize}} از پنجره «تنظیمات محدوده چاپ» انجام می‌شود؛ فاصله بدنه از بخش قبلی فقط در ابتدای متن اعمال می‌شود و در صفحه‌های بعد تکرار نمی‌شود.</div>
     <PrimaryButton class="w-full justify-center" :disabled="form.processing" @click="form.put(route('correspondence.template.update'))">ذخیره قالب نامه</PrimaryButton><InputError :message="form.errors.elements"/>
    </aside>
    <div v-if="showPageLayoutDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" @click.self="showPageLayoutDialog=false" @keydown.esc="showPageLayoutDialog=false">
     <section role="dialog" aria-modal="true" aria-labelledby="page-layout-title" class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl">
      <div class="mb-4 flex items-center justify-between"><div><h3 id="page-layout-title" class="text-lg font-bold">تنظیمات محدوده چاپ {{pageSize}}</h3><p class="mt-1 text-xs text-slate-500">حاشیه متن و فضای رزروشده سربرگ و پاصفحه مستقل هستند.</p></div><button type="button" class="rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100" aria-label="بستن" @click="showPageLayoutDialog=false">✕</button></div>
      <div class="grid grid-cols-2 gap-3">
       <label class="text-sm">حاشیه متن از بالا (mm)<input v-model.number="form.page_layout.top" type="number" min="5" max="80" step="0.5" class="mt-1 w-full rounded-lg border-slate-300"/></label>
       <label class="text-sm">حاشیه متن از پایین (mm)<input v-model.number="form.page_layout.bottom" type="number" min="5" max="70" step="0.5" class="mt-1 w-full rounded-lg border-slate-300"/></label>
       <label class="text-sm">حاشیه متن از راست (mm)<input v-model.number="form.page_layout.right" type="number" min="5" max="50" step="0.5" class="mt-1 w-full rounded-lg border-slate-300"/></label>
       <label class="text-sm">حاشیه متن از چپ (mm)<input v-model.number="form.page_layout.left" type="number" min="5" max="50" step="0.5" class="mt-1 w-full rounded-lg border-slate-300"/></label>
       <label class="text-sm">ارتفاع ناحیه سربرگ (mm)<input v-model.number="form.page_layout.header_height" type="number" min="0" max="100" step="0.5" class="mt-1 w-full rounded-lg border-slate-300"/></label>
       <label class="text-sm">ارتفاع ناحیه پاصفحه (mm)<input v-model.number="form.page_layout.footer_height" type="number" min="0" max="70" step="0.5" class="mt-1 w-full rounded-lg border-slate-300"/></label>
      </div>
      <p class="mt-4 rounded-lg bg-slate-50 p-3 text-xs leading-6 text-slate-600">ناحیه‌های رنگی سربرگ و پاصفحه و کادر خط‌چین محدوده امن متن را در پیش‌نمایش نشان می‌دهند. بدنه نامه خارج از این محدوده‌ها قرار می‌گیرد.</p>
      <div class="mt-5 flex justify-end gap-2"><button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm" @click="showPageLayoutDialog=false">بستن</button><button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white" @click="showPageLayoutDialog=false">اعمال تنظیمات</button></div>
     </section>
    </div>
   </div>
  </div>
 </AuthenticatedLayout>
</template>