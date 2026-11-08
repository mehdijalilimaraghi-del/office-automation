<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
interface AddressingSnapshot { organizational_title_prefix?:string|null; position?:string|null; personal_salutation?:string|null; professional_title?:string|null; name?:string|null; organization_name?:string|null; department_name?:string|null; }
type Role='meta'|'heading'|'page_number'|'issuer'|'repeat'|'body';
type PageFormat='number_of'|'slash'|'short'|'page';
interface TemplateElement { id:string; kind:'text'|'stamp'|'signature'; role:Role; label:string; content:string; x:number; y:number; width:number; spacing:number; font_size:number; font_family:string; page_format:PageFormat; bold:boolean; align:'right'|'center'|'left'; visible:boolean; }
interface PrintLetter { id:number; attachment_pages:number|null; type:'incoming'|'outgoing'|'internal'; subject:string; body:string|null; letter_number:string|null; registration_number:string|null; registered_at:string|null; sent_at:string|null; recipient_name:string|null; recipient_addressing:AddressingSnapshot|null; external_organization:string|null; external_department:string|null; }
const props=defineProps<{letter:PrintLetter;templateElements:TemplateElement[];organization:{name?:string|null;short_name?:string|null;address?:string|null;phone?:string|null;email?:string|null;website?:string|null};creatorName:string|null;creatorUnitTitle:string|null;stampDataUri:string|null;letterheadDataUri:string|null;signatureDataUri:string|null;printOptions:{show_creator_identity:boolean;show_stamp:boolean;show_department_signature:boolean;letterhead_show_name:boolean;letterhead_show_address:boolean;letterhead_show_phone:boolean;letterhead_show_email:boolean;letterhead_show_website:boolean;font_family:string;};}>();
const withLetterhead=ref(true);
const elements=computed(()=>props.templateElements.filter(item=>item.visible));
const flowMeta=computed(()=>elements.value.filter(item=>item.role==='meta'));
const flowHeading=computed(()=>elements.value.filter(item=>item.role==='heading'));
const flowIssuer=computed(()=>elements.value.filter(item=>item.role==='issuer'));
const bodyElement=computed(()=>elements.value.find(item=>item.role==='body')??{spacing:42,width:84,font_size:12,font_family:'default',bold:false,align:'right'} as TemplateElement);
const repeating=computed(()=>elements.value.filter(item=>item.role==='repeat'));
const pageNumberEnabled=computed(()=>elements.value.some(item=>item.role==='page_number'));
const hasCreatorElement=computed(()=>flowIssuer.value.some(item=>item.kind==='text'&&(item.content.includes('{{creator_name}}')||item.content.includes('{{creator_unit_title}}'))));
const hasSignatureElement=computed(()=>flowIssuer.value.some(item=>item.kind==='signature'));
const hasIssuerAssetElement=computed(()=>hasStampElement.value||hasSignatureElement.value);
const hasStampElement=computed(()=>flowIssuer.value.some(item=>item.kind==='stamp'));
const typeLabel=computed(()=>({incoming:'نامه وارده',outgoing:'نامه صادره',internal:'نامه داخلی'})[props.letter.type]);
const recipientLines=computed(()=>{const a=props.letter.recipient_addressing;if(!a)return [props.letter.recipient_name].filter(Boolean) as string[];return [[a.organizational_title_prefix,a.position].filter(Boolean).join(' '),[a.personal_salutation,a.professional_title,a.name].filter(Boolean).join(' '),[a.organization_name,a.department_name].filter(Boolean).join(' - ')].filter(Boolean) as string[];});
const letterDate=computed(()=>{const value=props.letter.sent_at??props.letter.registered_at;return value?new Intl.DateTimeFormat('fa-IR',{dateStyle:'long'}).format(new Date(value)):'—';});
function resolveTemplate(content:string):string {
 const values:Record<string,string>={organization_name:props.organization.name??'',short_name:props.organization.short_name??'',address:props.organization.address??'',tel:props.organization.phone??'',email:props.organization.email??'',website:props.organization.website??'',letter_number:props.letter.letter_number??'',registration_number:props.letter.registration_number??'',date:letterDate.value,recipient_name:props.letter.recipient_name??props.letter.recipient_addressing?.name??'',recipient_organization:props.letter.recipient_addressing?.organization_name??props.letter.external_organization??'',recipient_unit:props.letter.recipient_addressing?.department_name??props.letter.external_department??'',recipient_position:props.letter.recipient_addressing?.position??'',recipient_title:[props.letter.recipient_addressing?.personal_salutation,props.letter.recipient_addressing?.professional_title].filter(Boolean).join(' '),subject:props.letter.subject,letter_type:typeLabel.value,creator_name:props.creatorName??'',creator_unit_title:props.creatorUnitTitle??'',attachment_pages:props.letter.attachment_pages?`پیوست: دارد (${new Intl.NumberFormat('fa-IR').format(props.letter.attachment_pages)} صفحه)`:'پیوست: ندارد',page_number:'',pages:''};
 return content.replace(/\{\{([a-z_]+)\}\}/g,(token,key:string)=>Object.prototype.hasOwnProperty.call(values,key)?values[key]:token);
}
function fontFamily(item:TemplateElement):string{return item.font_family==='default'||!item.font_family?(props.printOptions.font_family||'Vazirmatn'):item.font_family;}
function boxStyle(item:TemplateElement):Record<string,string>{return {width:(item.width*2.1)+'mm',marginTop:item.spacing+'mm',marginRight:`calc(${item.x*2.1}mm - 18mm)`,fontSize:item.font_size+'pt',fontFamily:fontFamily(item),fontWeight:item.bold?'700':'400',textAlign:item.align};}
function positionedStyle(item:TemplateElement):Record<string,string>{return {right:item.x+'%',top:item.y+'%',bottom:'auto',width:item.width+'%',fontSize:item.font_size+'pt',fontFamily:fontFamily(item),fontWeight:item.bold?'700':'400',textAlign:item.align};}
function bodyStyle():Record<string,string>{const item=bodyElement.value;return {width:item.width+'%',marginTop:item.spacing+'mm',fontSize:item.font_size+'pt',fontFamily:fontFamily(item),fontWeight:item.bold?'700':'400',textAlign:item.align};}
function printLetter():void{window.print();}
</script>

<template>
 <Head :title="'پیش‌نمایش چاپ - '+letter.subject"/>
 <main class="min-h-screen bg-slate-100 px-4 py-6 text-slate-900" dir="rtl">
  <div class="no-print mx-auto mb-4 flex max-w-[210mm] flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
   <div><p class="text-sm font-bold">پیش‌نمایش چاپ نامه</p><p class="mt-1 text-xs text-slate-500">قالب A4 مشترک برای چاپ کاغذی و ذخیره PDF</p></div>
   <div class="flex flex-wrap items-center gap-3"><label class="inline-flex items-center gap-2 text-sm"><input v-model="withLetterhead" type="checkbox" class="rounded border-slate-300 text-teal-600"/>چاپ با سربرگ</label><button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white" @click="printLetter">چاپ / ذخیره PDF</button><Link :href="route('correspondence.show',letter.id)" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold">بازگشت به نامه</Link></div>
  </div>
  <article class="paper mx-auto bg-white" :style="{fontFamily:printOptions.font_family||'Vazirmatn'}" dir="rtl">
   <div class="template-coordinate-plane">
    <div v-if="withLetterhead && letterheadDataUri" class="letterhead-background" :style="{backgroundImage:'url('+letterheadDataUri+')'}" aria-hidden="true"></div>
    <div v-for="item in repeating" :key="item.id" class="repeat-item" :style="positionedStyle(item)">{{resolveTemplate(item.content)}}</div>
    <div v-for="item in elements.filter(entry=>entry.role==='page_number')" :key="item.id" class="page-number" :class="{'page-number-slash':item.page_format==='slash'}" :style="positionedStyle(item)"><span v-if="item.page_format==='short'">ص</span><span v-else-if="item.page_format==='page'">صفحه </span><span class="page-counter"></span><template v-if="item.page_format==='slash'">/</template><template v-else> از </template><span class="pages-counter"></span></div>
    <div v-for="item in flowMeta" :key="item.id" class="flow-item first-page-positioned" :style="positionedStyle(item)">{{item.kind==='stamp'&&stampDataUri?'':item.kind==='signature'&&signatureDataUri?'':resolveTemplate(item.content)}}<img v-if="item.kind==='stamp'&&stampDataUri" :src="stampDataUri" alt="مهر سازمان" class="inline-asset"/><img v-else-if="item.kind==='signature'&&signatureDataUri" :src="signatureDataUri" alt="امضای مدیر واحد" class="inline-asset"/></div>
    <div v-for="item in flowIssuer" :key="item.id" class="flow-item first-page-positioned" :style="positionedStyle(item)">
     <div v-if="item.kind==='stamp'&&stampDataUri"><img :src="stampDataUri" alt="مهر سازمان" class="inline-asset"/></div>
     <div v-else-if="item.kind==='signature'&&signatureDataUri"><img :src="signatureDataUri" alt="امضای مدیر واحد" class="inline-asset"/></div>
     <template v-else>{{resolveTemplate(item.content)}}</template>
    </div>
    <div v-for="item in flowHeading" :key="item.id" class="flow-item first-page-positioned" :style="positionedStyle(item)">
     <template v-if="item.content">{{resolveTemplate(item.content)}}</template>
     <template v-else><div v-if="recipientLines.length" class="mb-4 space-y-1 text-sm font-semibold leading-7"><p v-for="(line,index) in recipientLines" :key="index">{{line}}</p></div><h2 class="text-center text-base font-bold leading-8">{{letter.subject}}</h2></template>
    </div>
   </div>
   <div class="document-content">
    <section class="letter-body" :style="bodyStyle()" v-html="letter.body||''"></section>

    <footer v-if="(!hasCreatorElement&&!hasIssuerAssetElement&&printOptions.show_creator_identity)||(!hasSignatureElement&&printOptions.show_department_signature&&signatureDataUri)||(!hasStampElement&&printOptions.show_stamp&&stampDataUri)" class="signature-area">
     <div v-if="!hasCreatorElement&&printOptions.show_creator_identity" class="text-center text-sm leading-7"><p v-if="creatorUnitTitle" class="font-semibold">{{creatorUnitTitle}}</p><p v-if="creatorName" class="font-bold">{{creatorName}}</p></div>
     <img v-if="!hasSignatureElement&&printOptions.show_department_signature&&signatureDataUri" :src="signatureDataUri" alt="امضای مدیر واحد" class="inline-asset"/>
     <img v-if="!hasStampElement&&printOptions.show_stamp&&stampDataUri" :src="stampDataUri" alt="مهر سازمان" class="inline-asset"/>
    </footer>
   </div>
  </article>
 </main>
</template>

<style>
@page{size:A4;margin:0;}
 .paper{position:relative;box-sizing:border-box;width:210mm;min-height:297mm;color:#111827;background:#fff;}
 .template-coordinate-plane{position:absolute;top:0;right:0;left:0;height:297mm;z-index:1;pointer-events:none;}
 .document-content{position:relative;z-index:2;direction:rtl;text-align:right;unicode-bidi:plaintext;padding:14mm 18mm 18mm;}
.letterhead-background{position:absolute;inset:0;z-index:0;background-position:center top;background-repeat:no-repeat;background-size:100% 100%;pointer-events:none;}
.repeat-item{position:absolute;z-index:3;white-space:pre-wrap;line-height:1.5;direction:rtl;}
 .flow-item{position:relative;z-index:2;white-space:pre-wrap;line-height:1.8;max-width:100%;}.first-page-positioned{position:absolute;top:0;right:0;}
.letter-body{position:relative;z-index:2;box-sizing:border-box;min-width:0;direction:rtl;text-align:inherit;unicode-bidi:plaintext;white-space:normal;overflow-wrap:break-word;line-height:1.9;}
.letter-body *{box-sizing:border-box;max-width:100%;}
.letter-body p,.letter-body div,.letter-body li{overflow-wrap:break-word;}
.letter-body ul,.letter-body ol{padding-inline-start:1.5rem;}
.letter-body pre{white-space:pre-wrap;overflow-wrap:anywhere;}
.letter-body p{margin:0 0 .75rem;}
.letter-body img{max-width:100%;height:auto;}
.letter-body table{max-width:100%;border-collapse:collapse;}
 .letter-body td,.letter-body th{border:1px solid #cbd5e1;padding:4px 7px;overflow-wrap:anywhere;}
.inline-asset{display:block;max-width:42mm;max-height:28mm;object-fit:contain;margin-top:2mm;}
.issuer-item{break-inside:avoid;page-break-inside:avoid;}
.signature-area{position:relative;z-index:2;display:flex;align-items:flex-end;justify-content:space-between;gap:8mm;margin-top:16mm;break-inside:avoid;page-break-inside:avoid;}
.page-number{position:absolute;z-index:4;white-space:nowrap;text-align:center;font:9pt Tahoma,sans-serif;color:#475569;}
.page-number-slash{direction:ltr;unicode-bidi:isolate;}
.page-counter:before,.pages-counter:before{content:'۱';}
@media print{
 html,body{background:#fff!important;print-color-adjust:exact;-webkit-print-color-adjust:exact;}
 main{min-height:0!important;padding:0!important;background:#fff!important;}
 .no-print{display:none!important;}
 .paper{width:auto;min-height:297mm;margin:0;box-shadow:none;overflow:visible;}
 .document-content{padding:14mm 18mm 18mm;}
 .letterhead-background{position:fixed;}
 .repeat-item{position:fixed;}
  .page-number{position:fixed;z-index:4;left:auto;right:auto;bottom:auto;text-align:center;font:9pt Tahoma,sans-serif;color:#475569;}
 .page-counter:before{content:counter(page);}
 .pages-counter:before{content:counter(pages);}
}
@media screen and (max-width:800px){.paper{width:100%;min-height:0;}.document-content{padding:24px;}}
</style>