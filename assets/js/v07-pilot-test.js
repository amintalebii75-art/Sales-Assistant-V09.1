
(() => {
  'use strict';
  const auth = window.HIPPO_PILOT_AUTH || {};
  const version = 'V09.1.0';
  const roleOrder = ['manager','marketer','center_call','manager_viewer'];
  const roleLabels = {manager:'مدیر',marketer:'بازاریاب',center_call:'مرکز تماس',manager_viewer:'ناظر مدیریتی'};
  const tests = {
    manager:[
      ['m-login','ورود و داشبورد مدیر','ورود موفق، نمایش مرکز فرمان، گزارش تیمی و نبود خطای JavaScript.'],
      ['m-customer','ساخت و ویرایش مشتری','ساخت مشتری آزمایشی، ویرایش پرونده و مشاهده آن در کارت، جدول و قیف.'],
      ['m-access','تخصیص Customer Access','دادن View/Call/Edit به کاربران و کنترل اثر واقعی آن پس از خروج و ورود.'],
      ['m-results','مدیریت گزینه‌های مذاکره','افزودن گزینه با دکمه + و مشاهده آن در حساب‌های تیم بدون حذف گزینه‌های پیش‌فرض.'],
      ['m-plan','برنامه ماهانه','ساخت/انتشار برنامه، هفته‌ها، وظیفه و Assignment و مشاهده درصد پیشرفت.'],
      ['m-users','کاربران و Permissionها','بازشدن صفحه کاربران، تغییر مجوز آزمایشی و ثبت Audit بدون دسترسی نقش‌های دیگر.'],
      ['m-backup','Backup فقط‌خواندنی','بازشدن فهرست Backupها. Restore در این مرحله انجام نشود مگر روی داده آزمایشی.'],
    ],
    marketer:[
      ['mk-login','ورود و داده‌های محدود','فقط مشتریان و برنامه‌های مجاز دیده شوند؛ داده سایر اعضا نمایش داده نشود.'],
      ['mk-negotiation','ثبت مذاکره چندنتیجه‌ای','انتخاب چند نتیجه، نمایش واضح تیک و ثبت موفق در فعالیت‌های اخیر.'],
      ['mk-followup','پیگیری شمسی','ثبت تاریخ پیگیری شمسی، خروج و ورود مجدد و ماندگاری تاریخ.'],
      ['mk-task','وظایف و پیشرفت','مشاهده Assignment خود و تغییر پیشرفت بدون امکان ویرایش Assignment دیگران.'],
      ['mk-deny','ممانعت از مدیریت کاربران','بازکردن مستقیم users.php باید با دسترسی غیرمجاز رد شود.'],
    ],
    center_call:[
      ['cc-login','ورود و مشتریان دارای Call','مشتری دارای سطح Call قابل انتخاب باشد و مشتری خارج از Scope دیده نشود.'],
      ['cc-multi','انتخاب چند نتیجه','هر تعداد نتیجه قابل انتخاب باشد و انتخاب‌ها با رنگ، تیک و شمارنده دیده شوند.'],
      ['cc-handoff','ارجاع به بازاریاب','پس از ثبت، مذاکره و پیگیری برای بازاریاب مسئول همان مشتری قابل مشاهده باشد.'],
      ['cc-boundary','محدودیت فیلدهای حساس','ویرایش مالک، مرحله، پرداخت، سفارش قطعی و اطلاعات مالی ممکن نباشد.'],
      ['cc-no-create','ممانعت از ساخت مشتری','مرکز تماس نتواند مشتری جدید بسازد یا صفحه کاربران را باز کند.'],
    ],
    manager_viewer:[
      ['mv-login','ورود به خلاصه مدیریتی','حساب ناظر مستقیماً به صفحه Summary-only هدایت شود.'],
      ['mv-summary','نمایش گزارش تجمیعی','آمار قیف و عملکرد تیم بدون شماره تماس، آدرس و یادداشت خصوصی دیده شود.'],
      ['mv-readonly','عدم دسترسی نوشتن','ساخت/ویرایش مشتری، برنامه، کاربر، Backup و Full-State در دسترس نباشد.'],
      ['mv-direct','ممانعت مسیر مستقیم','بازکردن مستقیم index.php/users.php/api.php?action=state نباید Full-State بدهد.'],
    ],
  };
  let selectedRole = auth.role && tests[auth.role] ? auth.role : 'manager';
  const key = role => `hippoPilot:${version}:${role}`;
  const load = role => { try { return JSON.parse(localStorage.getItem(key(role)) || '{}'); } catch (_) { return {}; } };
  const save = (role, value) => { try { localStorage.setItem(key(role), JSON.stringify(value)); } catch (_) {} };
  const fa = n => new Intl.NumberFormat('fa-IR').format(n);
  const stateLabel = {not_run:'اجرا نشده',pass:'PASS',fail:'FAIL',blocked:'BLOCKED'};

  function allProgress() {
    let total=0, done=0, pass=0, fail=0, blocked=0;
    roleOrder.forEach(role => {
      const data=load(role);
      tests[role].forEach(([id])=>{ total++; const s=data[id]?.state || 'not_run'; if(s!=='not_run') done++; if(s==='pass') pass++; if(s==='fail') fail++; if(s==='blocked') blocked++; });
    });
    return {total,done,pass,fail,blocked,percent:total?Math.round(done*100/total):0};
  }

  function renderTabs(){
    const el=document.getElementById('roleTabs');
    el.innerHTML=roleOrder.map(role=>{
      const data=load(role); const total=tests[role].length; const pass=tests[role].filter(([id])=>data[id]?.state==='pass').length;
      return `<button class="v078-role-tab ${role===selectedRole?'active':''} ${role===auth.role?'current':''}" data-role="${role}"><b>${roleLabels[role]}</b><small>${fa(pass)} از ${fa(total)} PASS</small></button>`;
    }).join('');
    el.querySelectorAll('button').forEach(btn=>btn.addEventListener('click',()=>{selectedRole=btn.dataset.role;renderManual();renderTabs();}));
  }

  function renderManual(){
    const el=document.getElementById('manualChecklist'), data=load(selectedRole);
    el.innerHTML=`<div class="v078-manual-list">${tests[selectedRole].map(([id,title,desc])=>{
      const item=data[id]||{state:'not_run',note:''};
      return `<article class="v078-test-row ${item.state}"><div><h3>${title}</h3><p>${desc}</p></div><div class="v078-state-buttons">${['not_run','pass','fail','blocked'].map(st=>`<button type="button" data-id="${id}" data-state="${st}" class="${item.state===st?'active':''}">${stateLabel[st]}</button>`).join('')}</div><textarea class="v078-note" data-note-id="${id}" placeholder="یادداشت، خطا یا مدرک تست...">${String(item.note||'').replace(/[&<>]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]))}</textarea></article>`;
    }).join('')}</div>`;
    el.querySelectorAll('[data-state]').forEach(btn=>btn.addEventListener('click',()=>{const d=load(selectedRole),id=btn.dataset.id;d[id]=d[id]||{};d[id].state=btn.dataset.state;d[id].updatedAt=new Date().toISOString();save(selectedRole,d);renderManual();renderTabs();updateOverall();}));
    el.querySelectorAll('[data-note-id]').forEach(tx=>tx.addEventListener('input',()=>{const d=load(selectedRole),id=tx.dataset.noteId;d[id]=d[id]||{state:'not_run'};d[id].note=tx.value;d[id].updatedAt=new Date().toISOString();save(selectedRole,d);}));
  }

  function updateOverall(){
    const p=allProgress();
    document.getElementById('overallPercent').textContent=fa(p.percent)+'٪';
    document.getElementById('overallBar').style.width=p.percent+'%';
    const ready=p.done===p.total && p.fail===0 && p.blocked===0;
    const badge=document.getElementById('releaseBadge'),msg=document.getElementById('releaseMessage');
    badge.className='v078-release-badge '+(ready?'ready':'hold'); badge.textContent=ready?'READY':'HOLD';
    msg.textContent=ready?'تمام تست‌های چهار نقش PASS شده‌اند؛ نسخه می‌تواند وارد نصب محدود هاست پایلوت شود.':`پیشرفت ${fa(p.done)} از ${fa(p.total)}؛ FAIL: ${fa(p.fail)}، BLOCKED: ${fa(p.blocked)}. تا رفع موارد، نسخه روی XAMPP بماند.`;
  }

  function browserRow(label,ok,detail,expected=false){
    const cls=ok?'pass':(expected?'warn':'fail');
    return `<div class="v078-auto-row ${cls}"><span class="v078-auto-icon">${ok?'✓':(expected?'!':'×')}</span><div><b>${label}</b><small>${detail}</small></div><em>${ok?'PASS':(expected?'EXPECTED':'FAIL')}</em></div>`;
  }

  async function browserChecks(){
    const rows=[];
    let storage=false; try{const k='__hippo_pilot_test';localStorage.setItem(k,'1');storage=localStorage.getItem(k)==='1';localStorage.removeItem(k);}catch(_){}
    rows.push(browserRow('LocalStorage قابل استفاده است',storage,storage?'خواندن و نوشتن موفق':'مرورگر ذخیره محلی را مسدود کرده'));
    rows.push(browserRow('IndexedDB در مرورگر موجود است',!!window.indexedDB,window.indexedDB?'API موجود':'در دسترس نیست'));
    rows.push(browserRow('صفحه Responsive بارگذاری شده',document.documentElement.clientWidth>0,`${document.documentElement.clientWidth} × ${document.documentElement.clientHeight}`));
    try{
      const res=await fetch('api.php?action=state',{credentials:'same-origin',cache:'no-store'});
      const data=await res.json().catch(()=>({}));
      const expected=auth.role==='manager_viewer' && res.status===403 && data.error==='summary_only';
      rows.push(browserRow('Endpoint وضعیت مطابق نقش پاسخ می‌دهد',res.ok||expected,res.ok?`HTTP ${res.status} · scope ${data.scope||'نامشخص'}`:`HTTP ${res.status} · ${data.error||'خطای ناشناخته'}`,expected));
    }catch(e){rows.push(browserRow('اتصال مرورگر به API',false,String(e.message||e)));}
    const broken=[];
    for(const path of ['index.php','planning.php','users.php','pilot_test.php']){
      if(path==='users.php' && !auth.permissions?.['users.manage']) continue;
      try{const r=await fetch(path,{method:'HEAD',credentials:'same-origin',cache:'no-store'}); if(!r.ok) broken.push(`${path}: ${r.status}`);}catch(e){broken.push(`${path}: network`);}
    }
    rows.push(browserRow('مسیرهای مجاز اصلی پاسخ می‌دهند',broken.length===0,broken.length?broken.join('، '):'مسیرهای مرتبط با نقش فعال در دسترس‌اند'));
    const el=document.getElementById('browserChecks'); el.innerHTML=rows.join('');
    const failures=(el.querySelectorAll('.fail').length); const summary=document.getElementById('browserSummary');
    summary.className='v078-status '+(failures?'fail':'pass'); summary.textContent=failures?`${fa(failures)} خطا`:'همه بررسی‌ها موفق';
  }

  function downloadReport(){
    const payload={version,generatedAt:new Date().toISOString(),generatedBy:{id:auth.id,display_name:auth.display_name,role:auth.role},serverChecks:window.HIPPO_PILOT_SERVER_CHECKS||[],browser:{userAgent:navigator.userAgent,viewport:[innerWidth,innerHeight],online:navigator.onLine},roles:{}};
    roleOrder.forEach(role=>{payload.roles[role]={label:roleLabels[role],results:load(role)};});
    const blob=new Blob([JSON.stringify(payload,null,2)],{type:'application/json;charset=utf-8'}); const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=`Hippo-Pilot-Test-${version}-${new Date().toISOString().slice(0,10)}.json`;a.click();setTimeout(()=>URL.revokeObjectURL(a.href),1000);
  }
  document.getElementById('downloadReportBtn').addEventListener('click',downloadReport);
  document.getElementById('resetCurrentBtn').addEventListener('click',()=>{if(confirm(`نتایج نقش ${roleLabels[selectedRole]} پاک شود؟`)){localStorage.removeItem(key(selectedRole));renderTabs();renderManual();updateOverall();}});
  renderTabs(); renderManual(); updateOverall(); browserChecks();
})();
