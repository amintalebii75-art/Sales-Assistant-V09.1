/* V07.7.0 — Settings, backup/recovery and deployment-readiness UI.
 * Presentation and permission-aware controls only; existing API and persistence rules remain authoritative.
 */
(function(){
  'use strict';

  const uiKey=()=>`hippo_v077_ui_${Number(window.HIPPO_AUTH?.id||0)}`;
  const readUiPrefs=()=>{try{return JSON.parse(localStorage.getItem(uiKey())||'{}')||{}}catch(_){return {}}};
  const writeUiPrefs=p=>{try{localStorage.setItem(uiKey(),JSON.stringify(p))}catch(_){}};

  window.v077CanManageSettings=function(){
    return String(window.HIPPO_AUTH?.role||'')==='manager' && canPermission('settings.manage') && canPermission('state.save_full');
  };
  window.v077CanRestoreBackups=function(){
    return String(window.HIPPO_AUTH?.role||'')==='manager' && canPermission('backups.view') && canPermission('backups.restore') && canPermission('state.view_full') && canPermission('state.save_full');
  };
  window.v077RoleTitle=function(role){
    return ({manager:'مدیر',marketer:'بازاریاب',center_call:'مرکز تماس',manager_viewer:'ناظر مدیریتی'})[String(role||'')]||'کاربر';
  };
  window.v077ToggleCompact=function(checked){
    document.body.classList.toggle('compact-ui',!!checked);
    const p=readUiPrefs();p.compact=!!checked;writeUiPrefs(p);toast(checked?'نمای فشرده فعال شد':'نمای استاندارد فعال شد');
  };
  window.v077ToggleMotion=function(checked){
    document.body.classList.toggle('v077-reduce-motion',!!checked);
    const p=readUiPrefs();p.reduceMotion=!!checked;writeUiPrefs(p);toast(checked?'حرکت‌های رابط کاهش یافت':'حرکت‌های رابط فعال شد');
  };
  window.v077ResetInterface=function(){
    V03UI.confirmDialog({title:'بازنشانی تنظیمات رابط',message:'تنظیمات نمایشی این مرورگر به حالت استاندارد برگردد؟',confirmText:'بازنشانی',danger:true,onConfirm:()=>{
      document.body.classList.remove('compact-ui','v077-reduce-motion');writeUiPrefs({});renderSettings();toast('تنظیمات رابط بازنشانی شد');
    }});
  };
  window.v077ApplyInterfacePrefs=function(){
    const p=readUiPrefs();document.body.classList.toggle('compact-ui',!!p.compact);document.body.classList.toggle('v077-reduce-motion',!!p.reduceMotion);
  };

  function runtimeEnvironment(){
    const h=String(location.hostname||'');
    if(h==='localhost'||h==='127.0.0.1'||h==='::1')return {label:'محیط محلی XAMPP',tone:'warn',hint:'برای تست و پایلوت داخلی'};
    return {label:'سرور وب',tone:'ok',hint:location.protocol==='https:'?'ارتباط HTTPS فعال است':'HTTPS باید فعال شود'};
  }
  function syncState(){
    if(typeof SYNC_CONFLICT!=='undefined'&&SYNC_CONFLICT)return {label:'تداخل Revision',tone:'danger',hint:'نسخه سرور را بررسی کنید',icon:'!'};
    if(typeof SERVER_STATE_LOADED!=='undefined'&&SERVER_STATE_LOADED)return {label:'متصل و همگام',tone:'ok',hint:'ذخیره روی سرور فعال است',icon:'✓'};
    return {label:'در حال بررسی',tone:'warn',hint:'وضعیت ارتباط هنوز قطعی نیست',icon:'…'};
  }
  function backupPermission(){
    return String(window.HIPPO_AUTH?.role||'')==='manager'&&canPermission('backups.view')&&canPermission('state.view_full');
  }
  function projectField(label,html,full=false){return `<div class="field ${full?'full':''}"><label>${label}</label>${html}</div>`}
  function deploymentCheck(icon,title,text,state,tone){return `<article class="v077-check ${tone||''}"><span class="v077-check-icon">${icon}</span><div><h3>${title}</h3><p>${text}</p></div><span class="v077-state ${tone||''}">${state}</span></article>`}
  function actionButton(cls,icon,title,desc,onclick){return `<button class="v077-action ${cls||''}" type="button" onclick="${onclick}"><i>${icon}</i><b>${title}</b><small>${desc}</small></button>`}

  window.renderSettings=function(){
    const page=document.getElementById('page-settings');if(!page)return;
    const role=String(window.HIPPO_AUTH?.role||'');
    const manager=v077CanManageSettings();
    const restore=v077CanRestoreBackups();
    const env=runtimeEnvironment(),sync=syncState(),prefs=readUiPrefs();
    const revision=typeof SERVER_REVISION!=='undefined'?Number(SERVER_REVISION||0):0;
    const localRecovery=(()=>{try{return !!localStorage.getItem(typeof RECOVERY_KEY!=='undefined'?RECOVERY_KEY:'')}catch(_){return false}})();
    const projectName=esc(state.project?.name||'دستیار فروش');
    const startDate=state.project?.startDate||'';
    const lastSaved=esc(state.project?.lastSaved||'هنوز ذخیره نشده');
    const host=esc(location.hostname||'—');
    const protocol=esc((location.protocol||'').replace(':','').toUpperCase()||'—');
    const projectFields=manager
      ? `${projectField('نام پروژه یا شرکت',`<input value="${projectName}" onchange="state.project.name=this.value.trim()||'دستیار فروش';save();renderDashboard()">`,true)}${projectField('تاریخ شروع',jdatePickerHtml('projStartV077',startDate,true,"state.project.startDate=document.getElementById('projStartV077').value;save()"))}${projectField('هفته فعال',`<select onchange="setActiveWeek(+this.value)">${state.weeks.map(w=>`<option value="${w.n}" ${w.n===state.project.activeWeek?'selected':''}>هفته ${w.n}</option>`).join('')}</select>`)}`
      : `${projectField('نام پروژه یا شرکت',`<div class="v077-readonly">${projectName}</div>`,true)}${projectField('تاریخ شروع',`<div class="v077-readonly">${startDate?esc(faDate(startDate)):'ثبت نشده'}</div>`)}${projectField('هفته فعال',`<div class="v077-readonly">هفته ${fmt(state.project.activeWeek||1)}</div>`)}`;

    const backupActions=[
      actionButton('primary','↓','دانلود Recovery','نسخه سازگار با سطح دسترسی همین حساب','exportData()'),
      actionButton('','↻','نسخه مرورگر','بازیابی اضطراری از کش همین مرورگر','downloadLocalRecovery()'),
      `<label class="v077-action" for="importFileV077"><i>↑</i><b>ورود Recovery</b><small>فقط فایل متعلق به همین حساب و Scope</small></label><input id="importFileV077" type="file" accept="application/json" hidden onchange="importData(event)">`,
      actionButton('','م','CSV مشتریان','خروجی قابل استفاده در Excel','exportCustomersCSV()'),
      actionButton('','گ','CSV مذاکرات','سوابق مذاکره قابل تحلیل','exportInteractionsCSV()'),
      actionButton('','AI','خروجی تحلیل','فایل ساختاریافته برای تحلیل هوش مصنوعی','exportForAI()')
    ];
    if(backupPermission())backupActions.unshift(actionButton('warn','⟲','نسخه‌های سرور','مشاهده ۲۰ نسخه اخیر و بازیابی کنترل‌شده','openServerBackups()'));

    page.innerHTML=`
      <div class="v077-settings-hero"><div><span class="v04-kicker">مدیریت سیستم</span><h1>تنظیمات، پشتیبان و آمادگی استقرار</h1><p>کنترل تنظیمات سازمان، بازیابی امن داده و چک‌لیست انتقال از پایلوت به اجرای واقعی.</p></div><div class="v077-settings-actions"><button class="btn" onclick="saveNow()">ذخیره همین حالا</button>${backupPermission()?'<button class="btn primary" onclick="openServerBackups()">نسخه‌های سرور</button>':''}</div></div>
      <div class="v077-status-strip">
        <article class="v077-status-card ${sync.tone}"><span class="v077-status-icon">${sync.icon}</span><div><small>وضعیت داده</small><b>${sync.label}</b><span>${sync.hint}</span></div></article>
        <article class="v077-status-card"><span class="v077-status-icon">R</span><div><small>Revision سرور</small><b>${fmt(revision)}</b><span>کنترل تداخل هم‌زمان فعال</span></div></article>
        <article class="v077-status-card ${env.tone}"><span class="v077-status-icon">⌘</span><div><small>محیط اجرا</small><b>${env.label}</b><span>${env.hint}</span></div></article>
        <article class="v077-status-card ${localRecovery?'ok':'warn'}"><span class="v077-status-icon">↺</span><div><small>Recovery مرورگر</small><b>${localRecovery?'موجود':'هنوز ساخته نشده'}</b><span>آخرین ذخیره: ${lastSaved}</span></div></article>
      </div>

      <div class="v077-grid">
        <section class="v077-card"><div class="v077-card-head"><div><h2>تنظیمات پروژه</h2><p>تنها مدیر دارای مجوز می‌تواند تنظیمات سازمان را تغییر دهد.</p></div><span class="badge ${manager?'ok':'warn'}">${manager?'قابل ویرایش':'فقط مشاهده'}</span></div><div class="v077-card-body"><div class="v077-form-grid">${projectFields}</div>${manager?'<div class="v077-role-note"><b>✓</b><span>تغییرات این بخش با Revision روی سرور ذخیره می‌شود و در Backupهای سازمان قرار می‌گیرد.</span></div>':'<div class="v077-role-note warn"><b>!</b><span>این حساب تنظیمات سازمان را فقط مشاهده می‌کند. مجوز ویرایش برای مدیر سیستم محفوظ است.</span></div>'}</div></section>
        <section class="v077-card"><div class="v077-card-head"><div><h2>محیط و حساب فعال</h2><p>اطلاعات تشخیصی بدون نمایش رمز یا داده حساس.</p></div></div><div class="v077-card-body"><div class="v077-runtime-list"><div class="v077-runtime-row"><span>حساب فعال</span><b>${esc(window.HIPPO_AUTH?.display_name||'—')}</b></div><div class="v077-runtime-row"><span>نقش</span><b>${v077RoleTitle(role)}</b></div><div class="v077-runtime-row"><span>میزبان</span><b>${host}</b></div><div class="v077-runtime-row"><span>پروتکل</span><b>${protocol}</b></div><div class="v077-runtime-row"><span>نسخه رابط</span><b>V07.7.0</b></div></div></div></section>
      </div>

      <div class="v077-section-title"><div><h2>پشتیبان و انتقال داده</h2><p>Recovery هر حساب Scope دارد؛ بازیابی کامل سازمان فقط با مجوز مدیر انجام می‌شود.</p></div></div>
      <section class="v077-card"><div class="v077-card-head"><div><h2>ابزارهای خروجی و بازیابی</h2><p>قبل از تغییر مهم یا استقرار، یک Recovery و یک Backup دیتابیس نگه دارید.</p></div><span class="badge blue">Revision ${fmt(revision)}</span></div><div class="v077-card-body"><div class="v077-action-grid">${backupActions.join('')}</div><div class="v077-safety-flow"><article><span>۱</span><b>انتخاب نسخه</b><p>نسخه سرور یا فایل Recovery معتبر انتخاب می‌شود.</p></article><article><span>۲</span><b>کنترل دسترسی و Revision</b><p>CSRF، نقش، Scope و تداخل هم‌زمان بررسی می‌شود.</p></article><article><span>۳</span><b>نسخه قبل از Restore</b><p>پیش از بازیابی، وضعیت فعلی خودکار ذخیره می‌شود.</p></article></div><div id="v077BackupSummary" class="v077-backup-summary"><div class="v077-backup-empty">${backupPermission()?'در حال دریافت خلاصه نسخه‌های سرور…':'فهرست نسخه‌های کامل سرور فقط برای مدیر مجاز نمایش داده می‌شود.'}</div></div></div></section>

      <div class="v077-section-title"><div><h2>چک‌لیست استقرار واقعی</h2><p>موارد «دستی» باید هنگام انتقال به هاست و قبل از ورود تیم تأیید شوند.</p></div></div>
      <section class="v077-card"><div class="v077-card-body"><div class="v077-checklist">
        ${deploymentCheck('✓','فایل‌های حساس محافظت شده‌اند','قوانین Apache دسترسی مستقیم به config، SQL، گزارش و شواهد تست را می‌بندد.','آماده در بسته','')}
        ${deploymentCheck('✓','فایل ساخت مدیر داخل Release نیست','در نسخه اجرایی V07.7 فایل create_admin.php توزیع نشده است.','آماده در بسته','')}
        ${deploymentCheck('i','تنظیم config.php سرور','اطلاعات دیتابیس هاست باید دستی و خارج از فایل‌های عمومی وارد شود.','هنگام استقرار','manual')}
        ${deploymentCheck('↗','فعال‌سازی HTTPS','روی دامنه نهایی گواهی SSL و انتقال اجباری HTTP به HTTPS بررسی شود.','روی سرور','server')}
        ${deploymentCheck('DB','Backup کامل دیتابیس','پیش از استقرار و قبل از هر Migration، خروجی SQL مستقل نگهداری شود.','دستی','manual')}
        ${deploymentCheck('4','Smoke Test چهار نقش','مدیر، بازاریاب، مرکز تماس و ناظر مدیریتی روی سرور نهایی تست شوند.','دستی','manual')}
        ${deploymentCheck('PHP','خاموش‌کردن نمایش خطای PHP','display_errors در Production خاموش و ثبت خطا در فایل امن فعال شود.','روی سرور','server')}
        ${deploymentCheck('↺','برنامه Backup دوره‌ای','برای دیتابیس و فایل‌ها زمان‌بندی روزانه یا هفتگی تعریف شود.','دستی','manual')}
      </div><div class="v077-deploy-note"><i>!</i><div><b>وضعیت نسخه: آماده پایلوت واقعی، نه انتشار بدون کنترل</b><p>نسخه V07.7 برای نصب آزمایشی و استفاده محدود تیم آماده است. انتشار عمومی فقط بعد از Backup مستقل، HTTPS، تست چهار نقش و بررسی Logهای سرور انجام می‌شود.</p></div></div></div></section>

      <div class="v077-grid" style="margin-top:15px">
        <section class="v077-card"><div class="v077-card-head"><div><h2>تنظیمات رابط همین مرورگر</h2><p>این گزینه‌ها داده سازمان را تغییر نمی‌دهند.</p></div></div><div class="v077-card-body"><div class="v077-interface-row"><div><b>نمای فشرده</b><p>تعداد ردیف بیشتری در جدول‌ها و فهرست‌ها نمایش داده شود.</p></div><label class="ui-switch"><input type="checkbox" ${prefs.compact?'checked':''} onchange="v077ToggleCompact(this.checked)"><span class="ui-switch-track"></span><span>${prefs.compact?'فعال':'غیرفعال'}</span></label></div><div class="v077-interface-row"><div><b>کاهش حرکت رابط</b><p>انیمیشن‌ها و Transitionها برای دستگاه‌های ضعیف یا ترجیح کاربر کاهش یابد.</p></div><label class="ui-switch"><input type="checkbox" ${prefs.reduceMotion?'checked':''} onchange="v077ToggleMotion(this.checked)"><span class="ui-switch-track"></span><span>${prefs.reduceMotion?'فعال':'غیرفعال'}</span></label></div><div style="margin-top:13px"><button class="btn" onclick="v077ResetInterface()">بازنشانی تنظیمات رابط</button></div></div></section>
        <section class="v077-card ${manager?'v077-danger-zone':'v077-disabled'}"><div class="v077-card-head"><div><h2>ابزارهای پایلوت و ناحیه حساس</h2><p>${manager?'فقط مدیر؛ استفاده با تأیید و Backup قبلی.':'برای این حساب غیرفعال است.'}</p></div></div><div class="v077-card-body"><div class="v077-action-grid">${manager?actionButton('','＋','داده نمونه','افزودن مشتریان آزمایشی بدون حذف داده موجود','seedDemoCustomers()')+actionButton('danger','×','بازنشانی داده','جایگزینی Scope همین حساب با نسخه اولیه','resetData()'):'<div class="v077-role-note warn full"><b>قفل</b><span>ابزارهای تغییر داده آزمایشی فقط برای مدیر دارای مجوز تنظیمات فعال می‌شوند.</span></div>'}</div></div></section>
      </div>`;

    if(backupPermission())setTimeout(v077LoadBackupSummary,0);
  };

  window.v077LoadBackupSummary=async function(){
    const box=document.getElementById('v077BackupSummary');if(!box||!backupPermission())return;
    try{
      const r=await fetch('api.php?action=backups',{credentials:'same-origin',cache:'no-store'}),j=await r.json();
      if(!r.ok||!j.ok)throw new Error('backup_list_failed');
      const list=(j.backups||[]).slice(0,3);
      if(!list.length){box.innerHTML='<div class="v077-backup-empty">هنوز نسخه‌ای روی سرور ثبت نشده است. اولین ذخیره، Backup اولیه را ایجاد می‌کند.</div>';return}
      box.innerHTML=`<div class="v077-card-head" style="padding:0 0 10px;border:0"><div><h2>سه نسخه اخیر سرور</h2><p>برای مشاهده همه نسخه‌ها از دکمه «نسخه‌های سرور» استفاده کنید.</p></div><span class="badge ok">${fmt((j.backups||[]).length)} نسخه</span></div>${list.map((b,i)=>`<div class="v077-backup-row"><span class="v077-backup-no">${fmt(i+1)}</span><div><b>Revision ${fmt(b.revision)} · ${esc(backupOperationLabel(b.operation))}</b><small>${esc(faDateTime(b.saved_at))} · ${esc(b.saved_by||'نامشخص')}</small></div>${v077CanRestoreBackups()?`<button class="btn small warn" onclick="restoreServerBackup(${Number(b.id)})">بازیابی</button>`:''}</div>`).join('')}`;
    }catch(_){box.innerHTML='<div class="v077-backup-empty">خلاصه نسخه‌های سرور دریافت نشد. اتصال یا Permission را بررسی کنید.</div>'}
  };

  v077ApplyInterfacePrefs();
})();
