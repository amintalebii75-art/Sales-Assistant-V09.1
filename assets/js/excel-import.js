(function(){
'use strict';

const MAX_FILE_SIZE=10*1024*1024;
const MAX_ROWS=5000;
const SAFE_PAYLOAD_BYTES=3_800_000;
const XLSX_MIMES=new Set(['','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip','application/octet-stream']);
const CSV_MIMES=new Set(['','text/csv','application/csv','text/plain','application/vnd.ms-excel','application/octet-stream']);
const TEMPLATE_PATH='SAMPLES/customers-import-template.xlsx';
const FIELD_DEFS=[
  ['name','نام مشتری',['نام مشتری','نام','مشتری','customer name','name']],
  ['company','نام شرکت',['نام شرکت','شرکت','company','company name','organization']],
  ['phone','شماره تماس',['شماره تماس','موبایل','تلفن','شماره موبایل','phone','mobile','telephone']],
  ['phone2','شماره تماس دوم',['شماره تماس دوم','تلفن دوم','موبایل دوم','phone 2','secondary phone']],
  ['city','شهر',['شهر','city']],
  ['province','استان',['استان','province','state']],
  ['industry','صنعت',['صنعت','حوزه فعالیت','industry']],
  ['source','منبع مشتری',['منبع مشتری','منبع','نحوه آشنایی','source','lead source']],
  ['product','محصول موردنیاز',['محصول موردنیاز','محصول','نیاز فنی','product','required product']],
  ['assignee','مسئول مشتری',['مسئول مشتری','مسئول','کارشناس','assignee','owner']],
  ['stage','مرحله فروش',['مرحله فروش','مرحله','وضعیت فروش','stage','pipeline stage']],
  ['note','توضیحات',['توضیحات','یادداشت','شرح','description','notes','note']],
  ['nextFollowUp','تاریخ پیگیری بعدی',['تاریخ پیگیری بعدی','پیگیری بعدی','تاریخ پیگیری','next follow up','follow up date']]
];

const S={
  step:1,file:null,fileType:'',fileMeta:null,sheets:[],sheetIndex:0,headers:[],rows:[],mapping:{},
  duplicatePolicy:'skip',unknownAssignee:'unassigned',stageMappings:{},manualDuplicateActions:{},
  analysis:null,revisionStart:0,result:null,parseError:'',busy:false,payloadInfo:null
};

function safe(v){return typeof window.esc==='function'?window.esc(v):String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
function fa(v){return typeof window.fmt==='function'?window.fmt(v):String(v)}
function norm(v){return String(v??'').replace(/[ي]/g,'ی').replace(/[ك]/g,'ک').replace(/[۰-۹]/g,c=>String(c.charCodeAt(0)-1776)).replace(/[٠-٩]/g,c=>String(c.charCodeAt(0)-1632)).replace(/[\u200c\u200e\u200f]/g,' ').replace(/[_\-–—/\\()\[\]:؛،,.]+/g,' ').replace(/\s+/g,' ').trim().toLowerCase()}
function plain(v){return String(v??'').replace(/[\u0000-\u0008\u000b\u000c\u000e-\u001f]/g,'').trim()}
function normalizePhone(v){let d=norm(v).replace(/\D/g,'');if(d.startsWith('0098'))d=d.slice(4);else if(d.startsWith('98'))d=d.slice(2);if(d&&!d.startsWith('0'))d='0'+d;return d}
function validPhone(v){return !v||/^0\d{9,10}$/.test(v)}
function bytes(n){if(n<1024)return n+' بایت';if(n<1024*1024)return (n/1024).toFixed(1)+' کیلوبایت';return (n/1024/1024).toFixed(1)+' مگابایت'}
function currentUserName(){return SERVER_NAME||currentMember?.()?.name||'کاربر فعلی'}
function deep(v){return JSON.parse(JSON.stringify(v))}
function isoDate(v){
  if(v===null||v===undefined||v==='')return '';
  if(typeof v==='number'||/^\d+(\.\d+)?$/.test(String(v).trim())){
    const n=Number(v);if(n>20000&&n<80000){const d=new Date(Date.UTC(1899,11,30)+Math.floor(n)*86400000);return d.toISOString().slice(0,10)}
  }
  const s=norm(v).replace(/\//g,'-');
  const m=s.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);if(m){const y=+m[1],mo=+m[2],d=+m[3];if(y>=1900&&mo>=1&&mo<=12&&d>=1&&d<=31)return `${y}-${String(mo).padStart(2,'0')}-${String(d).padStart(2,'0')}`}
  const dt=new Date(String(v));return Number.isNaN(dt.getTime())?'':dt.toISOString().slice(0,10)
}
function colIndex(ref){const m=String(ref||'').match(/^([A-Z]+)/i);if(!m)return 0;let n=0;for(const c of m[1].toUpperCase())n=n*26+c.charCodeAt(0)-64;return n-1}
function xml(text){const d=new DOMParser().parseFromString(text,'application/xml');if(d.querySelector('parsererror'))throw new Error('invalid_xml');return d}
function localName(el,name){return [...el.getElementsByTagName('*')].filter(x=>x.localName===name)}

async function parseXlsx(buffer){
  if(typeof JSZip==='undefined')throw new Error('کتابخانه خواندن Excel بارگذاری نشده است.');
  const u=new Uint8Array(buffer);if(!(u[0]===0x50&&u[1]===0x4b))throw new Error('ساختار فایل XLSX معتبر نیست یا پسوند فایل جعلی است.');
  const zip=await JSZip.loadAsync(buffer,{checkCRC32:true});
  const names=Object.keys(zip.files);let uncompressed=0;for(const k of names){uncompressed+=Number(zip.files[k]?._data?.uncompressedSize||0)}
  if(names.length>500||uncompressed>80*1024*1024)throw new Error('ساختار فایل بیش از حد بزرگ یا غیرعادی است.');
  if(!zip.file('[Content_Types].xml')||!zip.file('xl/workbook.xml')||!zip.file('xl/_rels/workbook.xml.rels'))throw new Error('فایل انتخاب‌شده یک Workbook معتبر Excel نیست.');if(!names.some(n=>/^xl\/worksheets\/sheet.+\.xml$/i.test(n)))throw new Error('فایل XLSX فاقد Worksheet معتبر است.');
  const workbookDoc=xml(await zip.file('xl/workbook.xml').async('text'));
  const relFile=zip.file('xl/_rels/workbook.xml.rels');if(!relFile)throw new Error('ارتباط Sheetهای فایل Excel ناقص است.');
  const relDoc=xml(await relFile.async('text'));const rels={};
  localName(relDoc,'Relationship').forEach(r=>rels[r.getAttribute('Id')]=r.getAttribute('Target'));
  let shared=[];const ss=zip.file('xl/sharedStrings.xml');if(ss){const sd=xml(await ss.async('text'));shared=localName(sd,'si').map(si=>localName(si,'t').map(t=>t.textContent||'').join(''))}
  const sheetEls=localName(workbookDoc,'sheet');const sheets=[];
  for(const sh of sheetEls){
    const rid=sh.getAttribute('r:id')||sh.getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships','id');
    let target=rels[rid];if(!target)continue;target=target.replace(/^\//,'');if(!target.startsWith('xl/'))target='xl/'+target.replace(/^\.\//,'');target=target.replace(/\/\.\//g,'/');
    const entry=zip.file(target);if(!entry)continue;const doc=xml(await entry.async('text'));const rows=[];
    for(const rowEl of localName(doc,'row')){
      const row=[];
      for(const c of [...rowEl.children].filter(x=>x.localName==='c')){
        const idx=colIndex(c.getAttribute('r'));const type=c.getAttribute('t')||'';const formula=[...c.children].find(x=>x.localName==='f');
        const v=[...c.children].find(x=>x.localName==='v');const inline=[...c.children].find(x=>x.localName==='is');let val='';
        if(type==='s')val=shared[Number(v?.textContent||0)]??'';
        else if(type==='inlineStr'&&inline)val=localName(inline,'t').map(t=>t.textContent||'').join('');
        else if(type==='b')val=v?.textContent==='1'?'بله':'خیر';
        else if(type==='e')val='';
        else val=v?.textContent??'';
        if(formula&&val==='')val=''; // Formula is never evaluated. Only an existing cached value is read as plain text.
        row[idx]=val;
      }
      while(row.length&&String(row[row.length-1]??'').trim()==='')row.pop();
      if(row.some(v=>String(v??'').trim()!==''))rows.push(row);
      if(rows.length>MAX_ROWS+1)break;
    }
    sheets.push({name:plain(sh.getAttribute('name')||`Sheet ${sheets.length+1}`),rows,truncated:rows.length>MAX_ROWS+1});
  }
  if(!sheets.length)throw new Error('هیچ Sheet قابل خواندنی در فایل پیدا نشد.');
  return sheets;
}

async function decodeCsv(buffer){
  const b=new Uint8Array(buffer);let enc='utf-8',off=0;
  if(b[0]===0xFF&&b[1]===0xFE){enc='utf-16le';off=2}else if(b[0]===0xFE&&b[1]===0xFF){enc='utf-16be';off=2}else if(b[0]===0xEF&&b[1]===0xBB&&b[2]===0xBF){off=3}
  const sample=b.slice(off,Math.min(b.length,4096));if(!off&&sample.filter(x=>x===0).length>0)throw new Error('محتوای فایل CSV متنی نیست یا پسوند آن جعلی است.');
  const abnormal=sample.filter(x=>(x<9)||(x>13&&x<32)).length;if(sample.length&&abnormal/sample.length>0.02)throw new Error('محتوای باینری غیرعادی در فایل CSV شناسایی شد.');
  try{const text=new TextDecoder(enc,{fatal:true}).decode(buffer.slice(off));if(!text.trim())throw new Error('empty');return text}catch(e){throw new Error('Encoding فایل CSV پشتیبانی نمی‌شود یا محتوای آن متنی نیست.')}
}
async function parseCsv(buffer){const text=await decodeCsv(buffer);const rows=window.parseCSV?window.parseCSV(text):text.split(/\r?\n/).map(x=>x.split(','));return [{name:'CSV',rows:rows.slice(0,MAX_ROWS+2),truncated:rows.length>MAX_ROWS+1}]}

function reset(){Object.assign(S,{step:1,file:null,fileType:'',fileMeta:null,sheets:[],sheetIndex:0,headers:[],rows:[],mapping:{},duplicatePolicy:'skip',unknownAssignee:'unassigned',stageMappings:{},manualDuplicateActions:{},analysis:null,revisionStart:Number(SERVER_REVISION||0),result:null,parseError:'',busy:false,payloadInfo:null})}
function progress(){const labels=['انتخاب فایل','انتخاب Sheet','تطبیق ستون‌ها','پیش‌نمایش','نتیجه'];return `<div class="ei-steps">${labels.map((x,i)=>`<div class="ei-step ${S.step===i+1?'active':S.step>i+1?'done':''}"><span>${i+1}</span><b>${x}</b></div>`).join('')}</div>`}
function shell(body,footer=''){return `<div class="ei-shell"><div class="ei-header"><div><span class="v04-kicker">ورود گروهی مشتری</span><h2>ورود مشتریان از Excel</h2><p>فایل در مرورگر خوانده می‌شود و پیش از تأیید نهایی هیچ داده‌ای ذخیره نخواهد شد.</p></div><button class="icon-btn" onclick="ExcelImportV042.cancel()" aria-label="بستن">×</button></div>${progress()}<div class="ei-body">${body}</div><div class="ei-footer">${footer}</div></div>`}
function render(){const mc=document.getElementById('modalContent');if(!mc)return;mc.className='modal-card wide ei-modal-card';document.getElementById('modal')?.classList.add('excel-import-open');if(S.step===1)renderFile();else if(S.step===2)renderSheet();else if(S.step===3)renderMapping();else if(S.step===4)renderPreview();else renderResult()}
function setBusy(v){S.busy=v;render()}

function renderFile(){
 const info=S.fileMeta?`<div class="ei-file-card"><div class="ei-file-icon">${S.fileType==='xlsx'?'X':'CSV'}</div><div><b>${safe(S.fileMeta.name)}</b><span>${bytes(S.fileMeta.size)} · ${safe(S.fileMeta.mime||'MIME اعلام نشده')} · ${fa(S.sheets.length)} Sheet</span></div><button class="btn small" onclick="ExcelImportV042.clearFile()">تغییر فایل</button></div>`:'';
 const error=S.parseError?`<div class="alert danger">${safe(S.parseError)}</div>`:'';
 const body=`<div class="ei-dropzone"><input type="file" id="eiFile" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" onchange="ExcelImportV042.chooseFile(event)"><div class="ei-drop-mark">↑</div><h3>فایل XLSX یا CSV را انتخاب کنید</h3><p>حداکثر ۱۰ مگابایت و ۵۰۰۰ ردیف در هر عملیات</p><small>برای فایل‌های قدیمی XLS ابتدا در Excel یا LibreOffice آن‌ها را به XLSX تبدیل کنید.</small></div>${S.busy?'<div class="ui-state"><span class="ui-spinner"></span><div><b>در حال بررسی فایل</b><div class="mini muted">Signature، ساختار Workbook و محتوای فایل کنترل می‌شود.</div></div></div>':''}${error}${info}<div class="ei-help"><a class="btn soft" href="${TEMPLATE_PATH}" download>دانلود قالب نمونه XLSX</a><span>فایل در مرورگر پردازش می‌شود و پیش از تأیید نهایی هیچ داده‌ای ذخیره نمی‌شود.</span></div>`;
 renderShell(body,S.fileMeta?`<button class="btn" onclick="ExcelImportV042.cancel()">لغو</button><button class="btn primary" onclick="ExcelImportV042.nextSheet()">ادامه</button>`:`<button class="btn" onclick="ExcelImportV042.cancel()">بستن</button>`);
}
function renderShell(body,footer){const mc=document.getElementById('modalContent');mc.innerHTML=shell(body,footer)}
function renderSheet(){const body=`<div class="ei-section"><h3>Sheet موردنظر را انتخاب کنید</h3><p>ردیف اول Sheet به‌عنوان عنوان ستون‌ها در نظر گرفته می‌شود.</p><div class="ei-sheet-grid">${S.sheets.map((s,i)=>`<label class="ei-sheet ${S.sheetIndex===i?'selected':''}"><input type="radio" name="eiSheet" value="${i}" ${S.sheetIndex===i?'checked':''} onchange="ExcelImportV042.selectSheet(${i})"><span><b>${safe(s.name)}</b><small>${fa(Math.max(0,s.rows.length-1))} ردیف داده${s.truncated?' · بیش از حد مجاز':''}</small></span></label>`).join('')}</div></div>`;renderShell(body,`<button class="btn" onclick="ExcelImportV042.back(1)">مرحله قبل</button><button class="btn primary" onclick="ExcelImportV042.prepareMapping()">شناسایی ستون‌ها</button>`)}
function autoMapping(headers){const out={};FIELD_DEFS.forEach(([key,,aliases])=>{let idx=-1,best=0;headers.forEach((h,i)=>{const nh=norm(h);aliases.forEach(a=>{const na=norm(a);const score=nh===na?100:(nh.includes(na)||na.includes(nh)?60:0);if(score>best){best=score;idx=i}})});out[key]=idx});return out}
function renderMapping(){
 const opts=(selected=-1)=>`<option value="-1">وارد نشود</option>${S.headers.map((h,i)=>`<option value="${i}" ${Number(selected)===i?'selected':''}>${safe(h||'ستون بدون عنوان '+(i+1))}</option>`).join('')}`;
 const rows=FIELD_DEFS.map(([k,l])=>`<div class="ei-map-row"><label>${safe(l)}</label><select onchange="ExcelImportV042.map('${k}',this.value)">${opts(S.mapping[k])}</select></div>`).join('');
 const body=`<div class="ei-grid-two"><section class="ei-section"><h3>تطبیق ستون‌ها با CRM</h3><p>تطبیق خودکار انجام شده است و می‌توانید هر ستون را تغییر دهید.</p><div class="alert info"><b>قانون اعتبار هر ردیف</b><div class="mini">برای هر ردیف، وجود حداقل یکی از موارد نام مشتری، نام شرکت یا شماره تماس الزامی است.</div></div><div class="ei-mapping">${rows}</div></section><aside class="ei-options"><h3>رفتار ورود</h3><div class="field"><label>مشتری تکراری</label><select onchange="ExcelImportV042.setOption('duplicatePolicy',this.value)"><option value="skip" ${S.duplicatePolicy==='skip'?'selected':''}>وارد نشود (پیش‌فرض)</option><option value="fill" ${S.duplicatePolicy==='fill'?'selected':''}>فقط فیلدهای خالی تکمیل شود</option><option value="update" ${S.duplicatePolicy==='update'?'selected':''}>اطلاعات موجود به‌روزرسانی شود</option><option value="manual" ${S.duplicatePolicy==='manual'?'selected':''}>برای هر مورد دستی تصمیم می‌گیرم</option></select></div><div class="field"><label>اگر مسئول خالی یا ناشناخته بود</label><select onchange="ExcelImportV042.setOption('unknownAssignee',this.value)"><option value="unassigned" ${S.unknownAssignee==='unassigned'?'selected':''}>بدون مسئول ثبت شود</option><option value="importer" ${S.unknownAssignee==='importer'?'selected':''}>به کاربر واردکننده تخصیص داده شود</option></select></div><div class="alert info"><b>رفتار امن</b><div class="mini">نام مسئول ناشناخته حساب جدید نمی‌سازد. Stage جدید نیز از روی فایل ساخته نمی‌شود.</div></div></aside></div>`;
 renderShell(body,`<button class="btn" onclick="ExcelImportV042.back(2)">مرحله قبل</button><button class="btn primary" onclick="ExcelImportV042.buildPreview()">ساخت پیش‌نمایش</button>`);
}

function stageId(raw){const n=norm(raw);if(!n)return '';const number=Number(n);if(number>=1&&number<=STAGES.length)return STAGES[number-1].id;const found=STAGES.find(s=>norm(s.id)===n||norm(s.label)===n);return found?.id||S.stageMappings[n]||''}
function memberId(raw){const n=norm(raw);if(!n)return '';const m=(state.team||[]).find(x=>norm(x.name)===n||norm(x.id)===n);return m?.id||''}
function mappedIndex(key){const i=Number(S.mapping[key]);return Number.isInteger(i)&&i>=0?i:-1}
function getCell(row,key){const i=mappedIndex(key);return i>=0?plain(row[i]??''):''}
function hasFileValue(row,key){return mappedIndex(key)>=0&&getCell(row,key)!==''}
function importerMemberId(){return currentMember?.()?.id||''}
function buildCustomer(row,rowNumber){
 const importedFields=new Set();
 const take=(key,prop=key,transform=plain)=>{const raw=getCell(row,key);if(raw==='')return '';const val=transform(raw);if(val!==''&&val!==null&&val!==undefined)importedFields.add(prop);return val};
 const name=take('name','name'),company=take('company','company'),phoneRaw=getCell(row,'phone'),phone=phoneRaw?normalizePhone(phoneRaw):'';
 if(phoneRaw)importedFields.add('phone');
 const phone2Raw=getCell(row,'phone2'),phone2=phone2Raw?normalizePhone(phone2Raw):'';if(phone2Raw)importedFields.add('phone2');
 const issues=[];
 if(!name&&!company&&!phoneRaw)issues.push(['نام/شرکت/شماره تماس','حداقل یکی از نام مشتری، نام شرکت یا شماره تماس لازم است.']);
 if(phoneRaw&&!validPhone(phone))issues.push(['شماره تماس','شماره ایرانی معتبر مانند 09121234567 وارد کنید.']);
 if(phone2Raw&&!validPhone(phone2))issues.push(['شماره تماس دوم','شماره دوم معتبر نیست.']);
 const assigneeRaw=getCell(row,'assignee'),matchedAssignee=memberId(assigneeRaw);let assignee='';let unknownAssignee='';let unassignedReason='';
 if(assigneeRaw&&matchedAssignee){assignee=matchedAssignee;importedFields.add('assignee')}
 else if(assigneeRaw&&!matchedAssignee){unknownAssignee=assigneeRaw;assignee=S.unknownAssignee==='importer'?importerMemberId():'';unassignedReason='unknown'}
 else {assignee=S.unknownAssignee==='importer'?importerMemberId():'';unassignedReason='blank'}
 const stageRaw=getCell(row,'stage'),stageMapped=stageId(stageRaw),unknownStage=stageRaw&&!stageMapped;
 if(stageRaw&&stageMapped)importedFields.add('stage');
 const nextRaw=getCell(row,'nextFollowUp'),nextFollowUp=nextRaw?isoDate(nextRaw):'';
 if(nextRaw&&nextFollowUp)importedFields.add('nextFollowUp');
 if(nextRaw&&!nextFollowUp)issues.push(['تاریخ پیگیری بعدی','تاریخ قابل تشخیص نیست.']);
 const industry=take('industry','industry'),city=take('city','city'),province=take('province','province'),sourceRaw=take('source','source'),product=take('product','technicalNeed'),note=take('note','note');
 const c={id:'',name:name||company||phone||`مشتری ردیف ${rowNumber}`,company,industry,city,province,contact:name,phone:phone||phoneRaw,phone2,address:'',source:sourceRaw||'ورود Excel',assignee,stage:stageMapped||'new',estimatedVolume:0,nextFollowUp,paymentPreference:'',competitor:'',technicalNeed:product,note,score:{fit:3,volume:3,decision:2,urgency:2,payment:2},createdAt:new Date().toISOString(),updatedAt:new Date().toISOString()};
 return {rowNumber,raw:row.slice(),customer:c,phone,phoneRaw,issues,importedFields:[...importedFields],unknownStage:unknownStage?stageRaw:'',unknownAssignee,unassignedReason,sheetName:S.sheets[S.sheetIndex]?.name||'Sheet'};
}
function errorRecord(item,field,reason,suggestion,severity='error'){return {row:item.rowNumber,sheet:item.sheetName,name:item.customer.name||'',company:item.customer.company||'',phone:item.phoneRaw||item.customer.phone||'',raw:item.raw,reason,field,suggestion,severity}}
function analyze(){
 const existingByPhone=new Map();(state.customers||[]).forEach(c=>{const p=normalizePhone(c.phone);if(p&&!existingByPhone.has(p))existingByPhone.set(p,c)});
 const seen=new Set(),items=[],errors=[],unknownStages=new Set(),unknownAssignees=new Set();
 S.rows.slice(0,MAX_ROWS).forEach((r,i)=>{const item=buildCustomer(r,i+2);if(item.unknownStage){unknownStages.add(item.unknownStage);errors.push(errorRecord(item,'مرحله فروش',`مرحله «${item.unknownStage}» با Pipeline فعلی تطبیق نداشت.`,`آن را در پیش‌نمایش به یکی از مراحل موجود Map کنید؛ در غیر این صورت سرنخ جدید استفاده می‌شود.`,'warning'))}if(item.unknownAssignee){unknownAssignees.add(item.unknownAssignee);errors.push(errorRecord(item,'مسئول مشتری',`مسئول «${item.unknownAssignee}» شناخته نشد.`,`نام را با عضو معتبر تطبیق دهید یا سیاست مسئول خالی/ناشناخته را انتخاب کنید.`,'warning'))}if(item.issues.length){item.status='invalid';item.issues.forEach(([field,reason])=>errors.push(errorRecord(item,field,reason,'مقدار ستون مربوط را اصلاح و فایل را دوباره بررسی کنید.')));items.push(item);return}let dup=null;if(item.phone)dup=existingByPhone.get(item.phone)||null;if(!dup&&item.phone&&seen.has(item.phone))dup={id:'__within_file__',name:'ردیف قبلی همین فایل'};if(item.phone)seen.add(item.phone);if(dup){item.status='duplicate';item.duplicate=dup}else item.status='valid';items.push(item)});
 const valid=items.filter(x=>x.status==='valid').length,invalid=items.filter(x=>x.status==='invalid').length,duplicates=items.filter(x=>x.status==='duplicate').length;let willImport=valid,willUpdate=0,duplicateRejected=0;
 items.filter(x=>x.status==='duplicate').forEach(x=>{let action=S.duplicatePolicy;if(action==='manual')action=S.manualDuplicateActions[x.rowNumber]||'skip';if(x.duplicate?.id==='__within_file__')action='skip';x.duplicateAction=action;if(action==='fill'||action==='update'){willUpdate++;willImport++}else{duplicateRejected++;errors.push(errorRecord(x,'شماره تماس','شماره تماس تکراری است و طبق سیاست انتخابی وارد نشد.','سیاست تکراری‌ها را تغییر دهید یا شماره را اصلاح کنید.'))}});
 const unknownAssigneeCount=items.filter(x=>!!x.unknownAssignee).length;
 const unassignedCount=items.filter(x=>x.status!=='invalid'&&!x.customer.assignee).length;
 return {items,errors,unknownStages:[...unknownStages],unknownAssignees:[...unknownAssignees],total:S.rows.length,valid,invalid,duplicates,duplicateRejected,willImport,willUpdate,unknownAssigneeCount,unassignedCount,ignored:S.rows.length-willImport};
}
function stageMapControls(a){if(!a.unknownStages.length)return '';return `<div class="ei-warning-block"><h4>مرحله‌های ناشناخته</h4><p>Stage جدید ساخته نمی‌شود. برای هر مقدار یکی از مراحل فعلی را انتخاب کنید.</p>${a.unknownStages.map((raw,index)=>`<div class="ei-stage-map"><span>${safe(raw)}</span><select onchange="ExcelImportV042.mapStageAt(${index},this.value)"><option value="">سرنخ جدید (پیش‌فرض)</option>${STAGES.map(s=>`<option value="${s.id}" ${S.stageMappings[norm(raw)]===s.id?'selected':''}>${safe(s.label)}</option>`).join('')}</select></div>`).join('')}</div>`}
function duplicateControls(a){if(S.duplicatePolicy!=='manual'||!a.duplicates)return '';return `<div class="ei-warning-block"><h4>تصمیم دستی برای موارد تکراری</h4>${a.items.filter(x=>x.status==='duplicate').slice(0,50).map(x=>`<div class="ei-stage-map"><span>ردیف ${fa(x.rowNumber)} · ${safe(x.customer.name)} · ${safe(x.phone)}</span><select onchange="ExcelImportV042.manualDuplicate(${x.rowNumber},this.value)"><option value="skip">وارد نشود</option>${x.duplicate?.id!=='__within_file__'?`<option value="fill" ${S.manualDuplicateActions[x.rowNumber]==='fill'?'selected':''}>فقط تکمیل فیلدهای خالی</option><option value="update" ${S.manualDuplicateActions[x.rowNumber]==='update'?'selected':''}>به‌روزرسانی اطلاعات</option>`:''}</select></div>`).join('')}</div>`}
function previewTable(a){const xs=a.items.slice(0,10);return `<div class="ei-preview-table"><table><thead><tr><th>ردیف</th><th>نام/شرکت</th><th>تلفن</th><th>مسئول</th><th>مرحله</th><th>نتیجه</th></tr></thead><tbody>${xs.map(x=>`<tr><td>${fa(x.rowNumber)}</td><td>${safe(x.customer.name)}<small>${safe(x.customer.company||'')}</small></td><td>${safe(x.customer.phone||'—')}</td><td>${safe(memberName(x.customer.assignee)||'بدون مسئول')}${x.unknownAssignee?'<small class="danger-text">نام مسئول تطبیق نشد</small>':''}</td><td>${safe(stageObj(x.customer.stage).label)}</td><td><span class="badge ${x.status==='valid'?'ok':x.status==='duplicate'?'warn':'danger'}">${x.status==='valid'?'معتبر':x.status==='duplicate'?'تکراری':'نامعتبر'}</span></td></tr>`).join('')}</tbody></table></div><div class="ei-preview-cards">${xs.map(x=>`<article><div><b>ردیف ${fa(x.rowNumber)} · ${safe(x.customer.name)}</b><span>${safe(x.customer.phone||'بدون شماره')}</span></div><span class="badge ${x.status==='valid'?'ok':x.status==='duplicate'?'warn':'danger'}">${x.status==='valid'?'معتبر':x.status==='duplicate'?'تکراری':'نامعتبر'}</span></article>`).join('')}</div>`}
function requestPayloadInfo(draft,expectedRevision,selectedRows){const request={data:draft,expected_revision:expectedRevision,state_context_token:String(window.STATE_CONTEXT_TOKEN||STATE_CONTEXT_TOKEN||''),operation:'excel_import'};const text=JSON.stringify(request);const size=new TextEncoder().encode(text).byteLength;const current=new TextEncoder().encode(JSON.stringify({data:state,expected_revision:expectedRevision})).byteLength;const suggested=size>SAFE_PAYLOAD_BYTES?Math.max(1,Math.floor(Number(selectedRows||1)*(SAFE_PAYLOAD_BYTES/size)*0.9)):Number(selectedRows||0);return {request,text,size,current,suggested,limit:SAFE_PAYLOAD_BYTES}}
function renderPreview(){const a=S.analysis||analyze();S.analysis=a;let estimate=null;try{const built=buildDraft(a);estimate=requestPayloadInfo(built.draft,S.revisionStart,a.willImport)}catch(e){}const payloadAlert=S.payloadInfo&&S.payloadInfo.size>SAFE_PAYLOAD_BYTES?`<div class="alert danger"><b>حجم اطلاعات بیش از ظرفیت فعلی است</b><div class="mini">حجم فعلی: ${bytes(S.payloadInfo.current)} · پس از Import: ${bytes(S.payloadInfo.size)} · ردیف انتخابی: ${fa(a.willImport)} · پیشنهاد تقریبی: حداکثر ${fa(S.payloadInfo.suggested)} ردیف. فایل را به چند بخش کوچک‌تر تقسیم کنید.</div></div>`:(estimate?`<div class="alert info"><b>کنترل ظرفیت State</b><div class="mini">حجم فعلی ${bytes(estimate.current)} و حجم تقریبی پس از Import ${bytes(estimate.size)} از حد ایمن ${bytes(SAFE_PAYLOAD_BYTES)} است.</div></div>`:'');const body=`<div class="ei-summary"><div><small>کل ردیف‌ها</small><b>${fa(a.total)}</b></div><div><small>معتبر</small><b>${fa(a.valid)}</b></div><div><small>ناقص/نامعتبر</small><b>${fa(a.invalid)}</b></div><div><small>تکراری</small><b>${fa(a.duplicates)}</b></div><div><small>ثبت یا به‌روزرسانی</small><b>${fa(a.willImport)}</b></div><div><small>بدون مسئول</small><b>${fa(a.unassignedCount)}</b></div></div>${payloadAlert}${stageMapControls(a)}${duplicateControls(a)}${a.unknownAssignees.length?`<div class="alert info"><b>مسئولان تطبیق‌داده‌نشده:</b> ${a.unknownAssignees.map(safe).join('، ')}<div class="mini">سیاست «${S.unknownAssignee==='importer'?'تخصیص به کاربر واردکننده':'ثبت بدون مسئول'}» اعمال می‌شود و حساب جدید ساخته نمی‌شود.</div></div>`:''}<section class="ei-section"><h3>پیش‌نمایش ۱۰ ردیف اول</h3>${previewTable(a)}</section><div class="alert warn"><b>تأیید نهایی لازم است</b><div class="mini">این عملیات دقیقاً یک Save کنترل‌شده ایجاد می‌کند. در صورت تغییر Revision، پاسخ 409 مدیریت و فایل و Mapping حفظ می‌شود.</div></div>`;renderShell(body,`<button class="btn" onclick="ExcelImportV042.back(3)">مرحله قبل</button><button class="btn primary" ${(a.willImport&&!(S.payloadInfo&&S.payloadInfo.size>SAFE_PAYLOAD_BYTES))?'':'disabled'} onclick="ExcelImportV042.confirmImport()">تأیید و ورود ${fa(a.willImport)} ردیف</button>`)}
function renderResult(){const r=S.result;if(!r){S.step=4;return renderPreview()}const partial=r.ok&&(r.invalid||r.duplicateRejected||r.unknownAssignees);const tone=r.ok?(partial?'warn':'ok'):'danger';const title=r.ok?(partial?'ورود اطلاعات با موفقیت نسبی انجام شد':'ورود اطلاعات با موفقیت انجام شد'):'ورود اطلاعات انجام نشد';const body=`<div class="ei-result"><div class="ei-result-mark ${tone}">${r.ok?'✓':'!'}</div><h3>${title}</h3><p>${safe(r.message||'')}</p>${r.conflict?`<div class="alert danger"><b>تعارض نسخه</b><div class="mini">State سرور پس از انتخاب فایل تغییر کرده است. داده‌های نشست دیگر overwrite نشدند و فایل و Mapping شما حفظ شده‌اند.</div></div>`:''}<div class="ei-result-grid"><div><small>زمان</small><b>${safe(r.time||'—')}</b></div><div><small>کاربر</small><b>${safe(r.user||'—')}</b></div><div><small>فایل</small><b>${safe(r.file||'—')}</b></div><div><small>کل ردیف</small><b>${fa(r.total||0)}</b></div><div><small>مشتری جدید</small><b>${fa(r.added||0)}</b></div><div><small>به‌روزرسانی‌شده</small><b>${fa(r.updated||0)}</b></div><div><small>تکراری ردشده</small><b>${fa(r.duplicateRejected||0)}</b></div><div><small>نامعتبر</small><b>${fa(r.invalid||0)}</b></div><div><small>مسئول ناشناخته</small><b>${fa(r.unknownAssignees||0)}</b></div><div><small>بدون مسئول</small><b>${fa(r.unassigned||0)}</b></div><div><small>Revision قبل</small><b>${fa(r.revisionBefore??S.revisionStart)}</b></div><div><small>Revision بعد</small><b>${fa(r.revisionAfter??'—')}</b></div></div>${r.errors?.length?`<button class="btn" onclick="ExcelImportV042.downloadErrors()">دانلود گزارش خطا CSV</button>`:''}</div>`;const footer=r.conflict?`<button class="btn" onclick="ExcelImportV042.cancel()">بستن</button><button class="btn primary" onclick="ExcelImportV042.reloadAndRetry()">بارگذاری نسخه سرور و بررسی دوباره</button>`:`<button class="btn" onclick="ExcelImportV042.cancel()">بستن</button><button class="btn primary" onclick="ExcelImportV042.restart()">ورود فایل دیگر</button>`;renderShell(body,footer)}
function buildDraft(a){const draft=deep(state);const existing=new Map();draft.customers.forEach(c=>{const p=normalizePhone(c.phone);if(p&&!existing.has(p))existing.set(p,c)});let added=0,updated=0;const now=new Date().toISOString();for(const item of a.items){if(item.status==='invalid')continue;if(item.status==='valid'){const c=deep(item.customer);c.id=`c${Date.now()}_${item.rowNumber}_${Math.random().toString(36).slice(2,7)}`;draft.customers.push(c);if(item.phone)existing.set(item.phone,c);added++;continue}const action=item.duplicateAction;if(action==='skip'||item.duplicate?.id==='__within_file__')continue;const target=existing.get(item.phone);if(!target)continue;const source=item.customer;for(const k of item.importedFields){const val=source[k];if(val===undefined||val===null||String(val).trim()==='')continue;if(action==='fill'){if(target[k]===undefined||target[k]===null||String(target[k]).trim()==='')target[k]=val}else if(action==='update')target[k]=val}target.updatedAt=now;updated++}draft.project.lastSaved=new Date().toLocaleString('fa-IR-u-ca-persian');draft.project.savedAt=Date.now();return {draft,added,updated}}
function sleep(ms){return new Promise(r=>setTimeout(r,ms))}
async function settlePendingSave(){if(_serverSaveTimer){clearTimeout(_serverSaveTimer);_serverSaveTimer=null}if(_serverSaveQueued&&!_serverSaveInFlight&&!SYNC_CONFLICT)await flushServerSave();const deadline=Date.now()+12000;while(_serverSaveInFlight&&Date.now()<deadline)await sleep(50);if(_serverSaveInFlight)throw new Error('save_busy');if(_serverSaveQueued&&!SYNC_CONFLICT){await flushServerSave();while(_serverSaveInFlight&&Date.now()<deadline)await sleep(50)}if(SYNC_CONFLICT)throw new Error('revision_conflict');if(_serverSaveInFlight||_serverSaveQueued)throw new Error('save_busy')}
async function confirmImport(){const a=S.analysis=analyze();if(!a.willImport)return;if(!confirm(`ورود ${a.willImport} ردیف تأیید شود؟`))return;S.busy=true;setSyncStatus?.('saving','در حال آماده‌سازی Import','کنترل ذخیره‌های در انتظار و Revision');try{await settlePendingSave();S.revisionStart=Number(SERVER_REVISION||0);if(SYNC_CONFLICT)throw new Error('revision_conflict');const built=buildDraft(a);const payload=requestPayloadInfo(built.draft,S.revisionStart,a.willImport);S.payloadInfo=payload;if(payload.size>SAFE_PAYLOAD_BYTES){S.busy=false;S.step=4;render();return}setSyncStatus?.('saving','در حال ورود Excel','ارسال یک Save کنترل‌شده با Revision '+S.revisionStart);const resp=await fetch('api.php?action=save',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':window.HIPPO_AUTH?.csrf_token||''},body:payload.text});if(resp.status===401){location.href='login.php';return}const j=await resp.json().catch(()=>null);if(resp.status===409){SYNC_CONFLICT=true;SERVER_REVISION=Number(j?.current_revision||SERVER_REVISION||0);setSyncStatus?.('conflict','تعارض نسخه','ورود Excel متوقف شد');showSyncAlert?.('conflict','تعارض هنگام ورود Excel','نسخه سرور تغییر کرده است؛ اطلاعات نشست دیگر حذف نشد.');S.result={ok:false,conflict:true,message:'سرور پاسخ 409 Conflict برگرداند و هیچ داده‌ای از این Import ذخیره نشد.',time:new Date().toLocaleString('fa-IR-u-ca-persian'),user:currentUserName(),file:S.file.name,total:a.total,duplicateRejected:a.duplicateRejected,invalid:a.invalid,unknownAssignees:a.unknownAssigneeCount,unassigned:a.unassignedCount,revisionBefore:S.revisionStart,revisionAfter:SERVER_REVISION,errors:a.errors};S.step=5;return render()}if(resp.status===400&&j?.error==='bad_payload'){throw new Error('bad_payload')}if(resp.status===403&&j?.error==='customer_exists_forbidden'){throw new Error('customer_exists_forbidden')}if(resp.status===403&&j?.error==='forbidden'){throw new Error('forbidden')}if(!resp.ok||!j?.ok)throw new Error(j?.error||'save_failed');state=built.draft;const before=S.revisionStart;SERVER_REVISION=Number(j.revision??before);STATE_CONTEXT_TOKEN=String(j.state_context_token||STATE_CONTEXT_TOKEN||'');SERVER_STATE_LOADED=true;SYNC_CONFLICT=false;_serverSaveQueued=false;if(_serverSaveTimer){clearTimeout(_serverSaveTimer);_serverSaveTimer=null}if(j.reload_required&&typeof serverLoadState==='function')await serverLoadState(true);else{persistLocalState?.();renderAll?.()}hideSyncAlert?.();setSyncStatus?.('saved','ذخیره شد · نسخه '+SERVER_REVISION,'ورود Excel با یک Save ثبت شد');S.result={ok:true,message:`${built.added} مشتری جدید ثبت و ${built.updated} مشتری موجود به‌روزرسانی شد.`,time:new Date().toLocaleString('fa-IR-u-ca-persian'),user:currentUserName(),file:S.file.name,total:a.total,added:built.added,updated:built.updated,duplicateRejected:a.duplicateRejected,invalid:a.invalid,unknownAssignees:a.unknownAssigneeCount,unassigned:a.unassignedCount,revisionBefore:before,revisionAfter:SERVER_REVISION,errors:a.errors};S.step=5;toast?.('ورود مشتریان پایان یافت');render()}catch(e){const code=e?.message||'save_failed';let msg='ارتباط با سرور یا ذخیره‌سازی انجام نشد. State قبلی محفوظ است.';if(code==='bad_payload')msg='حجم اطلاعات پس از ورود مشتریان از ظرفیت فعلی سیستم بیشتر می‌شود. فایل را به چند بخش کوچک‌تر تقسیم کنید.';else if(code==='save_busy')msg='ذخیره دیگری هنوز در حال اجرا است. پس از پایان ذخیره دوباره تلاش کنید.';else if(code==='revision_conflict')msg='به‌دلیل تعارض Revision، Import متوقف شد و اطلاعات قبلی حفظ شد.';else if(code==='customer_exists_forbidden')msg='این شماره در سیستم وجود دارد و شما مجاز به مشاهده یا تغییر آن نیستید.';else if(code==='forbidden')msg='مجوز ورود مشتریان از Excel برای این حساب فعال نیست.';setSyncStatus?.('error','خطای ورود Excel','هیچ داده‌ای روی سرور ذخیره نشد');S.result={ok:false,conflict:code==='revision_conflict',message:msg,time:new Date().toLocaleString('fa-IR-u-ca-persian'),user:currentUserName(),file:S.file?.name,total:a.total,duplicateRejected:a.duplicateRejected,invalid:a.invalid,unknownAssignees:a.unknownAssigneeCount,unassigned:a.unassignedCount,revisionBefore:S.revisionStart,errors:a.errors};S.step=5;render()}finally{S.busy=false}}
function downloadErrors(){const es=S.result?.errors||S.analysis?.errors||[];if(!es.length)return toast?.('گزارش خطایی وجود ندارد');const rows=[['شماره ردیف','نام Sheet','نام مشتری','نام شرکت','شماره تماس','فیلد مشکل‌دار','علت رد','پیشنهاد اصلاح'],...es.map(e=>[e.row,e.sheet||'',e.name||'',e.company||'',e.phone||'',e.field,e.reason,e.suggestion])];csvDownload('customers-import-errors.csv',rows)}

const api={
 open(){reset();openModal('<div></div>',true);render()},
 async chooseFile(e){const file=e.target.files?.[0];if(!file)return;S.parseError='';S.file=null;S.fileMeta=null;S.sheets=[];S.busy=true;render();try{if(file.size===0)throw new Error('فایل خالی است.');if(file.size>MAX_FILE_SIZE)throw new Error('حجم فایل بیشتر از ۱۰ مگابایت است.');const ext=(file.name.split('.').pop()||'').toLowerCase();if(ext==='xls')throw new Error('فرمت XLS پشتیبانی نمی‌شود. ابتدا فایل را در Excel یا LibreOffice به XLSX تبدیل کنید.');if(!['xlsx','csv'].includes(ext))throw new Error('فقط فایل‌های XLSX و CSV پذیرفته می‌شوند.');const mime=String(file.type||'').toLowerCase();if(ext==='xlsx'&&!XLSX_MIMES.has(mime))throw new Error('MIME Type فایل با XLSX سازگار نیست.');if(ext==='csv'&&!CSV_MIMES.has(mime))throw new Error('MIME Type فایل با CSV سازگار نیست.');const buf=await file.arrayBuffer();const sheets=ext==='xlsx'?await parseXlsx(buf):await parseCsv(buf);if(!sheets.some(sh=>sh.rows.length))throw new Error('هیچ داده‌ای در فایل پیدا نشد.');S.file=file;S.fileType=ext;S.sheets=sheets;S.sheetIndex=0;S.fileMeta={name:file.name,size:file.size,mime:file.type||'',sheets:sheets.length};S.revisionStart=Number(SERVER_REVISION||0)}catch(err){S.parseError=err?.message||'فایل خوانده نشد.'}finally{S.busy=false;render()}},
 clearFile(){reset();render()},nextSheet(){if(!S.file)return;S.step=2;render()},selectSheet(i){S.sheetIndex=Number(i);render()},
 prepareMapping(){const sh=S.sheets[S.sheetIndex];if(!sh||!sh.rows.length){S.parseError='Sheet انتخاب‌شده خالی است.';S.step=1;return render()}if(sh.truncated||sh.rows.length-1>MAX_ROWS){S.parseError=`تعداد ردیف‌ها بیشتر از حد مجاز ${MAX_ROWS} است.`;S.step=1;return render()}S.headers=(sh.rows[0]||[]).map(plain);S.rows=sh.rows.slice(1).filter(r=>r.some(v=>plain(v)!==''));if(!S.headers.some(Boolean)||!S.rows.length){S.parseError='Sheet انتخاب‌شده عنوان ستون یا ردیف داده ندارد.';S.step=1;return render()}S.mapping=autoMapping(S.headers);S.step=3;render()},
 back(n){S.payloadInfo=null;S.step=n;render()},map(k,v){S.mapping[k]=Number(v);S.analysis=null;S.payloadInfo=null},setOption(k,v){S[k]=v;S.analysis=null;S.payloadInfo=null},
 buildPreview(){const essential=['name','company','phone'].some(k=>Number(S.mapping[k])>=0);if(!essential)return toast?.('حداقل ستون نام مشتری، نام شرکت یا شماره تماس را تطبیق دهید.');S.analysis=analyze();S.payloadInfo=null;S.step=4;render()},
 mapStageAt(index,v){const raw=S.analysis?.unknownStages?.[Number(index)]??'';S.stageMappings[norm(raw)]=v;S.analysis=analyze();S.payloadInfo=null;render()},manualDuplicate(row,v){S.manualDuplicateActions[row]=v;S.analysis=analyze();S.payloadInfo=null;render()},confirmImport,downloadErrors,
 async reloadAndRetry(){try{SYNC_CONFLICT=false;await serverLoadState(true);S.revisionStart=Number(SERVER_REVISION||0);S.result=null;S.analysis=analyze();S.payloadInfo=null;S.step=4;render()}catch(e){toast?.('بارگذاری نسخه سرور انجام نشد')}},
 restart(){reset();S.step=1;render()},cancel(){reset();document.getElementById('modal')?.classList.remove('excel-import-open');closeModal()},
 __test:{parseXlsx,parseCsv,decodeCsv,normalizePhone,autoMapping,buildCustomer,analyze,buildDraft,requestPayloadInfo,csvSafeCell:v=>typeof window.csvSafeCell==='function'?window.csvSafeCell(v):v,state:S}
};
window.ExcelImportV042=api;
window.openCustomerImportModal=()=>api.open();
window.downloadCustomerImportTemplate=()=>{const a=document.createElement('a');a.href=TEMPLATE_PATH;a.download='customers-import-template.xlsx';document.body.appendChild(a);a.click();a.remove()};

const oldRender=window.renderCustomers;
window.renderCustomers=function(){oldRender?.();const actions=document.querySelector('#page-customers .v04-head-actions');if(window.HIPPO_AUTH?.permissions?.['excel_import.use']&&actions&&!actions.querySelector('[data-excel-import]')){const b=document.createElement('button');b.type='button';b.className='btn';b.dataset.excelImport='1';b.textContent='ورود مشتریان از Excel';b.onclick=()=>api.open();actions.prepend(b)}};
try{window.renderCustomers()}catch(e){console.error('V04.2 Excel import UI init failed',e)}
})();
