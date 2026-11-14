<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, nextTick } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
interface AddressingSnapshot { organizational_title_prefix?:string|null; position?:string|null; personal_salutation?:string|null; professional_title?:string|null; name?:string|null; organization_name?:string|null; department_name?:string|null; }
type Role='meta'|'heading'|'page_number'|'issuer'|'header'|'footer'|'repeat'|'body';
type PageFormat='number_of'|'slash'|'short'|'page';
interface TemplateElement { id:string; kind:'text'|'stamp'|'signature'; role:Role; label:string; content:string; x:number; y:number; width:number; spacing:number; font_size:number; font_family:string; page_format:PageFormat; bold:boolean; align:'right'|'center'|'left'; visible:boolean; }
interface PrintLetter { id:number; attachment_pages:number|null; appendix_mode:'none'|'editor'|'file'|'both'; appendix_body:string|null; appendix_file_count:number; type:'incoming'|'outgoing'|'internal'; subject:string; body:string|null; letter_number:string|null; registration_number:string|null; registered_at:string|null; sent_at:string|null; recipient_name:string|null; recipient_addressing:AddressingSnapshot|null; external_organization:string|null; external_department:string|null; }
const props=defineProps<{isPreview?:boolean;previewNotice?:string|null;letter:PrintLetter;templateElements:TemplateElement[];pageLayout:{top:number;right:number;bottom:number;left:number;header_height:number;footer_height:number};pageSize:'A4'|'A5';organization:{name?:string|null;short_name?:string|null;address?:string|null;postal_code?:string|null;phone?:string|null;email?:string|null;website?:string|null};creatorName:string|null;creatorUnitTitle:string|null;managerName:string|null;managerPosition:string|null;managerTitle:string|null;creatorDepartmentName:string|null;stampDataUri:string|null;letterheadDataUri:string|null;signatureDataUri:string|null;printOptions:{show_creator_identity:boolean;show_stamp:boolean;show_department_signature:boolean;letterhead_show_name:boolean;letterhead_show_address:boolean;letterhead_show_phone:boolean;letterhead_show_email:boolean;letterhead_show_website:boolean;font_family:string;};}>();
const pageWidthMm = computed(() => props.pageSize === 'A5' ? 148 : 210);
const pageHeightMm = computed(() => props.pageSize === 'A5' ? 210 : 297);
const withLetterhead=ref(true);
const mainContent=ref<HTMLElement[]>([]);
const appendixMeasure=ref<HTMLElement|null>(null);
const firstPagePlane=ref<HTMLElement|null>(null);
const firstPageContentWindow=ref<HTMLElement|null>(null);
function setFirstPagePlane(element:unknown):void { firstPagePlane.value=element instanceof HTMLElement?element:null; }
function setFirstPageContentWindow(element:unknown):void { firstPageContentWindow.value=element instanceof HTMLElement?element:null; }
const measuredBodyOffsetMm=ref(0);
const estimatedPageCount=ref(1);
const mainPageCount=ref(1);
const appendixPageCount=ref(0);
let contentObserver:ResizeObserver|null=null;
let layoutObserver:ResizeObserver|null=null;
const elements=computed(()=>props.templateElements.filter(item=>item.visible));
const flowMeta=computed(()=>elements.value.filter(item=>item.role==='meta'));
const flowHeading=computed(()=>elements.value.filter(item=>item.role==='heading'));
const flowIssuer=computed(()=>elements.value.filter(item=>item.role==='issuer'));
const bodyElement=computed(()=>elements.value.find(item=>item.role==='body')??{spacing:42,width:84,font_size:12,font_family:'default',bold:false,align:'right'} as TemplateElement);
const repeating=computed(()=>elements.value.filter(item=>['header','footer','repeat'].includes(item.role)));
const pageNumberEnabled=computed(()=>elements.value.some(item=>item.role==='page_number'));
const pageNumberFormat=computed(()=>elements.value.find(item=>item.role==='page_number')?.page_format??'number_of');
const pageNumberElement=computed(()=>elements.value.find(item=>item.role==='page_number')??null);
const pageNumberPosition=computed(()=>{
 const item=pageNumberElement.value;
 if(!item)return '@bottom-center';
 const yMm=item.y*pageHeightMm.value/100;
 const topEdge=Math.max(props.pageLayout.top,props.pageLayout.header_height);
 const bottomEdge=pageHeightMm.value-Math.max(props.pageLayout.bottom,props.pageLayout.footer_height);
 const vertical=yMm-Math.max(topEdge,props.pageLayout.header_height)<=bottomEdge-yMm?'top':'bottom';
 const anchorX=item.align==='right'?item.x:item.align==='left'?item.x+item.width:item.x+(item.width/2);
  const horizontal=anchorX<33.333?'right':anchorX>66.667?'left':'center';
 return '@'+vertical+'-'+horizontal;
});
const pageCss=computed(()=>`@page{size:${pageWidthMm.value}mm ${pageHeightMm.value}mm;margin:0;}`);
const pageWindowStyle=computed(()=>({
 top:Math.max(props.pageLayout.top,props.pageLayout.header_height)+'mm',
 right:props.pageLayout.right+'mm',
 bottom:Math.max(props.pageLayout.bottom,props.pageLayout.footer_height)+'mm',
 left:props.pageLayout.left+'mm',
}));
function pageContentStyle(pageIndex:number):Record<string,string> {
 const pageHeight=pageHeightMm.value-Math.max(props.pageLayout.top,props.pageLayout.header_height)-Math.max(props.pageLayout.bottom,props.pageLayout.footer_height);
 const offset=Math.max(0,(pageIndex-1)*pageHeight);
 return { transform:'translateY(-'+offset+'mm)' };
}
const paperStyle=computed(()=>({
 fontFamily:props.printOptions.font_family||'Vazirmatn',
 width:pageWidthMm.value+'mm',
 height:pageHeightMm.value+'mm',
 minHeight:pageHeightMm.value+'mm',
 '--print-page-width':pageWidthMm.value+'mm',
 '--print-page-height':pageHeightMm.value+'mm',
 '--print-content-top':Math.max(props.pageLayout.top,props.pageLayout.header_height)+'mm',
 '--print-content-left':props.pageLayout.left+'mm',
 '--print-content-right':props.pageLayout.right+'mm',
}));
const hasCreatorElement=computed(()=>flowIssuer.value.some(item=>item.kind==='text'&&(item.content.includes('{{creator_name}}')||item.content.includes('{{creator_unit_title}}')||item.content.includes('{{manager_name}}')||item.content.includes('{{manager_position}}')||item.content.includes('{{manager_title}}')||item.content.includes('{{creator_department_name}}'))));
const hasSignatureElement=computed(()=>flowIssuer.value.some(item=>item.kind==='signature'));
const hasIssuerAssetElement=computed(()=>hasStampElement.value||hasSignatureElement.value);
const hasStampElement=computed(()=>flowIssuer.value.some(item=>item.kind==='stamp'));
const typeLabel=computed(()=>({incoming:'نامه وارده',outgoing:'نامه صادره',internal:'نامه داخلی'})[props.letter.type]);
const appendixSummary=computed(()=>{const parts:string[]=[];if(appendixPageCount.value>0)parts.push(new Intl.NumberFormat('fa-IR').format(appendixPageCount.value)+' برگ');if(props.letter.appendix_file_count>0)parts.push(new Intl.NumberFormat('fa-IR').format(props.letter.appendix_file_count)+' فایل');return parts.length?'پیوست: '+parts.join(' + '):'پیوست: ندارد';});
const recipientLines=computed(()=>{const a=props.letter.recipient_addressing;if(!a)return [props.letter.recipient_name].filter(Boolean) as string[];return [[a.organizational_title_prefix,a.position].filter(Boolean).join(' '),[a.personal_salutation,a.professional_title,a.name].filter(Boolean).join(' '),[a.organization_name,a.department_name].filter(Boolean).join(' - ')].filter(Boolean) as string[];});
const letterDate=computed(()=>{const value=props.letter.sent_at??props.letter.registered_at;return value?new Intl.DateTimeFormat('fa-IR',{dateStyle:'long'}).format(new Date(value)):'—';});
function resolveTemplate(content:string):string {
 const values:Record<string,string>={organization_name:props.organization.name??'',short_name:props.organization.short_name??'',address:props.organization.address??'',postal_code:props.organization.postal_code??'',tel:props.organization.phone??'',email:props.organization.email??'',website:props.organization.website??'',letter_number:props.letter.letter_number??'',registration_number:props.letter.registration_number??'',date:letterDate.value,recipient_name:props.letter.recipient_name??props.letter.recipient_addressing?.name??'',recipient_organization:props.letter.recipient_addressing?.organization_name??props.letter.external_organization??'',recipient_unit:props.letter.recipient_addressing?.department_name??props.letter.external_department??'',recipient_position:props.letter.recipient_addressing?.position??'',recipient_organizational_title_prefix:props.letter.recipient_addressing?.organizational_title_prefix??'',recipient_personal_title_prefix:props.letter.recipient_addressing?.personal_salutation??'',recipient_title:[props.letter.recipient_addressing?.personal_salutation,props.letter.recipient_addressing?.professional_title].filter(Boolean).join(' '),subject:props.letter.subject,letter_type:typeLabel.value,creator_name:props.creatorName??'',creator_unit_title:props.creatorUnitTitle??'',manager_name:props.managerName??'',manager_position:props.managerPosition??'',manager_title:props.managerTitle??props.managerPosition??'',creator_department_name:props.creatorDepartmentName??'',attachment_pages:appendixSummary.value,page_number:'',pages:''};
 return content.replace(/\{\{([a-z_]+)\}\}/g,(token,key:string)=>Object.prototype.hasOwnProperty.call(values,key)?values[key]:token);
}
function fontFamily(item:TemplateElement):string{return item.font_family==='default'||!item.font_family?(props.printOptions.font_family||'Vazirmatn'):item.font_family;}
function boxStyle(item:TemplateElement):Record<string,string>{return {width:item.width+'%',marginTop:item.spacing+'mm',marginRight:`calc(${item.x*pageWidthMm.value/100}mm - ${props.pageLayout.right}mm)`,fontSize:item.font_size+'pt',fontFamily:fontFamily(item),fontWeight:item.bold?'700':'400',textAlign:item.align};}
function positionedStyle(item:TemplateElement):Record<string,string>{
 const contentTop=Math.max(props.pageLayout.top,props.pageLayout.header_height);
 return {right:item.x+'%',top:item.y+'%',bottom:'auto',width:item.width+'%',fontSize:item.font_size+'pt',fontFamily:fontFamily(item),fontWeight:item.bold?'700':'400',textAlign:item.align,'--print-repeat-top':(item.y*pageHeightMm.value/100-contentTop)+'mm','--print-repeat-right':(item.x*pageWidthMm.value/100)+'mm','--print-repeat-width':(item.width*pageWidthMm.value/100)+'mm'};
}
function pageNumberScreenStyle(item:TemplateElement):Record<string,string> {
 const topEdge=Math.max(props.pageLayout.top,props.pageLayout.header_height);
 const requestedY=item.y*pageHeightMm.value/100;
 const mappedY=requestedY;
 const anchorX=item.align==='right'?item.x:item.align==='left'?item.x+item.width:item.x+(item.width/2);
 const slot=anchorX<33.333?'right':anchorX>66.667?'left':'center';
 const slotRight=slot==='right'?0:slot==='center'?33.333:66.667;
 const slotLeft=slotRight+33.333;
 const style:Record<string,string>={right:slotRight+'%',top:(mappedY/pageHeightMm.value*100)+'%',width:'33.333%',fontSize:item.font_size+'pt',fontFamily:fontFamily(item),fontWeight:item.bold?'700':'400',textAlign:item.align,direction:item.page_format==='slash'?'ltr':'rtl'};
 if(item.align==='right')style.paddingRight=Math.max(0,anchorX-slotRight)*pageWidthMm.value/100+'mm';
 if(item.align==='left')style.paddingLeft=Math.max(0,slotLeft-anchorX)*pageWidthMm.value/100+'mm';
 if(item.align==='center')style.transform='translateX('+((slotRight+16.6665-anchorX)*pageWidthMm.value/100)+'mm)';
 style['--print-page-number-top']=(mappedY-topEdge)+'mm';
 style['--print-page-number-right']=(item.x*pageWidthMm.value/100)+'mm';
 style['--print-page-number-width']=(item.width*pageWidthMm.value/100)+'mm';
 return style;
}
function bodyStyle():Record<string,string>{const item=bodyElement.value;return {width:item.width+'%',marginTop:(item.spacing+measuredBodyOffsetMm.value)+'mm',fontSize:item.font_size+'pt',fontFamily:fontFamily(item),fontWeight:item.bold?'700':'400',textAlign:item.align};}
function appendixBodyStyle():Record<string,string>{const item=bodyElement.value;return {width:item.width+'%',marginTop:'0',fontSize:item.font_size+'pt',fontFamily:fontFamily(item),fontWeight:item.bold?'700':'400',textAlign:item.align};}
function constrainLetterTables():void {
 document.querySelectorAll<HTMLElement>('.letter-body table').forEach(table=>{
  table.style.setProperty('max-width','100%','important');
  table.style.setProperty('box-sizing','border-box');
  table.style.setProperty('table-layout','fixed');
  table.querySelectorAll<HTMLElement>('td,th').forEach(cell=>{
   cell.style.setProperty('max-width','100%','important');
   cell.style.setProperty('min-width','0','important');
   cell.style.setProperty('box-sizing','border-box');
   cell.style.setProperty('overflow-wrap','anywhere');
  });
 });
}
function updateBodyStartOffset():void {
 const plane=firstPagePlane.value;
 const contentWindow=firstPageContentWindow.value;
 const paper=plane?.closest('.paper') as HTMLElement|null;
 if(!plane||!contentWindow||!paper)return;
 const paperRect=paper.getBoundingClientRect();
 const safeTop=contentWindow.getBoundingClientRect().top;
 const pxPerMm=paperRect.width/pageWidthMm.value;
 if(pxPerMm<=0)return;
 const preceding=Array.from(plane.querySelectorAll<HTMLElement>('.first-page-positioned'));
 const lastBottom=preceding.reduce((bottom,element)=>Math.max(bottom,element.getBoundingClientRect().bottom),safeTop);
 measuredBodyOffsetMm.value=Math.max(0,(lastBottom-safeTop)/pxPerMm);
}
function updateEstimatedPageCount():void {
 const main=mainContent.value[0];
 const appendix=appendixMeasure.value;
 const availablePageHeightMm=pageHeightMm.value-Math.max(props.pageLayout.top,props.pageLayout.header_height)-Math.max(props.pageLayout.bottom,props.pageLayout.footer_height);
 const pageHeightPx=availablePageHeightMm*96/25.4;
 const safePageHeight=Math.max(1,pageHeightPx);
 mainPageCount.value=main?Math.max(1,Math.ceil(Math.max(0,main.scrollHeight)/safePageHeight)):1;
 appendixPageCount.value=props.letter.appendix_body?.trim()&&appendix?Math.max(1,Math.ceil(Math.max(0,appendix.scrollHeight)/safePageHeight)):0;
 estimatedPageCount.value=mainPageCount.value+appendixPageCount.value;
}
onMounted(async()=>{
 await nextTick();
 constrainLetterTables();
 updateBodyStartOffset();
 updateEstimatedPageCount();
 if(typeof ResizeObserver!=='undefined'){
  if(mainContent.value[0]){
   contentObserver=new ResizeObserver(()=>{updateBodyStartOffset();updateEstimatedPageCount();});
   contentObserver.observe(mainContent.value[0]);
  }
  if(appendixMeasure.value){
   contentObserver??=new ResizeObserver(()=>updateEstimatedPageCount());
   contentObserver.observe(appendixMeasure.value);
  }
  if(firstPagePlane.value){
   layoutObserver=new ResizeObserver(()=>{updateBodyStartOffset();updateEstimatedPageCount();});
   layoutObserver.observe(firstPagePlane.value);
   firstPagePlane.value.querySelectorAll<HTMLElement>('.first-page-positioned').forEach(element=>layoutObserver?.observe(element));
  }
 }
});
onBeforeUnmount(()=>{contentObserver?.disconnect();layoutObserver?.disconnect();});
function pageNumberSample(item:TemplateElement,pageIndex:number):string {
 const current=new Intl.NumberFormat('fa-IR').format(pageIndex), total=new Intl.NumberFormat('fa-IR').format(mainPageCount.value);
 if(item.page_format==='slash')return current+'/'+total;
 if(item.page_format==='short')return 'ص'+current+' از '+total;
 if(item.page_format==='page')return 'صفحه '+current+' از '+total;
 return current+' از '+total;
}
function appendixPageNumberSample(pageIndex:number):string{return 'پیوست ـ صفحه '+new Intl.NumberFormat('fa-IR').format(pageIndex)+' از '+new Intl.NumberFormat('fa-IR').format(appendixPageCount.value);}
function printLetter():void{window.print();}
function closePreview():void{if(window.opener){window.close();return;}window.history.back();}
</script>

<template>
 <Head :title="'پیش‌نمایش چاپ - '+letter.subject"/>
  <component :is="'style'">{{pageCss}}</component>
 <main class="min-h-screen bg-slate-100 px-4 py-6 text-slate-900" :style="{'--print-page-width':pageWidthMm+'mm','--print-page-height':pageHeightMm+'mm'}" dir="rtl">
  <div class="no-print mx-auto mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm" :style="{maxWidth:pageWidthMm+'mm'}">
   <div><p class="text-sm font-bold">پیش‌نمایش چاپ نامه</p><p class="mt-1 text-xs text-slate-500">قالب {{props.pageSize}} · برآورد پیش‌نمایش: {{estimatedPageCount}} صفحه</p></div>
   <div class="flex flex-wrap items-center gap-3"><label class="inline-flex items-center gap-2 text-sm"><input v-model="withLetterhead" type="checkbox" class="rounded border-slate-300 text-teal-600"/>چاپ با سربرگ</label><button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white" @click="printLetter">چاپ / ذخیره PDF</button><button v-if="isPreview" type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold" @click="closePreview">بستن پیش‌نمایش</button><Link v-else :href="route('correspondence.show',letter.id)" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold">بازگشت به نامه</Link></div>
   <p v-if="previewNotice" class="mt-3 text-xs leading-6 text-amber-700">{{previewNotice}}</p>
  </div>
  <article v-for="pageIndex in estimatedPageCount" :key="pageIndex" class="paper screen-page mx-auto mb-6 bg-white" :class="'page-format-'+pageNumberFormat" :style="paperStyle" dir="rtl">
   <div class="template-coordinate-plane" :ref="pageIndex===1 ? setFirstPagePlane : undefined">
    <div v-if="withLetterhead && letterheadDataUri" class="letterhead-background" :style="{backgroundImage:'url('+letterheadDataUri+')'}" aria-hidden="true"></div>
    <div v-for="item in repeating" :key="item.id" class="repeat-item" :style="positionedStyle(item)">{{resolveTemplate(item.content)}}</div>
    <div v-for="item in elements.filter(entry=>entry.role==='page_number')" :key="item.id" class="page-number" :class="'page-number-format-'+item.page_format" :style="pageNumberScreenStyle(item)"><bdi :dir="item.page_format==='slash'?'ltr':'rtl'">{{pageIndex>mainPageCount?appendixPageNumberSample(pageIndex-mainPageCount):pageNumberSample(item,pageIndex)}}</bdi></div>
    <template v-if="pageIndex===1"><div v-for="item in flowMeta" :key="item.id" class="flow-item first-page-positioned" :style="positionedStyle(item)">{{item.kind==='text'?resolveTemplate(item.content):''}}<img v-if="item.kind==='stamp'&&stampDataUri" :src="stampDataUri" alt="مهر سازمان" class="inline-asset"/><img v-else-if="item.kind==='signature'&&signatureDataUri" :src="signatureDataUri" alt="امضای مدیر واحد" class="inline-asset"/></div>
    <div v-for="item in flowHeading" :key="item.id" class="flow-item first-page-positioned" :style="positionedStyle(item)">
     <template v-if="item.content">{{resolveTemplate(item.content)}}</template>
     <template v-else><div v-if="recipientLines.length" class="mb-4 space-y-1 text-sm font-semibold leading-7"><p v-for="(line,index) in recipientLines" :key="index">{{line}}</p></div><h2 class="text-center text-base font-bold leading-8">{{letter.subject}}</h2></template>
    </div></template>
   </div>
   <div class="page-content-window" :ref="pageIndex===1 ? setFirstPageContentWindow : undefined" :style="pageWindowStyle">
    <div v-if="pageIndex<=mainPageCount" ref="mainContent" class="document-content" :style="pageContentStyle(pageIndex)">
     <section class="letter-body" :style="bodyStyle()" v-html="letter.body||''"></section>
     <div v-for="item in flowIssuer" :key="item.id" class="flow-item issuer-item" :style="boxStyle(item)">
      <img v-if="item.kind==='stamp'&&stampDataUri" :src="stampDataUri" alt="مهر سازمان" class="inline-asset"/>
      <img v-else-if="item.kind==='signature'&&signatureDataUri" :src="signatureDataUri" alt="امضای مدیر واحد" class="inline-asset"/>
      <template v-else-if="item.kind==='text'">{{resolveTemplate(item.content)}}</template>
     </div>
     <footer v-if="!hasCreatorElement&&!hasIssuerAssetElement&&printOptions.show_creator_identity" class="signature-area">
      <div class="text-center text-sm leading-7"><p v-if="creatorUnitTitle" class="font-semibold">{{creatorUnitTitle}}</p><p v-if="creatorName" class="font-bold">{{creatorName}}</p></div>
     </footer>
    </div>
    <div v-else class="document-content appendix-content" :style="pageContentStyle(pageIndex-mainPageCount)">
     <div v-if="pageIndex===mainPageCount+1" class="appendix-heading">پیوست</div>
     <section class="letter-body appendix-body" :style="appendixBodyStyle()" v-html="letter.appendix_body||''"></section>
    </div>
   </div>
  </article>
  <div ref="appendixMeasure" class="appendix-measure-root" :style="{width:(pageWidthMm-pageLayout.left-pageLayout.right)+'mm'}" aria-hidden="true"><div class="appendix-heading">پیوست</div><section class="letter-body" :style="appendixBodyStyle()" v-html="letter.appendix_body||''"></section></div>
 </main>
</template>

<style>
  .paper{position:relative;box-sizing:border-box;width:var(--print-page-width,210mm);height:var(--print-page-height,297mm);min-height:var(--print-page-height,297mm);color:#111827;background:#fff;overflow:hidden;}
  .template-coordinate-plane{position:absolute;top:0;right:0;left:0;height:var(--print-page-height,297mm);z-index:1;pointer-events:none;}
 .page-content-window{position:absolute;z-index:2;overflow:hidden;}.document-content{position:absolute;top:0;right:0;left:0;display:flow-root;box-sizing:border-box;width:100%;direction:rtl;text-align:right;unicode-bidi:plaintext;}
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
.appendix-heading{font-weight:700;text-align:center;margin:0 0 8mm;padding-bottom:3mm;border-bottom:1px solid #cbd5e1;}
.appendix-measure-root{position:absolute;left:-100000px;top:0;visibility:hidden;pointer-events:none;overflow:visible;display:flow-root;}
.appendix-content .letter-body{margin-top:0;}
 .letter-body table{max-width:100%!important;border-collapse:collapse;table-layout:fixed;}\n.letter-body td,.letter-body th{max-width:100%!important;min-width:0!important;overflow-wrap:anywhere;}
.letter-body td,.letter-body th{border:1px solid #cbd5e1;padding:4px 7px;}
 .inline-asset{display:block;width:100%;height:auto;max-width:none;max-height:none;object-fit:contain;margin-top:2mm;}
.issuer-item{break-inside:avoid;page-break-inside:avoid;}
.signature-area .inline-asset{width:auto;max-width:42mm;max-height:28mm;}
.signature-area{position:relative;z-index:2;display:flex;align-items:flex-end;justify-content:space-between;gap:8mm;margin-top:16mm;break-inside:avoid;page-break-inside:avoid;}
.page-number{position:absolute;z-index:4;white-space:nowrap;text-align:center;font:9pt Tahoma,sans-serif;color:#475569;direction:ltr;unicode-bidi:isolate;}
.page-number-slash{direction:ltr;unicode-bidi:isolate;}

@media print{
 html,body{width:auto!important;margin:0!important;padding:0!important;background:#fff!important;print-color-adjust:exact;-webkit-print-color-adjust:exact;}
 main{display:block!important;width:var(--print-page-width,210mm)!important;min-height:0!important;margin:0!important;padding:0!important;background:#fff!important;}
 .no-print{display:none!important;}
 /* Print the exact page slices already rendered by the preview. Do not ask Chromium to repaginate the letter body. */
 .paper,.paper.screen-page{display:block!important;position:relative!important;box-sizing:border-box!important;width:var(--print-page-width,210mm)!important;height:var(--print-page-height,297mm)!important;min-height:var(--print-page-height,297mm)!important;margin:0!important;padding:0!important;overflow:hidden!important;box-shadow:none!important;break-after:page;page-break-after:always;}
 .paper:last-child{break-after:auto;page-break-after:auto;}
 .template-coordinate-plane{position:absolute!important;top:0!important;right:0!important;left:0!important;width:100%!important;height:var(--print-page-height,297mm)!important;}
 .letterhead-background{position:absolute!important;inset:0!important;width:auto!important;height:auto!important;background-position:center top!important;background-size:100% 100%!important;}
 .repeat-item,.page-number,.first-page-positioned{position:absolute!important;}
 .page-content-window{position:absolute!important;display:block!important;overflow:hidden!important;}
 .document-content{position:absolute!important;top:0!important;right:0!important;left:0!important;width:100%!important;padding-left:0!important;padding-right:0!important;}
 .page-number bdi{visibility:visible!important;}
 .page-number::after{content:none!important;display:none!important;}
}
@media screen and (max-width:800px){.paper{width:var(--print-page-width,210mm);}}
</style>