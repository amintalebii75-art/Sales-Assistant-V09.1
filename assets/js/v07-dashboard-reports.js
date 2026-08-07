/* V07.4.0 — Ariana dashboard and reports, phase 5.
 * Presentation and client-side aggregation only; no API, database, permission or business-rule change.
 */
(function(){
'use strict';
const A=()=>window.HIPPO_AUTH||{role:'',permissions:{}};
const can=(key)=>A().role==='manager'||!!A().permissions?.[key];
const escv=(value)=>typeof window.esc==='function'?window.esc(value):String(value??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
const nfmt=(value)=>typeof window.fmt==='function'?window.fmt(value):String(value??0);
const tISO=()=>typeof window.todayISO==='function'?window.todayISO():new Date().toISOString().slice(0,10);
const fdate=(value)=>value&&typeof window.faDate==='function'?window.faDate(value):(value||'—');
const fdatetime=(value)=>value&&typeof window.faDateTime==='function'?window.faDateTime(value):(value||'—');
const member=()=>typeof window.currentMember==='function'?window.currentMember():null;
const teamView=()=>A().role==='manager'||A().role==='manager_viewer'||can('reports.view_team');
const visibleCs=()=>typeof window.visibleCustomers==='function'?window.visibleCustomers():(window.state?.customers||[]);
const visibleInts=()=>typeof window.visibleInteractions==='function'?window.visibleInteractions():(window.state?.interactions||[]);
const stageInfo=(id)=>typeof window.stageObj==='function'?window.stageObj(id):((window.STAGES||[]).find(x=>x.id===id)||{label:id||'—',color:'blue'});
const memberLabel=(id)=>typeof window.memberName==='function'?window.memberName(id):id||'—';
const customer=(id)=>typeof window.customerById==='function'?window.customerById(id):(window.state?.customers||[]).find(x=>x.id===id);
const resultIds=(i)=>typeof window.interactionResultIds==='function'?window.interactionResultIds(i):(Array.isArray(i.resultIds)?i.resultIds:(i.resultId?[i.resultId]:[]));
const resultLabel=(id)=>{const r=typeof window.replyById==='function'?window.replyById(id):(window.state?.replyLibrary||[]).find(x=>x.id===id);return r?.label||id};
const stageList=()=>Array.isArray(window.STAGES)&&window.STAGES.length?window.STAGES:[
 {id:'new',label:'سرنخ جدید',color:'blue'},{id:'contacted',label:'ارتباط اولیه',color:'cyan'},{id:'qualified',label:'نیاز تأییدشده',color:'green'},{id:'sample',label:'نمونه/تست',color:'purple'},{id:'negotiation',label:'مذاکره',color:'orange'},{id:'trial',label:'سفارش آزمایشی',color:'teal'},{id:'won',label:'خرید/تکرار',color:'green'},{id:'paused',label:'متوقف/نامرتبط',color:'gray'}
];
function taskRows(scopeTeam){
 const rows=[];(window.state?.weeks||[]).forEach(w=>(w.tasks||[]).forEach(t=>rows.push({...t,_week:w.n})));
 if(scopeTeam)return rows;
 const id=member()?.id;return rows.filter(t=>!t.assignee||t.assignee===id);
}
function daysOld(value){if(!value)return 9999;const d=new Date(value);return Number.isFinite(d.getTime())?Math.floor((Date.now()-d.getTime())/86400000):9999}
function activeCustomer(c){return !['won','paused'].includes(c.stage)}
function permissionAction(key,html){return can(key)?html:''}
function dashboardPriorityItems(cs,tasks){
 const today=tISO();const rows=[];
 cs.filter(c=>activeCustomer(c)&&c.nextFollowUp&&String(c.nextFollowUp).slice(0,10)<today).sort((a,b)=>String(a.nextFollowUp).localeCompare(String(b.nextFollowUp))).slice(0,3).forEach(c=>rows.push({tone:'danger',badge:'عقب‌افتاده',title:`پیگیری ${c.name}`,meta:`موعد ${fdate(c.nextFollowUp)} · ${stageInfo(c.stage).label}`,button:'پرونده',action:`openCustomerDetail('${c.id}')`}));
 cs.filter(c=>activeCustomer(c)&&String(c.nextFollowUp||'').slice(0,10)===today).slice(0,2).forEach(c=>rows.push({tone:'warn',badge:'امروز',title:`تماس با ${c.name}`,meta:`${c.industry||'صنعت نامشخص'} · مسئول ${memberLabel(c.assignee)}`,button:'ثبت نتیجه',action:`prefillQuick('${c.id}')`}));
 tasks.filter(t=>t.status!=='done'&&(t.priority==='urgent'||t.fromManager)).slice(0,2).forEach(t=>rows.push({tone:'info',badge:'وظیفه',title:t.text||'وظیفه فروش',meta:`هفته ${nfmt(t._week)} · ${memberLabel(t.assignee)}`,button:'وظایف',action:`showPage('mytasks')`}));
 cs.filter(c=>activeCustomer(c)&&!(window.state?.interactions||[]).some(i=>i.customerId===c.id)).slice(0,1).forEach(c=>rows.push({tone:'neutral',badge:'بدون تماس',title:`اولین تماس با ${c.name}`,meta:'برای این مشتری هنوز مذاکره‌ای ثبت نشده است.',button:'شروع',action:`prefillQuick('${c.id}')`}));
 return rows.slice(0,5);
}
function metric(icon,label,value,sub,tone=''){
 return `<article class="v074-metric ${tone}"><span class="v074-metric-icon">${icon}</span><div><small>${escv(label)}</small><b>${nfmt(value)}</b><p>${escv(sub)}</p></div></article>`;
}
function funnelHtml(cs){
 const stages=stageList();const max=Math.max(1,...stages.map(s=>cs.filter(c=>c.stage===s.id).length));
 return `<div class="v074-funnel">${stages.map(s=>{const count=cs.filter(c=>c.stage===s.id).length;const pct=Math.max(4,Math.round(count/max*100));return `<button type="button" class="v074-funnel-row" onclick="state.settings.customerStage='${escv(s.id)}';save();showPage('customers')"><span class="v074-funnel-label"><b>${escv(s.label)}</b><small>${nfmt(count)} مشتری</small></span><span class="v074-funnel-track"><i class="tone-${escv(s.color)}" style="width:${pct}%"></i></span><strong>${nfmt(count)}</strong></button>`}).join('')}</div>`;
}
function weekBars(ints){
 const weeks=window.state?.weeks||[];const active=+window.state?.project?.activeWeek||1;const data=weeks.map(w=>({n:w.n,count:ints.filter(i=>+i.week===+w.n).length}));const max=Math.max(1,...data.map(x=>x.count));
 return `<div class="v074-week-chart">${data.map(x=>`<div class="v074-week-col ${+x.n===active?'active':''}"><span>${nfmt(x.count)}</span><div><i style="height:${Math.max(5,Math.round(x.count/max*100))}%"></i></div><small>هفته ${nfmt(x.n)}</small></div>`).join('')}</div>`;
}
window.renderDashboard=function(){
 const cs=visibleCs();const ints=visibleInts().slice().sort((a,b)=>String(b.date).localeCompare(String(a.date)));const isTeam=teamView();const tasks=taskRows(isTeam);const today=tISO();const activeWeek=+window.state?.project?.activeWeek||1;
 const overdue=cs.filter(c=>activeCustomer(c)&&c.nextFollowUp&&String(c.nextFollowUp).slice(0,10)<today);const dueToday=cs.filter(c=>activeCustomer(c)&&String(c.nextFollowUp||'').slice(0,10)===today);const weekInts=ints.filter(i=>+i.week===activeWeek);const purchases=ints.filter(i=>resultIds(i).includes('purchase'));const openTasks=tasks.filter(t=>t.status!=='done');const priority=dashboardPriorityItems(cs,tasks);const recent=ints.slice(0,7);
 const root=document.getElementById('page-dashboard');if(!root)return;
 root.innerHTML=`<div class="v074-hero"><div><span class="v04-kicker">${isTeam?'نمای تیم فروش':'فضای کاری شخصی'}</span><h1>مرکز فرمان فروش</h1><p>${new Date().toLocaleDateString('fa-IR-u-ca-persian',{weekday:'long',year:'numeric',month:'long',day:'numeric'})} · مهم‌ترین اقدام‌ها و وضعیت لحظه‌ای فروش</p></div><div class="v074-hero-actions">${permissionAction('customers.create','<button class="btn" onclick="openCustomerModal()">＋ مشتری جدید</button>')}${permissionAction('interactions.create','<button class="btn primary" onclick="showPage(\'activities\')">＋ ثبت مذاکره</button>')}<button class="btn soft" onclick="showPage('reports')">گزارش عملکرد</button></div></div>
 <div class="v074-metrics">${metric('م','مشتریان فعال',cs.filter(activeCustomer).length,'در مسیر فروش','primary')}${metric('!','پیگیری عقب‌افتاده',overdue.length,dueToday.length?`${nfmt(dueToday.length)} پیگیری برای امروز`:'پیگیری امروز ثبت نشده',overdue.length?'danger':'success')}${metric('گ','مذاکره هفته',weekInts.length,`هفته فعال ${nfmt(activeWeek)}`,'info')}${metric('✓','خرید ثبت‌شده',purchases.length,`${nfmt(openTasks.length)} وظیفه باز`,'success')}</div>
 <div class="v074-dashboard-grid"><section class="v074-card v074-priority-card"><div class="v074-card-head"><div><h2>اولویت‌های امروز</h2><p>مواردی که بیشترین اثر را روی فروش دارند</p></div><span class="badge ${overdue.length?'danger':'ok'}">${overdue.length?`${nfmt(overdue.length)} عقب‌افتاده`:'وضعیت مرتب'}</span></div><div class="v074-priority-list">${priority.length?priority.map((x,i)=>`<article class="v074-priority-item ${x.tone}"><span class="v074-priority-no">${nfmt(i+1)}</span><div><span class="v074-priority-tag">${escv(x.badge)}</span><h3>${escv(x.title)}</h3><p>${escv(x.meta)}</p></div><button class="btn small" onclick="${x.action}">${escv(x.button)}</button></article>`).join(''):`<div class="v074-empty"><b>کار فوری باقی نمانده است</b><p>پیگیری عقب‌افتاده یا وظیفه فوری برای امروز وجود ندارد.</p></div>`}</div></section>
 <aside class="v074-side-stack"><section class="v074-card"><div class="v074-card-head"><div><h2>نبض امروز</h2><p>خلاصه سریع وضعیت</p></div></div><div class="v074-status-list"><button onclick="showPage('followups')"><span>پیگیری امروز</span><b>${nfmt(dueToday.length)}</b></button><button onclick="showPage('followups')"><span>پیگیری عقب‌افتاده</span><b class="danger">${nfmt(overdue.length)}</b></button><button onclick="showPage('mytasks')"><span>وظایف باز</span><b>${nfmt(openTasks.length)}</b></button><button onclick="showPage('operations')"><span>سفارش‌ها</span><b>${nfmt(purchases.length)}</b></button></div></section><section class="v074-card v074-quick-card"><h2>دسترسی سریع</h2><div>${permissionAction('interactions.create','<button class="btn primary" onclick="showPage(\'activities\')">ثبت مذاکره</button>')}<button class="btn" onclick="showPage('customers')">مشتریان</button><button class="btn" onclick="showPage('pipeline')">قیف فروش</button><button class="btn" onclick="showPage('reports')">گزارش‌ها</button></div></section></aside></div>
 <div class="v074-insight-grid"><section class="v074-card"><div class="v074-card-head"><div><h2>نمای لحظه‌ای قیف فروش</h2><p>با کلیک روی هر مرحله، مشتریان همان مرحله باز می‌شوند</p></div><button class="btn small" onclick="showPage('pipeline')">قیف کامل</button></div>${funnelHtml(cs)}</section><section class="v074-card"><div class="v074-card-head"><div><h2>ریتم فعالیت هفتگی</h2><p>تعداد تماس و مذاکره در هفته‌ها</p></div><span class="badge blue">هفته ${nfmt(activeWeek)}</span></div>${weekBars(ints)}</section></div>
 <section class="v074-card v074-recent"><div class="v074-card-head"><div><h2>آخرین فعالیت‌ها</h2><p>جدیدترین مذاکرات ثبت‌شده در محدوده دسترسی شما</p></div><button class="btn small" onclick="showPage('reports')">مشاهده گزارش</button></div><div class="v074-recent-list">${recent.length?recent.map(i=>{const c=customer(i.customerId);const labels=resultIds(i).map(resultLabel).join('، ')||'فعالیت';return `<article><span class="v074-avatar">${escv((c?.name||'م').slice(0,1))}</span><div><h3>${escv(c?.name||'مشتری حذف‌شده')}</h3><p>${escv(labels)} · ${fdatetime(i.date)} · ${escv(memberLabel(i.memberId))}</p></div><button class="btn small" onclick="openCustomerDetail('${escv(i.customerId)}')">پرونده</button></article>`}).join(''):`<div class="v074-empty"><b>فعالیتی ثبت نشده است</b><p>پس از ثبت مذاکره، تاریخچه اینجا نمایش داده می‌شود.</p></div>`}</div></section>`;
};

window.V074ReportRange=window.V074ReportRange||'week';
window.V074ReportScope=window.V074ReportScope||(teamView()?'team':'personal');
window.v074SetReportRange=function(value){window.V074ReportRange=value;window.renderReportsV03()};
window.v074SetReportScope=function(value){if(value==='team'&&!teamView())return;window.V074ReportScope=value;window.renderReportsV03()};
function inRange(i,range){if(range==='all')return true;if(range==='week')return +i.week===+(window.state?.project?.activeWeek||1);const prefix=tISO().slice(0,7);return String(i.date||'').slice(0,7)===prefix}
function scopedData(scope,range){
 const mid=member()?.id;let cs=visibleCs().slice(),ints=visibleInts().slice();
 if(scope==='personal'){cs=cs.filter(c=>c.assignee===mid);ints=ints.filter(i=>i.memberId===mid)}
 ints=ints.filter(i=>inRange(i,range));return {cs,ints};
}
function reportRangeLabel(range){return range==='week'?`هفته ${nfmt(window.state?.project?.activeWeek||1)}`:range==='month'?'ماه جاری':'کل دوره'}
function reasonRows(ints){const map={};ints.forEach(i=>resultIds(i).forEach(id=>map[id]=(map[id]||0)+1));return Object.entries(map).sort((a,b)=>b[1]-a[1]).slice(0,8)}
function reportTeamRows(ints,cs){
 const members=(window.state?.team||[]).filter(m=>m.active!==false);return members.map(m=>{const mi=ints.filter(i=>i.memberId===m.id);const mc=cs.filter(c=>c.assignee===m.id);const wins=mi.filter(i=>resultIds(i).includes('purchase')).length;return {m,ints:mi.length,customers:mc.length,wins,over:mc.filter(c=>activeCustomer(c)&&c.nextFollowUp&&String(c.nextFollowUp).slice(0,10)<tISO()).length}}).sort((a,b)=>b.ints-a.ints);
}
window.renderReportsV03=function(){
 const range=window.V074ReportRange||'week';const scope=(window.V074ReportScope==='team'&&teamView())?'team':'personal';const {cs,ints}=scopedData(scope,range);const allScoped=scopedData(scope,'all');const overdue=cs.filter(c=>activeCustomer(c)&&c.nextFollowUp&&String(c.nextFollowUp).slice(0,10)<tISO()).length;const trials=ints.filter(i=>resultIds(i).includes('trial_order')).length;const wins=ints.filter(i=>resultIds(i).includes('purchase')).length;const newCustomers=cs.filter(c=>daysOld(c.createdAt)<=30).length;const conversion=ints.length?Math.round(wins/ints.length*100):0;const reasons=reasonRows(ints);const maxReason=Math.max(1,...reasons.map(x=>x[1]));const teamRows=scope==='team'?reportTeamRows(ints,cs):[];const recent=ints.slice().sort((a,b)=>String(b.date).localeCompare(String(a.date))).slice(0,8);
 const root=document.getElementById('page-reports');if(!root)return;
 root.innerHTML=`<div class="v074-hero v074-report-hero"><div><span class="v04-kicker">تحلیل فروش</span><h1>${scope==='team'?'گزارش عملکرد تیم':'گزارش عملکرد شخصی'}</h1><p>شاخص‌ها، قیف، موانع و فعالیت‌های ${reportRangeLabel(range)}</p></div><div class="v074-hero-actions"><button class="btn" onclick="exportInteractionsCSV()">خروجی CSV</button></div></div>
 <div class="v074-report-toolbar"><div class="v074-segment">${['week','month','all'].map(x=>`<button class="${range===x?'active':''}" onclick="v074SetReportRange('${x}')">${x==='week'?'هفته فعال':x==='month'?'ماه جاری':'کل دوره'}</button>`).join('')}</div>${teamView()?`<div class="v074-segment"><button class="${scope==='personal'?'active':''}" onclick="v074SetReportScope('personal')">شخصی</button><button class="${scope==='team'?'active':''}" onclick="v074SetReportScope('team')">تیم</button></div>`:''}</div>
 <div class="v074-metrics">${metric('گ','تماس و مذاکره',ints.length,reportRangeLabel(range),'primary')}${metric('م','مشتریان',cs.length,`${nfmt(newCustomers)} مشتری جدید`,'info')}${metric('!','پیگیری عقب‌افتاده',overdue,'نیازمند اقدام','danger')}${metric('%','نرخ تبدیل',conversion,`${nfmt(wins)} خرید · ${nfmt(trials)} آزمایشی`,'success')}</div>
 <div class="v074-report-grid"><section class="v074-card"><div class="v074-card-head"><div><h2>توزیع مشتریان در قیف</h2><p>وضعیت فعلی مشتریان ${scope==='team'?'تیم':'شما'}</p></div></div>${funnelHtml(cs)}</section><section class="v074-card"><div class="v074-card-head"><div><h2>موانع و نتایج پرتکرار</h2><p>براساس مذاکرات ثبت‌شده در بازه انتخابی</p></div></div><div class="v074-reason-list">${reasons.length?reasons.map(([id,count])=>`<div><span><b>${escv(resultLabel(id))}</b><small>${nfmt(count)} بار</small></span><i><em style="width:${Math.max(5,Math.round(count/maxReason*100))}%"></em></i></div>`).join(''):`<div class="v074-empty"><b>داده کافی وجود ندارد</b><p>با ثبت نتیجه مذاکرات، این تحلیل تکمیل می‌شود.</p></div>`}</div></section></div>
 ${scope==='team'?`<section class="v074-card"><div class="v074-card-head"><div><h2>عملکرد اعضای تیم</h2><p>مقایسه عملیاتی بدون نمایش اطلاعات خارج از محدوده دسترسی</p></div></div><div class="v074-team-table"><table><thead><tr><th>عضو تیم</th><th>مشتری</th><th>مذاکره</th><th>خرید</th><th>عقب‌افتاده</th></tr></thead><tbody>${teamRows.map(x=>`<tr><td><span class="v074-person"><i>${escv((x.m.name||'ک').slice(0,1))}</i><b>${escv(x.m.name||x.m.id)}</b></span></td><td>${nfmt(x.customers)}</td><td>${nfmt(x.ints)}</td><td>${nfmt(x.wins)}</td><td><span class="badge ${x.over?'danger':'ok'}">${nfmt(x.over)}</span></td></tr>`).join('')}</tbody></table></div></section>`:''}
 <div class="v074-report-grid v074-report-bottom"><section class="v074-card"><div class="v074-card-head"><div><h2>وضعیت پیگیری</h2><p>ریسک‌های اجرایی مشتریان</p></div></div><div class="v074-status-cards"><article><small>عقب‌افتاده</small><b class="danger">${nfmt(overdue)}</b></article><article><small>امروز</small><b>${nfmt(cs.filter(c=>String(c.nextFollowUp||'').slice(0,10)===tISO()).length)}</b></article><article><small>بدون پیگیری</small><b>${nfmt(cs.filter(c=>activeCustomer(c)&&!c.nextFollowUp).length)}</b></article><article><small>کل مذاکرات دوره</small><b>${nfmt(allScoped.ints.length)}</b></article></div></section><section class="v074-card"><div class="v074-card-head"><div><h2>آخرین فعالیت‌های بازه</h2><p>برای مشاهده جزئیات، پرونده مشتری را باز کنید</p></div></div><div class="v074-recent-list compact">${recent.length?recent.map(i=>{const c=customer(i.customerId);return `<article><span class="v074-avatar">${escv((c?.name||'م').slice(0,1))}</span><div><h3>${escv(c?.name||'مشتری')}</h3><p>${escv(resultIds(i).map(resultLabel).join('، ')||'تعامل')} · ${fdatetime(i.date)}</p></div><button class="btn small" onclick="openCustomerDetail('${escv(i.customerId)}')">پرونده</button></article>`}).join(''):`<div class="v074-empty"><b>فعالیتی در این بازه نیست</b><p>بازه دیگری انتخاب کنید یا مذاکره جدید ثبت کنید.</p></div>`}</div></section></div>`;
};

try{
 const p=window.currentPage||'dashboard';
 if(p==='dashboard')window.renderDashboard();
 if(p==='reports')window.renderReportsV03();
 document.querySelectorAll('[data-page="reports"] span:last-child').forEach(el=>el.textContent='گزارش عملکرد');
}catch(error){console.error('V07.4 dashboard/report render failed',error)}
})();
