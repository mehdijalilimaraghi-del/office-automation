<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, nextTick } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
interface AddressingSnapshot { organizational_title_prefix?:string|null; position?:string|null; personal_salutation?:string|null; professional_title?:string|null; name?:string|null; organization_name?:string|null; department_name?:string|null; }
type Role='meta'|'heading'|'page_number'|'issuer'|'header'|'footer'|'repeat'|'body';
type PageFormat='number_of'|'slash'|'short'|'page';
interface TemplateElement { id:string; kind:'text'|'stamp'|'signature'; role:Role; label:string; content:string; x:number; y:number; width:number; spacing:number; font_size:number; font_family:string; page_format:PageFormat; bold:boolean; align:'right'|'center'|'left'; visible:boolean; }
interface PrintLetter { id:number; attachment_pages:number|null; type:'incoming'|'outgoing'|'internal'; subject:string; body:string|null; letter_number:string|null; registration_number:string|null; registered_at:string|null; sent_at:string|null; recipient_name:string|null; recipient_addressing:AddressingSnapshot|null; external_organization:string|null; external_department:string|null; }
const props=defineProps<{letter:PrintLetter;templateElements:TemplateElement[];pageLayout:{top:number;right:number;bottom:number;left:number;header_height:number;footer_height:number};organization:{name?:string|null;short_name?:string|null;address?:string|null;postal_code?:string|null;phone?:string|null;email?:string|null;website?:string|null};creatorName:string|null;creatorUnitTitle:string|null;managerName:string|null;managerPosition:string|null;managerTitle:string|null;creatorDepartmentName:string|null;stampDataUri:string|null;letterheadDataUri:string|null;signatureDataUri:string|null;printOptions:{show_creator_identity:boolean;show_stamp:boolean;show_department_signature:boolean;letterhead_show_name:boolean;letterhead_show_address:boolean;letterhead_show_phone:boolean;letterhead_show_email:boolean;letterhead_show_website:boolean;font_family:string;};}>();
const withLetterhead=ref(true);
const documentContent=ref<HTMLElement[]>([]);
const firstPagePlane=ref<HTMLElement|null>(null);
const firstPageContentWindow=ref<HTMLElement|null>(null);
function setFirstPagePlane(element:unknown):void { firstPagePlane.value=element instanceof HTMLElement?element:null; }
function setFirstPageContentWindow(element:unknown):void { firstPageContentWindow.value=element instanceof HTMLElement?element:null; }
const measuredBodyOffsetMm=ref(0);
const estimatedPageCount=ref(1);
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
 const yMm=item.y*2.97;
 const topEdge=Math.max(props.pageLayout.top,props.pageLayout.header_height);
 const bottomEdge=297-Math.max(props.pageLayout.bottom,props.pageLayout.footer_height);
 const vertical=yMm-Math.max(topEdge,props.pageLayout.header_height)<=bottomEdge-yMm?'top':'bottom';
 const anchorX=item.align==='right'?item.x:item.align==='left'?item.x+item.width:item.x+(item.width/2);
  const horizontal=anchorX<33.333?'right':anchorX>66.667?'left':'center';
 return '@'+vertical+'-'+horizontal;
});
const pageCss=computed(()=>{
 const contentTop=Math.max(props.pageLayout.top,props.pageLayout.header_height);
 const contentBottom=Math.max(props.pageLayout.bottom,props.pageLayout.footer_height);
 const margins=contentTop+'mm '+props.pageLayout.right+'mm '+contentBottom+'mm '+props.pageLayout.left+'mm';
 const item=pageNumberElement.value;
 const position=pageNumberPosition.value;
 let counterContent='counter(page) " از " counter(pages)';
 if(pageNumberFormat.value==='slash')counterContent='counter(page) "/" counter(pages)';
 if(pageNumberFormat.value==='short')counterContent='"ص" counter(page) " از " counter(pages)';
 if(pageNumberFormat.value==='page')counterContent='"صفحه " counter(page) " از " counter(pages)';
 const yMm=item?item.y*2.97:0;
 const effectiveTop=Math.max(props.pageLayout.top,props.pageLayout.header_height);
 const effectiveBottom=Math.max(props.pageLayout.bottom,props.pageLayout.footer_height);
 const bottomBoundary=297-effectiveBottom;
  const mappedY=item?(yMm<effectiveTop?yMm:yMm>bottomBoundary?yMm:(yMm-effectiveTop<=bottomBoundary-yMm?effectiveTop:bottomBoundary)):0;
  const topPadding=item&&position.startsWith('@top')?Math.min(effectiveTop,Math.max(0,mappedY)):0;
 const bottomPadding=item&&position.startsWith('@bottom')?Math.min(effectiveBottom,Math.max(0,mappedY-bottomBoundary)):0;
 const verticalPadding=item?(pageNumberPosition.value.startsWith('@top')?topPadding:bottomPadding):0;
 const horizontalPadding=item?(item.align==='right'?item.x*2.1:item.align==='left'?Math.max(0,100-item.x-item.width)*2.1:0):0;
 const horizontalSide=item?(item.align==='right'?'padding-right':item.align==='left'?'padding-left':''):'';
 const extraPadding=horizontalSide?horizontalSide+':'+horizontalPadding.toFixed(2)+'mm;':'';
 const counterDirection=pageNumberFormat.value==='slash'?'ltr':'rtl';
 const rule=item?position+'{content:'+counterContent+';font:9pt Tahoma,sans-serif;color:#475569;direction:'+counterDirection+';unicode-bidi:isolate;text-align:'+item.align+';white-space:nowrap;vertical-align:top;padding-top:'+verticalPadding.toFixed(2)+'mm;'+extraPadding+'}':'';
 return '@page{size:A4;margin:'+margins+';}'
  +'@page number_of{size:A4;margin:'+margins+';'+(pageNumberFormat.value==='number_of'?rule:'')+'}'
  +'@page slash{size:A4;margin:'+margins+';'+(pageNumberFormat.value==='slash'?rule:'')+'}'
  +'@page short{size:A4;margin:'+margins+';'+(pageNumberFormat.value==='short'?rule:'')+'}'
  +'@page page{size:A4;margin:'+margins+';'+(pageNumberFormat.value==='page'?rule:'')+'}';
});
const pageWindowStyle=computed(()=>({
 top:Math.max(props.pageLayout.top,props.pageLayout.header_height)+'mm',
 right:props.pageLayout.right+'mm',
 bottom:Math.max(props.pageLayout.bottom,props.pageLayout.footer_height)+'mm',
 left:props.pageLayout.left+'mm',
}));
function pageContentStyle(pageIndex:number):Record<string,string> {
 const pageHeight=297-Math.max(props.pageLayout.top,props.pageLayout.header_height)-Math.max(props.pageLayout.bottom,props.pageLayout.footer_height);
 const offset=Math.max(0,(pageIndex-1)*pageHeight);
 return { transform:'translateY(-'+offset+'mm)' };
}
const paperStyle=computed(()=>({
 fontFamily:props.printOptions.font_family||'Vazirmatn',
 minHeight:'297mm',
}));
const hasCreatorElement=computed(()=>flowIssuer.value.some(item=>item.kind==='text'&&(item.content.includes('{{creator_name}}')||item.content.includes('{{creator_unit_title}}')||item.content.includes('{{manager_name}}')||item.content.includes('{{manager_position}}')||item.content.includes('{{manager_title}}')||item.content.includes('{{creator_department_name}}'))));
const hasSignatureElement=computed(()=>flowIssuer.value.some(item=>item.kind==='signature'));
const hasIssuerAssetElement=computed(()=>hasStampElement.value||hasSignatureElement.value);
const hasStampElement=computed(()=>flowIssuer.value.some(item=>item.kind==='stamp'));
const typeLabel=computed(()=>({incoming:'نامه وارده',outgoing:'نامه صادره',internal:'نامه داخلی'})[props.letter.type]);
const recipientLines=computed(()=>{const a=props.letter.recipient_addressing;if(!a)return [props.letter.recipient_name].filter(Boolean) as string[];return [[a.organizational_title_prefix,a.position].filter(Boolean).join(' '),[a.personal_salutation,a.professional_title,a.name].filter(Boolean).join(' '),[a.organization_name,a.department_name].filter(Boolean).join(' - ')].filter(Boolean) as string[];});
const letterDate=computed(()=>{const value=props.letter.sent_at??props.letter.registered_at;return value?new Intl.DateTimeFormat('fa-IR',{dateStyle:'long'}).format(new Date(value)):'—';});
function resolveTemplate(content:string):string {
 const values:Record<string,string>={organization_name:props.organization.name??'',short_name:props.organization.short_name??'',address:props.organization.address??'',postal_code:props.organization.postal_code??'',tel:props.organization.phone??'',email:props.organization.email??'',website:props.organization.website??'',letter_number:props.letter.letter_number??'',registration_number:props.letter.registration_number??'',date:letterDate.value,recipient_name:props.letter.recipient_name??props.letter.recipient_addressing?.name??'',recipient_organization:props.letter.recipient_addressing?.organization_name??props.letter.external_organization??'',recipient_unit:props.letter.recipient_addressing?.department_name??props.letter.external_department??'',recipient_position:props.letter.recipient_addressing?.position??'',recipient_title:[props.letter.recipient_addressing?.personal_salutation,props.letter.recipient_addressing?.professional_title].filter(Boolean).join(' '),subject:props.letter.subject,letter_type:typeLabel.value,creator_name:props.creatorName??'',creator_unit_title:props.creatorUnitTitle??'',manager_name:props.managerName??'',manager_position:props.managerPosition??'',manager_title:props.managerTitle??props.managerPosition??'',creator_department_name:props.creatorDepartmentName??'',attachment_pages:props.letter.attachment_pages?`پیوست: دارد (${new Intl.NumberFormat('fa-IR').format(props.letter.attachment_pages)} صفحه)`:'پیوست: ندارد',page_number:'',pages:''};
 return content.replace(/\{\{([a-z_]+)\}\}/g,(token,key:string)=>Object.prototype.hasOwnProperty.call(values,key)?values[key]:token);
}
function fontFamily(item:TemplateElement):string{return item.font_family==='default'||!item.font_family?(props.printOptions.font_family||'Vazirmatn'):item.font_family;}
function boxStyle(item:TemplateElement):Record<string,string>{return {width:item.width+'%',marginTop:item.spacing+'mm',marginRight:`calc(${item.x*2.1}mm - ${props.pageLayout.right}mm)`,fontSize:item.font_size+'pt',fontFamily:fontFamily(item),fontWeight:item.bold?'700':'400',textAlign:item.align};}
function positionedStyle(item:TemplateElement):Record<string,string>{return {right:item.x+'%',top:item.y+'%',bottom:'auto',width:item.width+'%',fontSize:item.font_size+'pt',fontFamily:fontFamily(item),fontWeight:item.bold?'700':'400',textAlign:item.align};}
function pageNumberScreenStyle(item:TemplateElement):Record<string,string> {
 const topEdge=Math.max(props.pageLayout.top,props.pageLayout.header_height);
 const bottomEdge=297-Math.max(props.pageLayout.bottom,props.pageLayout.footer_height);
 const requestedY=item.y*2.97;
 const mappedY=requestedY<topEdge?requestedY:requestedY>bottomEdge?requestedY:(requestedY-topEdge<=bottomEdge-requestedY?topEdge:bottomEdge);
 return {...positionedStyle(item),top:(mappedY/297*100)+'%'};
}
function bodyStyle():Record<string,string>{const item=bodyElement.value;return {width:item.width+'%',marginTop:(item.spacing+measuredBodyOffsetMm.value)+'mm',fontSize:item.font_size+'pt',fontFamily:fontFamily(item),fontWeight:item.bold?'700':'400',textAlign:item.align};}
function updateBodyStartOffset():void {
 const plane=firstPagePlane.value;
 const contentWindow=firstPageContentWindow.value;
 const paper=plane?.closest('.paper') as HTMLElement|null;
 if(!plane||!contentWindow||!paper)return;
 const paperRect=paper.getBoundingClientRect();
 const safeTop=contentWindow.getBoundingClientRect().top;
 const pxPerMm=paperRect.width/210;
 if(pxPerMm<=0)return;
 const preceding=Array.from(plane.querySelectorAll<HTMLElement>('.first-page-positioned'));
 const lastBottom=preceding.reduce((bottom,element)=>Math.max(bottom,element.getBoundingClientRect().bottom),safeTop);
 measuredBodyOffsetMm.value=Math.max(0,(lastBottom-safeTop)/pxPerMm);
}
function updateEstimatedPageCount():void {
 const element=documentContent.value[0];
 if(!element)return;
 const pageHeightMm=297-Math.max(props.pageLayout.top,props.pageLayout.header_height)-Math.max(props.pageLayout.bottom,props.pageLayout.footer_height);
 const pageHeightPx=pageHeightMm*96/25.4;
 const flowingHeight=Math.max(0,element.scrollHeight);
 estimatedPageCount.value=Math.max(1,Math.ceil(flowingHeight/Math.max(1,pageHeightPx)));
}
onMounted(async()=>{
 await nextTick();
 updateBodyStartOffset();
 updateEstimatedPageCount();
 if(typeof ResizeObserver!=='undefined'){
  if(documentContent.value[0]){
   contentObserver=new ResizeObserver(()=>{updateBodyStartOffset();updateEstimatedPageCount();});
   contentObserver.observe(documentContent.value[0]);
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
 const current=new Intl.NumberFormat('fa-IR').format(pageIndex), total=new Intl.NumberFormat('fa-IR').format(estimatedPageCount.value);
 if(item.page_format==='slash')return current+'/'+total;
 if(item.page_format==='short')return 'ص'+current+' از '+total;
 if(item.page_format==='page')return 'صفحه '+current+' از '+total;
 return current+' از '+total;
}
function printLetter():void{window.print();}
</script>

<template>
 <Head :title="'پیش‌نمایش چاپ - '+letter.subject"/>
  <component :is="'style'">{{pageCss}}</component>
 <main class="min-h-screen bg-slate-100 px-4 py-6 text-slate-900" dir="rtl">
  <div class="no-print mx-auto mb-4 flex max-w-[210mm] flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
   <div><p class="text-sm font-bold">پیش‌نمایش چاپ نامه</p><p class="mt-1 text-xs text-slate-500">قالب A4 · برآورد پیش‌نمایش: {{estimatedPageCount}} صفحه</p></div>
   <div class="flex flex-wrap items-center gap-3"><label class="inline-flex items-center gap-2 text-sm"><input v-model="withLetterhead" type="checkbox" class="rounded border-slate-300 text-teal-600"/>چاپ با سربرگ</label><button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white" @click="printLetter">چاپ / ذخیره PDF</button><Link :href="route('correspondence.show',letter.id)" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold">بازگشت به نامه</Link></div>
  </div>
  <article v-for="pageIndex in estimatedPageCount" :key="pageIndex" class="paper screen-page mx-auto mb-6 bg-white" :class="'page-format-'+pageNumberFormat" :style="paperStyle" dir="rtl">
   <div class="template-coordinate-plane" :ref="pageIndex===1 ? setFirstPagePlane : undefined">
    <div v-if="withLetterhead && letterheadDataUri" class="letterhead-background" :style="{backgroundImage:'url('+letterheadDataUri+')'}" aria-hidden="true"></div>
    <div v-for="item in repeating" :key="item.id" class="repeat-item" :style="positionedStyle(item)">{{resolveTemplate(item.content)}}</div>
    <div v-for="item in elements.filter(entry=>entry.role==='page_number')" :key="item.id" class="page-number" :style="pageNumberScreenStyle(item)"><bdi dir="ltr">{{pageNumberSample(item,pageIndex)}}</bdi></div>
    <template v-if="pageIndex===1"><div v-for="item in flowMeta" :key="item.id" class="flow-item first-page-positioned" :style="positionedStyle(item)">{{item.kind==='text'?resolveTemplate(item.content):''}}<img v-if="item.kind==='stamp'&&stampDataUri" :src="stampDataUri" alt="مهر سازمان" class="inline-asset"/><img v-else-if="item.kind==='signature'&&signatureDataUri" :src="signatureDataUri" alt="امضای مدیر واحد" class="inline-asset"/></div>
    <div v-for="item in flowHeading" :key="item.id" class="flow-item first-page-positioned" :style="positionedStyle(item)">
     <template v-if="item.content">{{resolveTemplate(item.content)}}</template>
     <template v-else><div v-if="recipientLines.length" class="mb-4 space-y-1 text-sm font-semibold leading-7"><p v-for="(line,index) in recipientLines" :key="index">{{line}}</p></div><h2 class="text-center text-base font-bold leading-8">{{letter.subject}}</h2></template>
    </div></template>
   </div>
   <div class="page-content-window" :ref="pageIndex===1 ? setFirstPageContentWindow : undefined" :style="pageWindowStyle">
    <div ref="documentContent" class="document-content" :style="pageContentStyle(pageIndex)">
    <section class="letter-body" :style="bodyStyle()" v-html="letter.body||''"></section>

    <div v-for="item in flowIssuer" :key="item.id" class="flow-item issuer-item" :style="boxStyle(item)">
     <img v-if="item.kind==='stamp'&&stampDataUri" :src="stampDataUri" alt="مهر سازمان" class="inline-asset"/>
     <img v-else-if="item.kind==='signature'&&signatureDataUri" :src="signatureDataUri" alt="امضای مدیر واحد" class="inline-asset"/>
     <template v-else-if="item.kind==='text'">{{resolveTemplate(item.content)}}</template>
    </div>

    <footer v-if="!hasCreatorElement&&!hasIssuerAssetElement&&printOptions.show_creator_identity" class="signature-area">
     <div v-if="!hasCreatorElement&&printOptions.show_creator_identity" class="text-center text-sm leading-7"><p v-if="creatorUnitTitle" class="font-semibold">{{creatorUnitTitle}}</p><p v-if="creatorName" class="font-bold">{{creatorName}}</p></div>
 
    </footer>
    </div>
   </div>
  </article>
 </main>
</template>

<style>
 .paper{position:relative;box-sizing:border-box;width:210mm;height:297mm;min-height:297mm;color:#111827;background:#fff;overflow:hidden;}
 .template-coordinate-plane{position:absolute;top:0;right:0;left:0;height:297mm;z-index:1;pointer-events:none;}
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
.letter-body table{max-width:100%;border-collapse:collapse;}
.letter-body td,.letter-body th{border:1px solid #cbd5e1;padding:4px 7px;}
 .inline-asset{display:block;width:100%;height:auto;max-width:none;max-height:none;object-fit:contain;margin-top:2mm;}
.issuer-item{break-inside:avoid;page-break-inside:avoid;}
.signature-area .inline-asset{width:auto;max-width:42mm;max-height:28mm;}
.signature-area{position:relative;z-index:2;display:flex;align-items:flex-end;justify-content:space-between;gap:8mm;margin-top:16mm;break-inside:avoid;page-break-inside:avoid;}
.page-number{position:absolute;z-index:4;white-space:nowrap;text-align:center;font:9pt Tahoma,sans-serif;color:#475569;direction:ltr;unicode-bidi:isolate;}
.page-number-slash{direction:ltr;unicode-bidi:isolate;}

@media print{
 html,body{background:#fff!important;print-color-adjust:exact;-webkit-print-color-adjust:exact;}
 main{min-height:0!important;padding:0!important;background:#fff!important;}
 .no-print{display:none!important;}
 .paper{width:auto;height:auto;min-height:0!important;margin:0 0 0!important;box-shadow:none;overflow:visible;}
 .paper.screen-page:not(:first-of-type){display:none!important;}
 .page-content-window{position:static;overflow:visible;}
 .document-content{position:relative;top:auto;right:auto;left:auto;transform:none!important;width:auto;}

 .letterhead-background{position:fixed;}
 .repeat-item{position:fixed;}
  .page-number{display:none!important;}
 .paper.page-format-number_of{page:number_of;}
 .paper.page-format-slash{page:slash;}
 .paper.page-format-short{page:short;}
 .paper.page-format-page{page:page;}
}
@media screen and (max-width:800px){.paper{width:210mm;}}
</style>