<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';
$hippoUser = hippo_require_login_page();
$hippoRole = hippo_role_alias((string)$hippoUser['role']);
if ($hippoRole === 'manager_viewer') {
    header('Location: manager.php');
    exit;
}
if (!in_array($hippoRole, ['manager','marketer','center_call'], true)) {
    http_response_code(403);
    exit('دسترسی غیرمجاز');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>دستیار فروش | فضای کار روزانه</title>
<link rel="stylesheet" href="assets/css/compat-index.css">
<link rel="stylesheet" href="assets/css/tokens.css">
<link rel="stylesheet" href="assets/css/base.css">
<link rel="stylesheet" href="assets/css/components.css?v=0910">
<link rel="stylesheet" href="assets/css/layout.css">
<link rel="stylesheet" href="assets/css/pages.css">
<link rel="stylesheet" href="assets/css/responsive.css">
<link rel="stylesheet" href="assets/css/v04-product.css">
<link rel="stylesheet" href="assets/css/v04-1-final.css">
<link rel="stylesheet" href="assets/css/excel-import.css">
<link rel="stylesheet" href="assets/css/v05-rbac.css">
<link rel="stylesheet" href="assets/css/v07-ariana.css?v=0910">
<link rel="stylesheet" href="assets/css/v07-dashboard-reports.css?v=0910">
<link rel="stylesheet" href="assets/css/v07-settings-deployment.css?v=0910">
<link rel="stylesheet" href="assets/css/v09-1-configurable-forms.css?v=0910">
<?php require __DIR__ . '/pwa_head.php'; ?></head>
<body class="v07-ui">
<div class="app">
<header class="topbar"><div class="topbar-inner">
  <button class="menu-toggle" type="button" onclick="V03Nav.openDrawer()" aria-label="بازکردن منو">☰</button>
  <div class="logo v07-logo" aria-hidden="true"><span>ف</span></div>
  <div class="brand-title"><div class="v07-brand-line"><strong>دستیار فروش</strong><span class="v07-version-badge">پایلوت V09.1</span></div><span>مدیریت مشتری، مذاکره و برنامه فروش</span></div>
  <div class="top-actions">
    <span id="syncStatus" class="sync-status local" title="وضعیت ارتباط با سرور">در حال بررسی</span>
    <button class="btn soft hide-mobile" onclick="showPage('activities')">ثبت مذاکره</button>
    <button class="btn hide-tablet" onclick="openCustomerModal()">مشتری جدید</button>
    <button class="btn primary" onclick="saveNow()">ذخیره</button>
    <div class="ui-dropdown" id="userMenu"><button class="btn" type="button" onclick="V03UI.toggleDropdown('userMenu')" data-tooltip="حساب کاربری"><?= htmlspecialchars($hippoUser['display_name'], ENT_QUOTES, 'UTF-8') ?> ▾</button><div class="ui-dropdown-menu"><span style="padding:9px;font-size:12px;color:var(--color-text-muted)"><?= htmlspecialchars($hippoUser['role_label'], ENT_QUOTES, 'UTF-8') ?></span><a href="logout.php">خروج از حساب</a></div></div>
  </div>
</div></header>
<div id="syncAlert" class="sync-alert"><div class="sync-alert-inner">
  <div><b id="syncAlertTitle">همگام‌سازی انجام نشد</b><span id="syncAlertText">نسخه مرورگر محفوظ است.</span></div>
  <div class="sync-alert-actions"><button class="btn small" onclick="downloadLocalRecovery()">دانلود نسخه مرورگر</button><button id="syncRetryBtn" class="btn small" onclick="retryServerSave()">تلاش دوباره</button><button id="syncReloadBtn" class="btn small danger" onclick="resolveConflictReload()" style="display:none">بارگذاری نسخه سرور</button></div>
</div></div>
<div class="layout">
<aside class="sidebar" aria-label="منوی اصلی">
  <div class="sidebar-header"><div class="v07-sidebar-brand"><span class="v07-sidebar-mark">ف</span><span><b>دستیار فروش</b><small>فضای کار تیم فروش</small></span></div><button class="sidebar-collapse" type="button" onclick="V03Nav.toggleSidebar()" aria-label="جمع‌کردن منو">‹</button></div>
  <div class="nav-group">کار روزانه</div>
  <button class="nav-btn active" data-page="dashboard" onclick="showPage('dashboard',this)"><span class="nav-icon">⌂</span><span>مرکز فرمان</span></button>
  <button class="nav-btn" data-page="customers" onclick="showPage('customers',this)"><span class="nav-icon">م</span><span>مشتریان</span></button>
  <button class="nav-btn" data-page="activities" onclick="showPage('activities',this)"><span class="nav-icon">＋</span><span>ثبت مذاکره</span></button>
  <button class="nav-btn" data-page="followups" onclick="showPage('followups',this)"><span class="nav-icon">پ</span><span>پیگیری‌ها</span><span id="navFollowupBadge" class="nav-badge">۰</span></button>
  <button class="nav-btn" data-page="mytasks" onclick="showPage('mytasks',this)"><span class="nav-icon">ک</span><span>وظایف</span><span id="navTaskBadge" class="nav-badge">۰</span></button>
  <?php if (hippo_can($hippoUser, 'plans.view_own') || hippo_can($hippoUser, 'plans.view_team')): ?><a class="nav-btn" href="planning.php"><span class="nav-icon">۴</span><span>برنامه ماهانه</span></a><?php endif; ?>
  <?php if ($hippoRole !== 'center_call'): ?>
  <div class="nav-group">فروش و عملیات</div>
  <button class="nav-btn" data-page="pipeline" onclick="showPage('pipeline',this)"><span class="nav-icon">ق</span><span>قیف فروش</span></button>
  <button class="nav-btn" data-page="operations" onclick="showPage('operations',this)"><span class="nav-icon">س</span><span>سوابق سفارش‌ها</span></button>
  <?php endif; ?>
  <div class="nav-group">تحلیل</div>
  <button class="nav-btn" data-page="reports" onclick="showPage('reports',this)"><span class="nav-icon">گ</span><span>گزارش عملکرد</span></button>
  <button class="nav-btn" data-page="ai" onclick="showPage('ai',this)"><span class="nav-icon">هو</span><span>دستیار هوش مصنوعی</span></button>
  <div class="nav-group">سیستم</div>
  <?php if (hippo_is_manager($hippoUser) && hippo_can($hippoUser, 'settings.manage')): ?><button class="nav-btn" data-page="baseinfo" onclick="showPage('baseinfo',this)"><span class="nav-icon">پ</span><span>فرم‌ها و اطلاعات پایه</span></button><?php endif; ?>
  <button class="nav-btn" data-page="settings" onclick="showPage('settings',this)"><span class="nav-icon">ت</span><span>تنظیمات</span></button>
  <a class="nav-btn" href="pilot_test.php"><span class="nav-icon">✓</span><span>آزمون پایلوت</span></a>
  <a class="nav-btn" href="pilot_issues.php"><span class="nav-icon">!</span><span>ثبت ایراد پایلوت</span></a>
  <?php if (hippo_is_manager($hippoUser) && hippo_can($hippoUser, 'settings.manage')): ?><a class="nav-btn" href="deployment_check.php"><span class="nav-icon">س</span><span>بررسی هاست</span></a><?php endif; ?>
  <?php if (hippo_can($hippoUser, 'users.manage')): ?><a class="nav-btn" href="users.php"><span class="nav-icon">ک</span><span>کاربران و دسترسی‌ها</span></a><?php endif; ?>
  <div class="sidebar-user"><b><?= htmlspecialchars($hippoUser['display_name'], ENT_QUOTES, 'UTF-8') ?></b><span><?= htmlspecialchars($hippoUser['role_label'], ENT_QUOTES, 'UTF-8') ?></span><a href="logout.php">خروج از حساب</a></div>
</aside>
<main>
  <section id="page-dashboard" class="page active"></section>
  <section id="page-customers" class="page"></section>
  <section id="page-activities" class="page"></section>
  <section id="page-followups" class="page"></section>
  <section id="page-mytasks" class="page"></section>
  <section id="page-pipeline" class="page"></section>
  <section id="page-operations" class="page"></section>
  <section id="page-reports" class="page"></section>
  <section id="page-ai" class="page"></section>
  <section id="page-baseinfo" class="page"></section>
  <section id="page-settings" class="page"></section>
  <section id="page-weeks" class="page"></section>
  <section id="page-formula" class="page"></section>
  <section id="page-work" class="page" hidden></section><section id="page-quick" class="page" hidden></section><section id="page-analysis" class="page" hidden></section><section id="page-team" class="page" hidden></section>
</main></div>
<button class="fab" onclick="showPage('activities')" aria-label="ثبت سریع مذاکره">＋ ثبت مذاکره</button>
<nav class="mobile-nav" aria-label="منوی موبایل">
  <button class="active" data-page="dashboard" onclick="showPage('dashboard',this)"><span class="mobile-nav-icon">⌂</span><span>خانه</span></button>
  <button data-page="customers" onclick="showPage('customers',this)"><span class="mobile-nav-icon">م</span><span>مشتریان</span></button>
  <button data-page="activities" onclick="showPage('activities',this)"><span class="mobile-nav-icon mobile-add">＋</span><span>ثبت</span></button>
  <button data-page="followups" onclick="showPage('followups',this)"><span class="mobile-nav-icon">پ</span><span>پیگیری‌ها</span></button>
  <button type="button" onclick="V03Nav.openDrawer()"><span class="mobile-nav-icon">•••</span><span>بیشتر</span></button>
</nav>
<div id="mobileDrawer" class="mobile-drawer" aria-hidden="true"><div class="mobile-drawer-backdrop" onclick="V03Nav.closeDrawer()"></div><aside class="mobile-drawer-panel" aria-label="همه بخش‌ها"><div class="mobile-drawer-head"><div><b>همه بخش‌ها</b><small>دسترسی کامل به فضای کاری</small></div><button class="icon-btn" onclick="V03Nav.closeDrawer()" aria-label="بستن">×</button></div>
  <div class="nav-group">کار روزانه</div>
  <button class="nav-btn" data-page="dashboard" onclick="showPage('dashboard',this);V03Nav.closeDrawer()"><span class="nav-icon">⌂</span><span>مرکز فرمان</span></button>
  <button class="nav-btn" data-page="customers" onclick="showPage('customers',this);V03Nav.closeDrawer()"><span class="nav-icon">م</span><span>مشتریان</span></button>
  <button class="nav-btn" data-page="activities" onclick="showPage('activities',this);V03Nav.closeDrawer()"><span class="nav-icon">＋</span><span>ثبت مذاکره</span></button>
  <button class="nav-btn" data-page="followups" onclick="showPage('followups',this);V03Nav.closeDrawer()"><span class="nav-icon">پ</span><span>پیگیری‌ها</span></button>
  <button class="nav-btn" data-page="mytasks" onclick="showPage('mytasks',this);V03Nav.closeDrawer()"><span class="nav-icon">ک</span><span>وظایف</span></button>
  <?php if (hippo_can($hippoUser, 'plans.view_own') || hippo_can($hippoUser, 'plans.view_team')): ?><a class="nav-btn" href="planning.php"><span class="nav-icon">۴</span><span>برنامه ماهانه</span></a><?php endif; ?>
  <?php if ($hippoRole !== 'center_call'): ?>
  <div class="nav-group">فروش و عملیات</div>
  <button class="nav-btn" data-page="pipeline" onclick="showPage('pipeline',this);V03Nav.closeDrawer()"><span class="nav-icon">ق</span><span>قیف فروش</span></button>
  <button class="nav-btn" data-page="operations" onclick="showPage('operations',this);V03Nav.closeDrawer()"><span class="nav-icon">س</span><span>سوابق سفارش‌ها</span></button>
  <?php endif; ?>
  <div class="nav-group">تحلیل</div>
  <button class="nav-btn" data-page="reports" onclick="showPage('reports',this);V03Nav.closeDrawer()"><span class="nav-icon">گ</span><span>گزارش عملکرد</span></button>
  <button class="nav-btn" data-page="ai" onclick="showPage('ai',this);V03Nav.closeDrawer()"><span class="nav-icon">هو</span><span>دستیار هوش مصنوعی</span></button>
  <div class="nav-group">سیستم</div>
  <?php if (hippo_is_manager($hippoUser) && hippo_can($hippoUser, 'settings.manage')): ?><button class="nav-btn" data-page="baseinfo" onclick="showPage('baseinfo',this);V03Nav.closeDrawer()"><span class="nav-icon">پ</span><span>فرم‌ها و اطلاعات پایه</span></button><?php endif; ?>
  <button class="nav-btn" data-page="settings" onclick="showPage('settings',this);V03Nav.closeDrawer()"><span class="nav-icon">ت</span><span>تنظیمات</span></button>
  <a class="nav-btn" href="pilot_test.php"><span class="nav-icon">✓</span><span>آزمون پایلوت</span></a>
  <a class="nav-btn" href="pilot_issues.php"><span class="nav-icon">!</span><span>ثبت ایراد پایلوت</span></a>
  <?php if (hippo_is_manager($hippoUser) && hippo_can($hippoUser, 'settings.manage')): ?><a class="nav-btn" href="deployment_check.php"><span class="nav-icon">س</span><span>بررسی هاست</span></a><?php endif; ?>
  <?php if (hippo_can($hippoUser, 'users.manage')): ?><a class="nav-btn" href="users.php"><span class="nav-icon">ک</span><span>کاربران و دسترسی‌ها</span></a><?php endif; ?>
</aside></div>
<div id="toast" class="toast" role="status" aria-live="polite">ذخیره شد</div><div id="modal" class="modal" onclick="if(event.target===this)closeModal()"><div id="modalContent" class="modal-card"></div></div>
<script>window.HIPPO_AUTH=<?= json_encode([
  'id'=>$hippoUser['id'],'display_name'=>$hippoUser['display_name'],'role'=>$hippoUser['role'],
  'role_label'=>$hippoUser['role_label'],'team_member_id'=>$hippoUser['team_member_id'],'team_member_valid'=>$hippoUser['team_member_valid'],
  'permissions'=>$hippoUser['permissions'],'permission_fingerprint'=>$hippoUser['permission_fingerprint'],'scope_version'=>$hippoUser['scope_version'],'csrf_token'=>$hippoUser['csrf_token']
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;</script>
<script>
const CACHE_SCOPE_VERSION=String(window.HIPPO_AUTH?.scope_version||'v05.2');
const LEGACY_KEYS=['hippoSalesAssistantOffline_v2','hippoPlast12WeekDashboard_v1','hippoSalesAssistantOffline_v2_recovery'];
function cacheDescriptor(auth=window.HIPPO_AUTH||{}){
  const uid=Number(auth.id||0),role=String(auth.role||'unknown').replace(/[^a-z0-9_-]/gi,''),fp=String(auth.permission_fingerprint||'none').slice(0,24),ver=String(auth.scope_version||CACHE_SCOPE_VERSION).replace(/[^a-z0-9._-]/gi,'');
  const scope=`${uid}:${role}:${fp}:${ver}`;
  return {scope,storage:`hippoSales:user:${scope}:scoped-state`,recovery:`hippoSales:user:${scope}:recovery`,db:`hippoSalesDB-${scope.replace(/[^a-z0-9_-]/gi,'-')}`};
}
let CACHE_DESC=cacheDescriptor(),STORAGE_KEY=CACHE_DESC.storage,RECOVERY_KEY=CACHE_DESC.recovery,IDB_NAME=CACHE_DESC.db;
const IDB_STORE='kv',IDB_KEY='state';let _idb=null;
function clearUnsafeLegacyLocalCache(){try{LEGACY_KEYS.forEach(k=>localStorage.removeItem(k))}catch(e){}}
function migrateLegacyCacheForManager(){
  try{
    if(String(window.HIPPO_AUTH?.role)!=='manager'){clearUnsafeLegacyLocalCache();return}
    if(localStorage.getItem(STORAGE_KEY))return;
    for(const key of LEGACY_KEYS.slice(0,2)){const raw=localStorage.getItem(key);if(raw){JSON.parse(raw);localStorage.setItem(STORAGE_KEY,raw);break}}
    clearUnsafeLegacyLocalCache();
  }catch(e){clearUnsafeLegacyLocalCache()}
}
migrateLegacyCacheForManager();
function idbOpen(){return new Promise((res,rej)=>{try{if(!window.indexedDB)return rej('no-idb');const rq=indexedDB.open(IDB_NAME,1);rq.onupgradeneeded=()=>{try{rq.result.createObjectStore(IDB_STORE)}catch(e){}};rq.onsuccess=()=>res(rq.result);rq.onerror=()=>rej(rq.error)}catch(e){rej(e)}})}
function idbGet(){return new Promise(res=>{if(!_idb)return res(null);try{const rq=_idb.transaction(IDB_STORE,'readonly').objectStore(IDB_STORE).get(IDB_KEY);rq.onsuccess=()=>res(rq.result||null);rq.onerror=()=>res(null)}catch(e){res(null)}})}
function idbSet(val){return new Promise(res=>{if(!_idb)return res(false);try{const tx=_idb.transaction(IDB_STORE,'readwrite');tx.objectStore(IDB_STORE).put(val,IDB_KEY);tx.oncomplete=()=>res(true);tx.onerror=()=>res(false)}catch(e){res(false)}})}
function deleteIndexedDb(name){return new Promise(res=>{try{const q=indexedDB.deleteDatabase(name);q.onsuccess=q.onerror=q.onblocked=()=>res()}catch(e){res()}})}
async function switchScopedCache(nextAuth){
  const next=cacheDescriptor(nextAuth),changed=next.scope!==CACHE_DESC.scope;
  if(!changed)return false;
  const old={...CACHE_DESC};try{localStorage.removeItem(old.storage);localStorage.removeItem(old.recovery)}catch(e){}
  try{_idb?.close()}catch(e){};_idb=null;await deleteIndexedDb(old.db);
  CACHE_DESC=next;STORAGE_KEY=next.storage;RECOVERY_KEY=next.recovery;IDB_NAME=next.db;
  state=sanitizeStateForCurrentScope(buildInitial());selectedWeek=state.project.activeWeek||1;
  return true;
}
async function initDurableStorage(){
  try{if(navigator.storage&&navigator.storage.persist){await navigator.storage.persist()}}catch(e){}
  try{await deleteIndexedDb('hippoSalesDB')}catch(e){}
  try{_idb=await idbOpen()}catch(e){_idb=null}
  if(!_idb)return;
  let idbRaw=null;try{idbRaw=await idbGet()}catch(e){}
  const lsRaw=localStorage.getItem(STORAGE_KEY);
  if(idbRaw&&!SERVER_STATE_LOADED){
    try{const idbState=sanitizeStateForCurrentScope(mergeState(JSON.parse(idbRaw)));
      const curAt=+((state.project&&state.project.savedAt)||0),idbAt=+((idbState.project&&idbState.project.savedAt)||0);
      if(idbAt>curAt){state=idbState;selectedWeek=state.project.activeWeek||1;renderAll();toast('اطلاعات همین حساب از حافظه پایدار بازیابی شد')}
    }catch(e){}
  }else if(!idbRaw&&lsRaw){try{await idbSet(lsRaw)}catch(e){}}
  try{await idbSet(JSON.stringify(sanitizeStateForCurrentScope(state)))}catch(e){}
}
const defaultWeeks=[
{n:1,title:'شروع همکاری و تعیین نقطه صفر',subtitle:'زمان شروع: از فردای تأیید همکاری و دریافت اطلاعات اولیه محصول',goal:'توافق روی مسیر ۱۲ هفته‌ای، تعریف نقش‌ها و مشخص کردن اطلاعات لازم برای شروع فروش.',principle:'اول مسیر را ساده و قابل اجرا می‌کنیم؛ نه وعده بزرگ می‌دهیم و نه هزینه ایجاد می‌کنیم.',tasks:['جلسه کوتاه با مدیریت برای تأیید اصول همکاری','دریافت اطلاعات محصول موجود، ظرفیت، قیمت پایه و شرایط فروش','تعریف بازارهای احتمالی بدون ورود به هزینه جدید','تعیین قالب گزارش هفتگی ساده و قابل فهم'],outputs:['صورت‌جلسه شروع','لیست اطلاعات موردنیاز','چارچوب گزارش هفتگی'],metrics:[['جلسه تصمیم','۱'],['هزینه توسعه','۰'],['هفته برنامه','۱۲'],['گزارش پایه','۱']]},
{n:2,title:'شناخت محصول، ظرفیت و محدودیت‌های واقعی',subtitle:'قبل از فروش باید دقیق بدانیم چه چیزی، با چه کیفیت و چه محدودیتی عرضه می‌شود',goal:'تبدیل محصول فعلی به یک پیشنهاد فروش واضح برای مشتری صنعتی.',principle:'در این مرحله هنوز فروش را تبلیغ نمی‌کنیم؛ اول محصول را برای گفت‌وگو با مشتری قابل توضیح می‌کنیم.',tasks:['ثبت مشخصات گرانول موجود: نوع، رنگ، کاربرد، کیفیت و بسته‌بندی','بررسی ظرفیت تولید و موجودی اولیه قابل فروش','مشخص کردن حداقل سفارش، زمان تحویل و شرایط پرداخت قابل قبول','فهرست‌کردن ریسک‌های محصول: کیفیت، ثبات تامین، قیمت و برگشتی'],outputs:['فایل مشخصات محصول','جدول ظرفیت و موجودی','حدود اولیه قیمت و فروش'],metrics:[['فایل محصول','۱'],['جدول موجودی','۱'],['ریسک فروش','۳-۵'],['هزینه تبلیغ','۰']]},
{n:3,title:'نقشه بازار و انتخاب مشتریان اولویت‌دار',subtitle:'هدف، پیدا کردن بازارهایی است که احتمال خریدشان واقعی‌تر است',goal:'شناسایی صنایع مصرف‌کننده و انتخاب ۳ تا ۵ گروه مشتری برای شروع ارتباط.',principle:'بازار ایران پراکنده و رابطه‌محور است؛ تمرکز اولیه روی مشتریانی است که دسترسی ساده‌تر و احتمال فروش واقعی‌تری دارند.',tasks:['بررسی مصرف‌کنندگان احتمالی گرانول در استان و خارج استان','دسته‌بندی مشتریان: تولیدکننده لوله، نایلون، قطعات، سبد، تزریق و…','اولویت‌بندی براساس نزدیکی، حجم مصرف، نقدی‌بودن و احتمال تست محصول','حذف بازارهایی که فعلاً دسترسی یا صرفه اقتصادی ندارند'],outputs:['نقشه بازار اولیه','۳ تا ۵ سگمنت هدف','لیست اولویت تماس'],metrics:[['گروه بازار','۵'],['مشتری بالقوه','۳۰+'],['اولویت اصلی','۳'],['هزینه جذب','۰']]},
{n:4,title:'ساخت بانک مشتریان و ابزار معرفی کم‌هزینه',subtitle:'ابزارهای فروش باید ساده، قابل ارسال و قابل اصلاح باشند',goal:'آماده‌سازی حداقل ابزار لازم برای شروع تماس حرفه‌ای با مشتری.',principle:'دیجیتال در این مرحله فقط پشتیبان فروش است؛ ابزارها ساده ساخته می‌شوند تا بعداً با نتیجه بازار توسعه پیدا کنند.',tasks:['ساخت بانک اطلاعات مشتریان با نام، شهر، حوزه فعالیت و شماره تماس','آماده‌سازی متن معرفی کوتاه برای تماس و واتساپ','طراحی فایل یک‌صفحه‌ای معرفی محصول و شرکت','ایجاد فرم ساده برای ثبت درخواست نمونه یا سفارش اولیه'],outputs:['بانک مشتریان اولیه','متن معرفی فروش','فایل معرفی یک‌صفحه‌ای','فرم ثبت پیگیری'],metrics:[['رکورد مشتری','۶۰+'],['متن معرفی','۱'],['فایل فروش','۱'],['هزینه اجرا','کم']]},
{n:5,title:'شروع ارتباط مستقیم با بازار',subtitle:'تماس واقعی با مشتری، اولین آزمون جدی بازار است',goal:'گرفتن بازخورد اولیه از بازار و تشخیص مشتریان جدی.',principle:'هدف هفته پنجم فروش قطعی نیست؛ هدف فهمیدن زبان بازار و جداکردن مشتری واقعی از تماس بی‌نتیجه است.',tasks:['تماس با مشتریان اولویت‌دار و معرفی کوتاه محصول','ارسال فایل معرفی برای مشتریانی که علاقه نشان می‌دهند','ثبت پاسخ‌ها: نیاز، قیمت مورد انتظار، مصرف ماهانه و شرایط پرداخت','تفکیک مشتریان به سه گروه: جدی، نیازمند پیگیری، نامرتبط'],outputs:['گزارش تماس‌ها','لیست مشتریان جدی','لیست ایرادها و سوالات بازار'],metrics:[['تماس اولیه','۳۰-۴۰'],['گفت‌وگوی مؤثر','۱۰+'],['مشتری جدی','۳-۵'],['گزارش بازار','۱']]},
{n:6,title:'ارسال نمونه و دریافت بازخورد فنی',subtitle:'در فروش صنعتی، کیفیت و تست محصول قبل از سفارش جدی تعیین‌کننده است',goal:'تبدیل علاقه اولیه به تست محصول و بازخورد قابل استفاده.',principle:'نمونه را بی‌هدف پخش نمی‌کنیم؛ فقط برای مشتریانی ارسال می‌شود که احتمال سفارش و تکرار خرید داشته باشند.',tasks:['انتخاب مشتریان مناسب برای ارسال نمونه محدود','هماهنگی مقدار نمونه، نوع بسته‌بندی و نحوه ارسال','گرفتن بازخورد فنی: کیفیت، رنگ، ثبات، بو، ناخالصی و کاربردپذیری','ثبت نتیجه تست برای اصلاح پیام فروش یا محصول'],outputs:['لیست نمونه‌های ارسال‌شده','گزارش بازخورد فنی','فهرست ایرادها و نقاط قوت محصول'],metrics:[['نمونه هدفمند','۳-۵'],['زمان پیگیری','۷ روز'],['گزارش فنی','۱'],['هزینه','کم و کنترل‌شده']]},
{n:7,title:'مذاکره قیمت، پرداخت و تحویل',subtitle:'فروش فقط قیمت نیست؛ ترکیب قیمت، نقدینگی و تحویل مهم است',goal:'تشخیص مدل تجاری قابل قبول برای مشتری و شرکت.',principle:'ممکن است فروش نقدی با قیمت کمتر بهتر از فروش اعتباری با حاشیه بیشتر باشد؛ تصمیم نهایی بعد از داده کامل‌تر گرفته می‌شود.',tasks:['مذاکره با مشتریان دارای بازخورد مثبت','بررسی حساسیت مشتری به قیمت، نقدی‌بودن و زمان تحویل','ثبت پیشنهادهای مختلف: نقدی، اعتباری محدود، سفارش آزمایشی','محاسبه حاشیه سود تقریبی هر سناریو با مدیریت'],outputs:['لیست مذاکرات جدی','سناریوهای فروش اولیه','جدول موانع خرید'],metrics:[['مذاکره جدی','۵+'],['سناریو فروش','۲-۳'],['جدول قیمت','۱'],['تصمیم مدیریتی','۱']]},
{n:8,title:'بازاریابی دیجیتال کم‌هزینه و پشتیبان فروش',subtitle:'دیجیتال مارکتینگ در شروع، ابزار اعتمادسازی و دریافت سرنخ است؛ نه خرج تبلیغاتی سنگین',goal:'ساخت حضور اولیه آنلاین با هزینه پایین برای پشتیبانی از تماس‌های فروش.',principle:'تبلیغات پولی فقط وقتی مطرح می‌شود که بدانیم کدام مشتری و کدام پیام جواب می‌دهد؛ فعلاً هزینه دیجیتال حداقلی می‌ماند.',tasks:['به‌روزرسانی یا ساخت صفحه معرفی ساده محصول و شرکت','آماده‌سازی کاتالوگ دیجیتال و لینک قابل ارسال','ایجاد فرم درخواست خرید، نمونه یا تماس کارشناسی','انتشار محتوای محدود و تخصصی فقط برای اعتبارسازی B2B'],outputs:['صفحه معرفی آنلاین','کاتالوگ دیجیتال','فرم سرنخ فروش','چک‌لیست اعتمادسازی'],metrics:[['صفحه محصول','۱'],['کاتالوگ دیجیتال','۱'],['فرم درخواست','۱'],['هزینه تبلیغ','بدون کمپین']]},
{n:9,title:'سنجش کانال‌ها و تمرکز روی مسیرهای جواب‌ده',subtitle:'هر مسیر فروش ارزش ادامه دادن ندارد؛ باید حذف و تمرکز انجام شود',goal:'تشخیص اینکه کدام مشتری، پیام و کانال بیشترین احتمال فروش دارد.',principle:'از این مرحله به بعد براساس پاسخ واقعی بازار تصمیم می‌گیریم، نه حدس.',tasks:['مقایسه نتایج تماس مستقیم، معرفی آنلاین، معرفی واسطه‌ای و نمونه‌دهی','حذف مشتریان کم‌کیفیت یا پرریسک از اولویت پیگیری','تمرکز روی ۲ تا ۳ گروه مشتری که واکنش بهتری داشته‌اند','اصلاح پیام فروش براساس اعتراض‌ها و سوالات واقعی بازار'],outputs:['گزارش کانال‌های مؤثر','لیست تمرکز هفته‌های بعد','نسخه اصلاح‌شده پیام فروش'],metrics:[['کانال بررسی','۴'],['کانال منتخب','۲-۳'],['حذف مسیر ضعیف','۲۰٪'],['پیام اصلاحی','۱']]},
{n:10,title:'فروش آزمایشی و نظم سفارش',subtitle:'هدف، رسیدن به اولین سفارش‌های قابل تکرار است؛ نه فروش تصادفی',goal:'تبدیل مذاکرات جدی به سفارش آزمایشی و ساخت نظم پیگیری فروش.',principle:'اگر فروش رخ ندهد هم داده به ما می‌گوید مشکل قیمت، کیفیت، پرداخت یا دسترسی به مشتری است.',tasks:['پیگیری مشتریان گرم برای سفارش آزمایشی یا خرید محدود','ثبت دقیق مقدار، قیمت، نحوه پرداخت، زمان تحویل و رضایت مشتری','بررسی امکان تکرار خرید و حجم مصرف ماهانه مشتری','ساخت روال پیگیری پس از فروش برای جلوگیری از قطع ارتباط'],outputs:['لیست سفارش‌های آزمایشی','فرم ثبت سفارش و تحویل','لیست مشتریان قابل تکرار'],metrics:[['سفارش آزمایشی','۱-۳'],['ثبت معامله','۱۰۰٪'],['پیگیری پس از فروش','۷ روز'],['روال سفارش','۱']]},
{n:11,title:'اتصال فروش به تولید، انبار و نقدینگی',subtitle:'فروش خوب باید با موجودی، تولید و جریان نقدی هم‌خوان باشد',goal:'فهمیدن اینکه فروش ایجادشده با ظرفیت واقعی شرکت و نقدینگی سازگار است یا نه.',principle:'ممکن است فروش زیاد اما اعتباری برای شرکت خطرناک باشد. هدف رسیدن به فروش قابل مدیریت است، نه فقط عدد بزرگ.',tasks:['مقایسه سفارش‌ها و مذاکرات با موجودی اولیه و ظرفیت تولید','بررسی زمان خرید مواد اولیه و اثر آن بر نقدینگی','محاسبه تقریبی حاشیه سود هر نوع فروش: نقدی، اعتباری، عمده یا سفارش محدود','تشخیص فشارهای عملیاتی: تامین، تولید، تحویل و وصول پول'],outputs:['جدول فروش و موجودی','نمای اولیه جریان نقدی','لیست ریسک‌های اجرایی'],metrics:[['جدول انبار','۱'],['جدول نقدینگی','۱'],['سناریو فروش','۳'],['لیست ریسک','۱']]},
{n:12,title:'جمع‌بندی ۹۰ روزه و پیشنهاد مدل فروش',subtitle:'در پایان هفته دوازدهم باید تصمیم قابل دفاع داشته باشیم، نه حدس',goal:'تبدیل داده‌های ۱۲ هفته به یک مدل فروش عملی برای دوره بعد.',principle:'اگر قرار است بعد از هفته ۱۲ هزینه کنیم، باید دقیق بدانیم برای چه چیزی، با چه عددی و با چه انتظار اقتصادی هزینه می‌کنیم.',tasks:['جمع‌بندی نتایج تماس‌ها، نمونه‌ها، مذاکرات و فروش‌های آزمایشی','مشخص کردن بهترین گروه مشتری و بهترین مدل پرداخت','پیشنهاد مسیر دوره بعد: ادامه فروش مستقیم، توسعه دیجیتال، نماینده فروش یا صادرات اولیه','ارائه تصمیم مرحله بعد همراه با هزینه، ریسک و بازگشت مورد انتظار'],outputs:['گزارش ۱۲ هفته‌ای','مدل فروش پیشنهادی','برنامه دوره بعد'],metrics:[['هفته داده','۱۲'],['مدل فروش','۱'],['تصمیم توسعه','۱'],['ریسک هزینه','کنترل‌شده']]}
];
const STAGES=[
 {id:'new',label:'سرنخ جدید',color:'blue'},{id:'contacted',label:'ارتباط اولیه',color:'dark'},{id:'qualified',label:'نیاز تأییدشده',color:'ok'},{id:'sample',label:'نمونه/تست',color:'warn'},
 {id:'negotiation',label:'مذاکره',color:'warn'},{id:'trial',label:'سفارش آزمایشی',color:'ok'},{id:'won',label:'خرید/تکرار',color:'ok'},{id:'paused',label:'متوقف/نامرتبط',color:'danger'}
];
/* ردیابی سفارش — روی همان تعامل‌هایی که نتیجه‌شان سفارش آزمایشی/خرید بوده (resultId). وضعیت از پرشدن فیلدها مشتق می‌شود، ذخیره نمی‌شود. */
const ORDER_RESULT_IDS=['trial_order','purchase'];
const PRODUCTION_SOURCES=[['fresh','تازه تولید شده'],['stock','از موجودی قدیم']];
const OUTCOME_TYPES={ok:{label:'تحویل موفق',color:'ok'},complaint:{label:'ایراد داشت',color:'danger'},no_repeat:{label:'تکرار نشد',color:'danger'}};
const CHANNELS=[['call','تماس تلفنی'],['whatsapp','واتساپ/پیام'],['meeting','جلسه'],['sample','ارسال/پیگیری نمونه'],['email','ایمیل/پیش‌فاکتور'],['other','سایر']];
const ROLE_OPTIONS=[['owner','مدیر شرکت'],['sales_manager','مدیر فروش'],['marketer','بازاریاب/کارشناس فروش'],['operations','تولید/عملیات'],['finance','مالی'],['viewer','ناظر']];
const CALL_CENTER_RESULT_IDS=['price_high','competitor_lower','payment','quality','sample_requested','has_inventory','decision_maker','bad_timing','transport','min_order','not_fit','quote_requested','follow_up','stop'];
const DEFAULT_REPLIES=[
 {id:'price_high',label:'قیمت برای مشتری بالاست',category:'قیمت',response:'قیمت را کامل می‌فهمم. عدد نهایی به حجم، نحوه پرداخت و مقصد حمل بستگی دارد. اگر حجم شروع و مقصدتان را بگویید، بهترین قیمتی که می‌توانم بدهم را دقیق برایتان می‌آورم.',action:'حجم سفارش، پرداخت نقدی، هزینه حمل و قیمت دقیق رقیب را قبل از پیگیری بعدی بررسی کنید.',stage:'negotiation',manager:true},
 {id:'competitor_lower',label:'رقیب قیمت بهتری داده',category:'رقابت',response:'طبیعی است که مقایسه کنید. برای اینکه مقایسه واقعی باشد، ضخامت و گرماژ، شرایط پرداخت و محل تحویل رقیب را هم کنار قیمت بگذاریم؛ معمولاً اختلاف واقعی از عدد اول کمتر درمی‌آید. مشخصات پیشنهادشان را دارید؟',action:'قیمت، کیفیت، شرایط پرداخت و حمل رقیب را جداگانه استخراج کنید؛ کاهش عمومی قیمت ندهید.',stage:'negotiation',manager:true},
 {id:'payment',label:'شرایط پرداخت مناسب نیست',category:'پرداخت',response:'قابل مذاکره است. معمولاً برای شروع، یک سفارش محدود با ریسک کمتر برای هر دو طرف بهتر جواب می‌دهد. چه مدل پرداختی برای شما عملی است تا ببینم کجا می‌توانیم به هم برسیم؟',action:'سقف اعتبار، درصد نقدی، حجم آزمایشی و زمان وصول را با مالی بررسی کنید.',stage:'negotiation',manager:true},
 {id:'quality',label:'کیفیت یا مشخصات فنی باید بررسی شود',category:'فنی',response:'درست است، بدون تست نباید تصمیم بگیرید. نمونه و برگه مشخصات فنی را آماده می‌کنم. کاربرد دقیق و مهم‌ترین معیاری که کیفیت را با آن می‌سنجید چیست؟',action:'معیار تست، کاربرد، نتیجه مورد انتظار و مسئول فنی مشتری را ثبت کنید.',stage:'sample',manager:false},
 {id:'sample_requested',label:'نمونه درخواست شد',category:'فنی',response:'نمونه را آماده می‌کنم. برای اینکه تست واقعی باشد بفرمایید چه مقدار لازم دارید، به چه آدرسی بفرستم، و تقریباً چه تاریخی نتیجه تست مشخص می‌شود؟',action:'نمونه را هدفمند ارسال کنید و تاریخ قطعی دریافت نتیجه فنی تعیین کنید.',stage:'sample',manager:false},
 {id:'has_inventory',label:'فعلاً موجودی دارد',category:'زمان خرید',response:'خوب است، پس عجله‌ای نیست. برای اینکه بی‌موقع مزاحمتان نشوم، موجودی فعلی تقریباً تا چه زمانی کفایت می‌کند؟ همان حوالی تماس می‌گیرم.',action:'تاریخ اتمام موجودی و دوره خرید مشتری را ثبت و پیگیری را زمان‌بندی کنید.',stage:'contacted',manager:false},
 {id:'decision_maker',label:'تصمیم‌گیرنده در دسترس نبود',category:'دسترسی',response:'متوجهم. موضوع کوتاه است. بهتر است مستقیم با مسئول خرید مطرح کنم یا شما منتقل می‌کنید؟ معمولاً چه ساعتی در دسترس هستند؟',action:'نام تصمیم‌گیرنده، نقش، شماره مستقیم و بهترین زمان تماس را پیدا کنید.',stage:'contacted',manager:false},
 {id:'bad_timing',label:'زمان خرید مناسب نیست',category:'زمان خرید',response:'مشکلی نیست. برای اینکه دقیق برنامه‌ریزی کنم، خریدهایتان معمولاً چه دوره‌ای است و چند وقت قبلش تصمیم می‌گیرید؟',action:'دوره خرید و تاریخ تصمیم را ثبت کنید؛ پیگیری زودهنگام و تکراری نداشته باشید.',stage:'contacted',manager:false},
 {id:'transport',label:'هزینه یا روش حمل مسئله است',category:'حمل',response:'حمل را جدا حساب کنیم بهتر است. مقصد و حجم تقریبی را بفرمایید تا هزینه حمل و گزینه تحویل مناسب‌تر را بررسی کنم و ببینم چقدر می‌شود کمش کرد.',action:'مقصد، حجم، شیوه تحویل و سهم حمل در قیمت نهایی را محاسبه کنید.',stage:'negotiation',manager:true},
 {id:'min_order',label:'حداقل سفارش مناسب نیست',category:'حجم',response:'برای سفارش اول انعطاف داریم. حجمی که برای شروع برایتان منطقی است چقدر است؟ ببینم می‌توانم همان را جا بیندازم.',action:'حجم شروع، هزینه تولید/حمل و حداقل اقتصادی شرکت را مقایسه کنید.',stage:'negotiation',manager:true},
 {id:'not_fit',label:'نیاز مشتری با محصول منطبق نیست',category:'تناسب',response:'پس این محصول جواب کارتان را نمی‌دهد. کاربرد و مشخصاتی که لازم دارید را ثبت می‌کنم؛ اگر بعداً چیز متناسبی داشتیم خبر می‌دهم و بی‌مورد وقتتان را نمی‌گیرم.',action:'مشتری را از اولویت فعال خارج کنید و دلیل عدم تناسب را نگه دارید.',stage:'paused',manager:false},
 {id:'quote_requested',label:'درخواست پیش‌فاکتور',category:'خرید',response:'پیش‌فاکتور را آماده می‌کنم. سه چیز را تأیید کنید تا دقیق دربیاید: حجم، مقصد تحویل، و شرایط پرداخت.',action:'حجم، قیمت، حمل، اعتبار پیشنهاد و تصمیم‌گیرنده را کامل کنید.',stage:'negotiation',manager:false},
 {id:'trial_order',label:'سفارش آزمایشی ثبت شد',category:'خرید',response:'ممنون از اعتمادتان. مقدار، قیمت، پرداخت و زمان تحویل را نهایی می‌کنم و بعد از مصرف، نتیجه را از خودتان می‌پرسم.',action:'سفارش، تحویل، وصول و تاریخ پیگیری رضایت را دقیق ثبت کنید.',stage:'trial',manager:true},
 {id:'purchase',label:'خرید انجام شد',category:'خرید',response:'ممنون از خریدتان. برای اینکه دفعه بعد بدون وقفه تأمین شوید، تقریباً چه زمانی به سفارش بعدی می‌رسید؟',action:'رضایت، مصرف واقعی، زمان خرید مجدد و فرصت افزایش حجم را پیگیری کنید.',stage:'won',manager:false},
 {id:'follow_up',label:'نیاز به پیگیری دارد',category:'پیگیری',response:'باشد، همان زمانی که گفتید پیگیری می‌کنم. چیزی هست که تا آن موقع برایتان آماده کنم؟',action:'تاریخ، موضوع و خروجی مورد انتظار پیگیری بعدی را روشن کنید.',stage:'qualified',manager:false},
 {id:'stop',label:'فعلاً پیگیری متوقف شود',category:'توقف',response:'متوجه شدم، فعلاً پیگیری نمی‌کنم. اگر شرایطتان تغییر کرد یک پیام بدهید؛ پرونده‌تان را باز نگه می‌دارم.',action:'دلیل توقف را ثبت کنید و فقط در صورت تغییر شرایط دوباره فعال کنید.',stage:'paused',manager:false}
];

const FORM_ROLE_KEYS=['manager','marketer','center_call'];
const DEFAULT_FORM_CONFIG={
 customer:{
  name:{label:'نام مشتری یا شرکت',enabled:true,required:true,locked:true,roles:{manager:true,marketer:true,center_call:true}},
  phone:{label:'شماره تماس',enabled:true,required:false,roles:{manager:true,marketer:true,center_call:true}},
  contact:{label:'نام فرد تماس',enabled:true,required:false,roles:{manager:true,marketer:true,center_call:true}},
  industry:{label:'زمینه فعالیت',enabled:true,required:false,masterKey:'industry',roles:{manager:true,marketer:true,center_call:true}},
  source:{label:'نحوه آشنایی',enabled:true,required:false,masterKey:'source',roles:{manager:true,marketer:true,center_call:true}},
  productGroup:{label:'گروه محصولات',enabled:true,required:false,masterKey:'productGroup',roles:{manager:true,marketer:true,center_call:true}},
  consumptionType:{label:'نوع مصرف',enabled:true,required:false,masterKey:'consumptionType',roles:{manager:true,marketer:true,center_call:true}},
  packaging:{label:'بسته‌بندی',enabled:true,required:false,masterKey:'packaging',roles:{manager:true,marketer:true,center_call:true}},
  currency:{label:'نوع ارز',enabled:true,required:false,masterKey:'currency',roles:{manager:true,marketer:true,center_call:true}},
  city:{label:'شهر',enabled:true,required:false,roles:{manager:true,marketer:true,center_call:true}},
  address:{label:'آدرس',enabled:true,required:false,roles:{manager:true,marketer:true,center_call:false}},
  assignee:{label:'مسئول مشتری',enabled:true,required:true,roles:{manager:true,marketer:false,center_call:false}},
  stage:{label:'مرحله فروش',enabled:true,required:true,roles:{manager:true,marketer:true,center_call:false}},
  estimatedVolume:{label:'مقدار مصرف یا خرید بالقوه',enabled:true,required:false,roles:{manager:true,marketer:true,center_call:false}},
  nextFollowUp:{label:'پیگیری بعدی',enabled:true,required:false,roles:{manager:true,marketer:true,center_call:true}},
  paymentPreference:{label:'شرایط پرداخت مورد انتظار',enabled:true,required:false,roles:{manager:true,marketer:true,center_call:false}},
  competitor:{label:'رقیب یا تأمین‌کننده فعلی',enabled:true,required:false,roles:{manager:true,marketer:true,center_call:false}},
  technicalNeed:{label:'نیاز فنی یا محصول موردنیاز',enabled:true,required:false,roles:{manager:true,marketer:true,center_call:true}},
  note:{label:'یادداشت پرونده',enabled:true,required:false,roles:{manager:true,marketer:false,center_call:false}},
  score:{label:'امتیازدهی مشتری',enabled:true,required:false,roles:{manager:true,marketer:false,center_call:false}}
 },
 interaction:{
  customer:{label:'مشتری',enabled:true,required:true,locked:true,roles:{manager:true,marketer:true,center_call:true}},
  channel:{label:'کانال ارتباط',enabled:true,required:true,roles:{manager:true,marketer:true,center_call:true}},
  results:{label:'نتیجه تماس یا مذاکره',enabled:true,required:true,locked:true,roles:{manager:true,marketer:true,center_call:true}},
  contactFor:{label:'ارتباط برای',enabled:true,required:false,masterKey:'contactFor',roles:{manager:true,marketer:true,center_call:true}},
  route:{label:'مسیر ارتباط',enabled:true,required:false,masterKey:'route',roles:{manager:true,marketer:true,center_call:true}},
  currency:{label:'نوع ارز',enabled:true,required:false,masterKey:'currency',roles:{manager:true,marketer:true,center_call:true}},
  nextFollowUp:{label:'پیگیری بعدی',enabled:true,required:false,roles:{manager:true,marketer:true,center_call:true}},
  week:{label:'ثبت در هفته',enabled:true,required:false,roles:{manager:true,marketer:true,center_call:false}},
  volume:{label:'حجم تقریبی اعلام‌شده مشتری',enabled:true,required:false,roles:{manager:true,marketer:true,center_call:false}},
  price:{label:'قیمت مورد انتظار مشتری',enabled:true,required:false,roles:{manager:true,marketer:true,center_call:false}},
  member:{label:'مسئول ثبت',enabled:true,required:false,roles:{manager:true,marketer:true,center_call:false}},
  note:{label:'یادداشت مذاکره',enabled:true,required:false,roles:{manager:true,marketer:true,center_call:true}}
 }
};
const DEFAULT_MASTER_DATA={
 source:{label:'نحوه آشنایی',addMode:'approval',options:[['source_phone','تماس تلفنی'],['source_referral','معرفی یا رابط'],['source_visit','بازدید حضوری'],['source_research','تحقیق و توسعه'],['source_site','سایت یا شبکه اجتماعی'],['source_exhibition','نمایشگاه']]},
 industry:{label:'زمینه فعالیت',addMode:'approval',options:[['industry_packaging','بسته‌بندی'],['industry_polymer','پلیمر و گرانول'],['industry_film','نایلون و فیلم'],['industry_injection','قطعات تزریقی'],['industry_recycling','بازیافت'],['industry_trade','بازرگانی']]},
 productGroup:{label:'گروه محصولات',addMode:'approval',options:[['product_stretch','فیلم استرچ'],['product_shrink','فیلم شرینک'],['product_granule','گرانول'],['product_masterbatch','مستربچ'],['product_other','سایر']]},
 consumptionType:{label:'نوع مصرف',addMode:'approval',options:[['consumption_continuous','تولید مستمر'],['consumption_project','سفارش پروژه‌ای'],['consumption_trial','مصرف آزمایشی'],['consumption_seasonal','مصرف فصلی']]},
 packaging:{label:'بسته‌بندی',addMode:'approval',options:[['pack_25kg','کیسه ۲۵ کیلویی'],['pack_jumbo','جامبوبگ'],['pack_roll','رول'],['pack_pallet','پالت'],['pack_custom','سفارشی']]},
 currency:{label:'نوع ارز',addMode:'manager_only',options:[['currency_irr','ریال'],['currency_toman','تومان'],['currency_usd','دلار'],['currency_eur','یورو']]},
 contactFor:{label:'ارتباط برای',addMode:'approval',options:[['contact_intro','معرفی اولیه'],['contact_price','پیگیری قیمت'],['contact_sample','ارسال یا پیگیری نمونه'],['contact_quote','پیش‌فاکتور'],['contact_order','پیگیری سفارش'],['contact_after_sales','خدمات پس از فروش']]},
 route:{label:'مسیر ارتباط',addMode:'approval',options:[['route_phone','تماس تلفنی'],['route_whatsapp','واتساپ'],['route_meeting','جلسه حضوری'],['route_referral','معرفی واسطه'],['route_exhibition','نمایشگاه']]}
};
function cloneDefaultFormConfig(){return JSON.parse(JSON.stringify(DEFAULT_FORM_CONFIG))}
function cloneDefaultMasterData(){const out={};Object.entries(DEFAULT_MASTER_DATA).forEach(([key,item])=>{out[key]={label:item.label,addMode:item.addMode,options:item.options.map(([id,label])=>({id,label,active:true,status:'active',system:true}))}});return out}
function mergeFormConfig(oldConfig){const base=cloneDefaultFormConfig(),old=oldConfig&&typeof oldConfig==='object'?oldConfig:{};Object.keys(base).forEach(entity=>Object.keys(base[entity]).forEach(key=>{const cur=old?.[entity]?.[key];if(cur&&typeof cur==='object')base[entity][key]={...base[entity][key],...cur,roles:{...base[entity][key].roles,...(cur.roles||{})}}}));return base}
function mergeMasterData(oldData){const base=cloneDefaultMasterData(),old=oldData&&typeof oldData==='object'?oldData:{};Object.keys(base).forEach(key=>{const cur=old[key];if(!cur||typeof cur!=='object')return;base[key]={...base[key],...cur};const byId={};(Array.isArray(cur.options)?cur.options:[]).forEach(o=>{if(o&&o.id)byId[o.id]=o});const options=base[key].options.map(o=>byId[o.id]?{...o,...byId[o.id]}:o);Object.values(byId).forEach(o=>{if(!options.some(x=>x.id===o.id))options.push(o)});base[key].options=options});return base}

function buildInitial(){return {
 version:2,project:{name:'Hippo Plast',startDate:'',activeWeek:1,lastSaved:'',currentMemberId:'m1',analysisWeek:1},
 team:[{id:'m1',name:'امین',role:'توسعه بازار و پیگیری فروش',access:'sales_manager'},{id:'m2',name:'مدیریت شرکت',role:'تصمیم‌گیری و تأیید تجاری',access:'owner'},{id:'m3',name:'مسئول تولید',role:'ظرفیت، کیفیت و تحویل',access:'operations'},{id:'m4',name:'مسئول مالی',role:'قیمت، وصول و نقدینگی',access:'finance'}],
 weeks:defaultWeeks.map(w=>({...w,status:'not_started',notes:'',tasks:w.tasks.map((t,i)=>({id:`w${w.n}t${i}`,text:t,status:'not_started',assignee:'m1',note:'',custom:false})),outputs:w.outputs.map((o,i)=>({id:`w${w.n}o${i}`,text:o,done:false})),metrics:w.metrics.map((m,i)=>({id:`w${w.n}m${i}`,name:m[0],target:m[1],actual:''}))})),
 customers:[],interactions:[],replyLibrary:DEFAULT_REPLIES.map(x=>({...x,active:true})),formConfig:cloneDefaultFormConfig(),masterData:cloneDefaultMasterData(),weeklyReports:[],
 formula:{initialInventory:0,production:0,confirmedSales:0,cashQty:0,cashPrice:0,creditQty:0,creditPrice:0,collectionRate:0,materialQty:0,materialPrice:0,operatingExpenses:0,notes:'',scenarios:[{name:'قیمت کمتر + فروش نقدی',price:0,qty:0,cashRate:100,margin:0},{name:'قیمت بالاتر + فروش اعتباری',price:0,qty:0,cashRate:20,margin:0},{name:'فروش محدود آزمایشی',price:0,qty:0,cashRate:100,margin:0},{name:'مشتری تکرارشونده',price:0,qty:0,cashRate:70,margin:0}]},
 settings:{crmView:'cards',customerSearch:'',customerStage:'all',taskStatus:'all'}
}}
function load(){let raw=null;try{raw=localStorage.getItem(STORAGE_KEY)}catch(e){}if(!raw)return sanitizeStateForCurrentScope(buildInitial());try{return sanitizeStateForCurrentScope(mergeState(JSON.parse(raw)))}catch(e){try{localStorage.removeItem(STORAGE_KEY)}catch(x){}return sanitizeStateForCurrentScope(buildInitial())}}
/* کتابخانه پاسخ‌ها: متن پیش‌فرض‌های به‌روزشده اعمال می‌شود، ولی هر پاسخی که
   خودت ویرایش کرده‌ای (edited:true) و هر پاسخ سفارشی، دست‌نخورده می‌ماند. */
function mergeReplyLibrary(oldLib,baseLib){
  if(!Array.isArray(oldLib)||!oldLib.length)return baseLib;
  const byId={};oldLib.forEach(r=>{if(r&&r.id)byId[r.id]=r});
  const out=baseLib.map(def=>{
    const cur=byId[def.id];
    if(!cur)return {...def};
    delete byId[def.id];
    if(cur.edited)return cur;                       // ویرایش دستی: دست نزن
    return {...def,active:cur.active!==false};      // پیش‌فرض: متن تازه، وضعیت فعال/غیرفعال حفظ شود
  });
  Object.values(byId).forEach(r=>out.push(r));      // پاسخ‌های سفارشی خودت
  return out;
}
function mergeState(old,authoritative=false){const base=buildInitial();const s={...base,...old,project:{...base.project,...(old.project||{})},formula:{...base.formula,...(old.formula||{})},settings:{...base.settings,...(old.settings||{})},formConfig:mergeFormConfig(old.formConfig),masterData:mergeMasterData(old.masterData)};s.team=(old.team?.length?old.team:base.team).map((m,i)=>({...m,access:m.access||(['sales_manager','owner','operations','finance'][i]||'marketer')}));s.project.currentMemberId=s.team.some(m=>m.id===s.project.currentMemberId)?s.project.currentMemberId:s.team[0]?.id;s.customers=Array.isArray(old.customers)?old.customers:[];s.interactions=Array.isArray(old.interactions)?old.interactions:[];s.replyLibrary=mergeReplyLibrary(old.replyLibrary,base.replyLibrary);s.weeklyReports=Array.isArray(old.weeklyReports)?old.weeklyReports:[];s.weeks=defaultWeeks.map((dw,i)=>{const ow=(old.weeks||[])[i];if(!ow)return base.weeks[i];return {...base.weeks[i],...ow,tasks:(ow.tasks||base.weeks[i].tasks).map(t=>({...t,assignee:t.assignee||s.team[0]?.id||''})),outputs:ow.outputs||base.weeks[i].outputs,metrics:ow.metrics||base.weeks[i].metrics}});return s}
function sanitizeStateForCurrentScope(input){
  const s=input&&typeof input==='object'?input:buildInitial();
  const full=String(window.HIPPO_AUTH?.role)==='manager'&&!!window.HIPPO_AUTH?.permissions?.['state.view_full'];
  if(full)return s;
  if(String(window.HIPPO_AUTH?.role)==='center_call'){
    s.replyLibrary=(Array.isArray(s.replyLibrary)?s.replyLibrary:[]).filter(r=>r&&r.active!==false&&(CALL_CENTER_RESULT_IDS.includes(String(r.id||''))||r.teamVisible||r.callCenterAllowed));
  }
  delete s.formula;
  s.weeklyReports=Array.isArray(s.weeklyReports)?s.weeklyReports.filter(r=>r&&String(r.memberId||'')===String(window.HIPPO_AUTH?.team_member_id||'')):[];
  s.weeks=(Array.isArray(s.weeks)?s.weeks:[]).map(w=>{const x={...w};x.outputs=[];x.metrics=[];delete x.notes;return x});
  return s;
}
function recoveryEnvelope(){return {_hippo_recovery:{user_id:Number(window.HIPPO_AUTH?.id||0),role:String(window.HIPPO_AUTH?.role||''),permission_fingerprint:String(window.HIPPO_AUTH?.permission_fingerprint||''),scope_version:String(window.HIPPO_AUTH?.scope_version||CACHE_SCOPE_VERSION)},data:sanitizeStateForCurrentScope(state)}}
let state=load(),selectedWeek=state.project.activeWeek||1,currentPage='dashboard',selectedReasonIds=[];

/* ===== سینک امن با سرور — V01 ===== */
let SERVER_ROLE=window.HIPPO_AUTH?.role||null,SERVER_NAME=window.HIPPO_AUTH?.display_name||null,SERVER_REVISION=0,SERVER_STATE_LOADED=false,STATE_CONTEXT_TOKEN='';
function canPermission(key){return !!window.HIPPO_AUTH?.permissions?.[key]}
function csrfHeaders(extra={}){return {'X-CSRF-Token':window.HIPPO_AUTH?.csrf_token||'',...extra}}
let _serverSaveTimer=null,_serverSaveInFlight=false,_serverSaveQueued=false,SYNC_CONFLICT=false;

function setSyncStatus(kind,text,title=''){
  const el=document.getElementById('syncStatus');if(!el)return;
  el.className='sync-status '+kind;el.textContent=text;el.title=title||text;
}
function showSyncAlert(type,title,text){
  const box=document.getElementById('syncAlert');if(!box)return;
  box.classList.add('show');document.getElementById('syncAlertTitle').textContent=title;document.getElementById('syncAlertText').textContent=text;
  document.getElementById('syncReloadBtn').style.display=type==='conflict'?'inline-flex':'none';
  document.getElementById('syncRetryBtn').style.display=type==='conflict'?'none':'inline-flex';
}
function hideSyncAlert(){const box=document.getElementById('syncAlert');if(box)box.classList.remove('show')}
function persistLocalState(){
  state=sanitizeStateForCurrentScope(state);const raw=JSON.stringify(state);try{localStorage.setItem(STORAGE_KEY,raw)}catch(e){}if(_idb)idbSet(raw);return raw;
}
function applyRoleUI(){
  document.body.dataset.role=SERVER_ROLE||'';
  document.querySelectorAll('[data-permission]').forEach(el=>el.classList.toggle('v05-role-hidden',!canPermission(el.dataset.permission)));
}

async function serverLoadState(forceServer=false){
  setSyncStatus('saving','در حال همگام‌سازی');
  try{
    const r=await fetch('api.php?action=state',{credentials:'same-origin',cache:'no-store'});
    if(r.status===401){location.href='login.php';return}
    if(r.status===403){location.href='manager.php';return}
    const j=await r.json();
    if(!r.ok||!j||!j.ok)throw new Error('load_failed');
    const nextUser=j.user||null;if(nextUser){await switchScopedCache(nextUser);window.HIPPO_AUTH={...window.HIPPO_AUTH,...nextUser,csrf_token:nextUser.csrf_token||window.HIPPO_AUTH.csrf_token};}SERVER_ROLE=nextUser?.role||j.role;SERVER_NAME=nextUser?.display_name||j.display_name;SERVER_REVISION=Number(j.revision||0);STATE_CONTEXT_TOKEN=String(j.state_context_token||'');SERVER_STATE_LOADED=true;SYNC_CONFLICT=false;
    if(j.data&&j.data!=='{}'){
      const serverState=JSON.parse(j.data),serverRaw=JSON.stringify(serverState),localRaw=JSON.stringify(state);
      if(serverRaw!==localRaw){
        if(!forceServer){try{localStorage.setItem(RECOVERY_KEY,localRaw)}catch(e){}}
        state=sanitizeStateForCurrentScope(mergeState(serverState,true));selectedWeek=state.project.activeWeek||1;
      }
      persistLocalState();
    }else{
      // دیتابیس خالی است؛ نسخه محلی با Revision صفر به‌عنوان اولین State ارسال می‌شود.
      _serverSaveQueued=true;
    }
    applyRoleUI();hideSyncAlert();
    setSyncStatus('saved','همگام · نسخه '+SERVER_REVISION,'آخرین نسخه تأییدشده سرور');
  }catch(e){
    SERVER_STATE_LOADED=false;
    setSyncStatus('error','فقط مرورگر','ارتباط با سرور برقرار نشد');
    showSyncAlert('error','اتصال به سرور برقرار نشد','تغییرات در مرورگر محفوظ است؛ برای ارسال دوباره روی «تلاش دوباره» بزنید.');
  }
  renderAll();
  initDurableStorage();
  if(_serverSaveQueued&&!SYNC_CONFLICT)serverSaveState(80);
}
function serverSaveState(delay=600){
  if(!canPermission('state.save_full')&&!canPermission('customers.edit_all')&&!canPermission('customers.edit_own')&&!canPermission('interactions.create')&&!canPermission('tasks.create_personal'))return;
  _serverSaveQueued=true;clearTimeout(_serverSaveTimer);
  _serverSaveTimer=setTimeout(flushServerSave,delay);
  setSyncStatus('saving','در انتظار ذخیره');
}
async function flushServerSave(){
  if(SYNC_CONFLICT||(!canPermission('state.save_full')&&!canPermission('customers.edit_all')&&!canPermission('customers.edit_own')&&!canPermission('interactions.create')&&!canPermission('tasks.create_personal')))return;
  if(_serverSaveInFlight){_serverSaveQueued=true;return}
  _serverSaveInFlight=true;_serverSaveQueued=false;let completed=false;
  const snapshot=JSON.parse(JSON.stringify(state)),expectedRevision=SERVER_REVISION;
  setSyncStatus('saving','در حال ذخیره','ارسال تغییرات به سرور');
  try{
    const r=await fetch('api.php?action=save',{method:'POST',credentials:'same-origin',headers:csrfHeaders({'Content-Type':'application/json'}),body:JSON.stringify({data:snapshot,expected_revision:expectedRevision,state_context_token:STATE_CONTEXT_TOKEN})});
    if(r.status===401){location.href='login.php';return}
    const j=await r.json().catch(()=>null);
    if(r.status===409){
      SYNC_CONFLICT=true;SERVER_REVISION=Number(j&&j.current_revision||SERVER_REVISION);
      setSyncStatus('conflict','تعارض نسخه','نسخه دیگری روی سرور ذخیره شده است');
      showSyncAlert('conflict','تعارض ذخیره‌سازی','نسخه سرور تغییر کرده است. ابتدا نسخه مرورگر را دانلود کنید، سپس نسخه سرور را بارگذاری کنید.');
      return;
    }
    if(!r.ok||!j||!j.ok)throw new Error((j&&j.error)||'save_failed');
    SERVER_REVISION=Number(j.revision??expectedRevision);STATE_CONTEXT_TOKEN=String(j.state_context_token||STATE_CONTEXT_TOKEN);SERVER_STATE_LOADED=true;completed=true;
    if(j.reload_required){await serverLoadState(true);}
    hideSyncAlert();setSyncStatus('saved','ذخیره شد · نسخه '+SERVER_REVISION,'آخرین ذخیره تأییدشده توسط سرور');
  }catch(e){
    _serverSaveQueued=true;
    setSyncStatus('error','خطای ذخیره','نسخه مرورگر محفوظ است');
    showSyncAlert('error','ذخیره روی سرور انجام نشد','نسخه مرورگر محفوظ است و با تلاش دوباره می‌توانید آن را ارسال کنید.');
  }finally{
    _serverSaveInFlight=false;
    if(completed&&_serverSaveQueued&&!SYNC_CONFLICT)serverSaveState(50);
  }
}
function save(){
  state.project.lastSaved=new Date().toLocaleString('fa-IR');state.project.savedAt=Date.now();persistLocalState();updateBadges();serverSaveState();
}
function saveNow(){save();toast('در مرورگر ذخیره شد؛ ارسال به سرور در حال انجام است')}
function retryServerSave(){
  if(SYNC_CONFLICT)return resolveConflictReload();
  hideSyncAlert();serverSaveState(0);
}
function downloadLocalRecovery(){
  const blob=new Blob([JSON.stringify(recoveryEnvelope(),null,2)],{type:'application/json;charset=utf-8'}),a=document.createElement('a');
  a.href=URL.createObjectURL(blob);a.download=`sales-assistant-local-recovery-${new Date().toISOString().slice(0,19).replace(/:/g,'-')}.json`;a.click();URL.revokeObjectURL(a.href);
  toast('نسخه مرورگر دانلود شد');
}
async function resolveConflictReload(){
  if(!confirm('نسخه سرور جایگزین تغییرات فعلی مرورگر شود؟ بهتر است ابتدا نسخه مرورگر را دانلود کنید.'))return;
  SYNC_CONFLICT=false;_serverSaveQueued=false;hideSyncAlert();await serverLoadState(true);toast('نسخه سرور بارگذاری شد');
}
function backupOperationLabel(op){return ({save:'ذخیره کاربر',manager_task:'ثبت کار مدیر',restore:'بازیابی نسخه',pre_restore:'نسخه قبل از بازیابی'})[op]||op}
async function openServerBackups(){
  const mc=document.getElementById('modalContent');mc.innerHTML='<h2 style="margin-top:0">نسخه‌های سرور</h2><p class="muted">در حال دریافت فهرست نسخه‌ها…</p>';document.getElementById('modal').classList.add('open');
  try{
    const r=await fetch('api.php?action=backups',{credentials:'same-origin',cache:'no-store'}),j=await r.json();
    if(!r.ok||!j.ok)throw new Error('backup_list_failed');
    const canRestore=canPermission('backups.restore')&&canPermission('state.save_full');
    const rows=(j.backups||[]).map(b=>`<div class="backup-row"><div><b>نسخه ${fmt(b.revision)} · ${esc(backupOperationLabel(b.operation))}</b><span>${esc(faDateTime(b.saved_at))} · ${esc(b.saved_by||'نامشخص')}</span></div>${canRestore?`<button class="btn small warn" onclick="restoreServerBackup(${Number(b.id)})">بازیابی</button>`:'<span class="badge">فقط مشاهده</span>'}</div>`).join('');
    mc.innerHTML=`<div class="section-head" style="margin-top:0"><div><h2>نسخه‌های سرور</h2><p>حداکثر ۲۰ نسخه اخیر؛ Revision فعلی ${fmt(SERVER_REVISION)}</p></div><button class="btn small" onclick="closeModal()">بستن</button></div>${rows?`<div class="backup-list">${rows}</div>`:emptyState('نسخه‌ای ثبت نشده','بعد از اولین ذخیره، نسخه‌های سرور اینجا نمایش داده می‌شوند.')}`;
  }catch(e){mc.innerHTML='<h2 style="margin-top:0">نسخه‌های سرور</h2><div class="alert danger">دریافت نسخه‌ها انجام نشد. اتصال سرور و Permission را بررسی کنید.</div><button class="btn" onclick="closeModal()">بستن</button>'}
}
async function restoreServerBackup(id){
  if(!(canPermission('backups.restore')&&canPermission('state.save_full')))return toast('مجوز بازیابی نسخه را ندارید');
  if(!confirm('این نسخه روی سرور بازیابی شود؟ وضعیت فعلی نیز پیش از بازیابی در Backup نگه داشته می‌شود.'))return;
  setSyncStatus('saving','در حال بازیابی');
  try{
    const r=await fetch('api.php?action=restore',{method:'POST',credentials:'same-origin',headers:csrfHeaders({'Content-Type':'application/json'}),body:JSON.stringify({backup_id:id,expected_revision:SERVER_REVISION})}),j=await r.json().catch(()=>null);
    if(r.status===409){SYNC_CONFLICT=true;SERVER_REVISION=Number(j&&j.current_revision||SERVER_REVISION);closeModal();setSyncStatus('conflict','تعارض نسخه');showSyncAlert('conflict','بازیابی متوقف شد','پیش از بازیابی، نسخه سرور توسط کاربر دیگری تغییر کرده است.');return}
    if(!r.ok||!j||!j.ok)throw new Error('restore_failed');
    SERVER_REVISION=Number(j.revision||SERVER_REVISION+1);STATE_CONTEXT_TOKEN=String(j.state_context_token||'');SYNC_CONFLICT=false;closeModal();await serverLoadState(true);hideSyncAlert();setSyncStatus('saved','بازیابی شد · نسخه '+SERVER_REVISION);toast('نسخه انتخاب‌شده بازیابی شد');
  }catch(e){setSyncStatus('error','خطای بازیابی');toast('بازیابی انجام نشد')}
}
function esc(v){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]))}
function todayISO(){const d=new Date();return d.toISOString().slice(0,10)}
function addDaysISO(days){const d=new Date();d.setDate(d.getDate()+days);return d.toISOString().slice(0,10)}
function faDate(v){if(!v)return 'ثبت نشده';const d=new Date(v.length===10?v+'T12:00:00':v);return isNaN(d)?v:d.toLocaleDateString('fa-IR-u-ca-persian')}
/* ===== انتخابگر تاریخ شمسی (روز/ماه/سال) — مقدار واقعی همیشه ISO میلادی می‌ماند، فقط نمایش فارسی است ===== */
const JALALI_MONTHS=['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
const JCUM=[0,31,62,93,124,155,186,216,246,276,306,336];
const _jFmt=new Intl.DateTimeFormat('fa-IR-u-ca-persian',{numberingSystem:'latn',year:'numeric',month:'2-digit',day:'2-digit',timeZone:'UTC'});
function gregorianToJalali(d){const parts=_jFmt.formatToParts(d);const o={};parts.forEach(p=>o[p.type]=p.value);return {jy:+o.year,jm:+o.month,jd:+o.day}}
const _jNyCache={};
function jalaliNewYearUTC(jy){if(_jNyCache[jy])return _jNyCache[jy];let g=new Date(Date.UTC(jy+621,2,19));for(let i=0;i<8;i++){const p=gregorianToJalali(g);if(p.jy===jy&&p.jm===1&&p.jd===1){_jNyCache[jy]=g;return g}g=new Date(g.getTime()+86400000)}throw new Error('nowruz not found')}
function jalaliYearLength(jy){return Math.round((jalaliNewYearUTC(jy+1).getTime()-jalaliNewYearUTC(jy).getTime())/86400000)}
function jalaliMonthLength(jy,jm){if(jm<=6)return 31;if(jm<=11)return 30;return jalaliYearLength(jy)-336}
function jalaliToISO(jy,jm,jd){const anchor=jalaliNewYearUTC(jy);const offset=JCUM[jm-1]+jd-1;return new Date(anchor.getTime()+offset*86400000).toISOString().slice(0,10)}
function isoToJalali(iso){if(!iso)return gregorianToJalali(new Date(todayISO()+'T00:00:00Z'));return gregorianToJalali(new Date(iso+'T00:00:00Z'))}
/* optional=true یعنی فیلد می‌تواند «تعیین‌نشده» (مقدار خالی) بماند — برای پیگیری/تولید/تحویل که پیش‌فرض خالی‌اند. afterJs بعد از هر تغییر اجرا می‌شود (مثلاً ذخیره‌ی مستقیم روی state). */
function jdatePickerHtml(id,isoValue,optional,afterJs){const hasValue=!!isoValue;const iso=isoValue||todayISO();const j=isoToJalali(iso);const nowY=isoToJalali(todayISO()).jy;const years=[];const minY=Math.min(nowY-20,j.jy),maxY=Math.max(nowY+5,j.jy);for(let y=minY;y<=maxY;y++)years.push(y);const len=jalaliMonthLength(j.jy,j.jm);const days=Array.from({length:len},(_,i)=>i+1);const after=afterJs?';'+afterJs:'';const rowHtml=`<div class="jdate-row" id="${id}_row" style="${optional&&!hasValue?'display:none':''}"><select id="${id}_d" aria-label="روز" title="روز" onchange="jdateSync('${id}')${after}">${days.map(d=>`<option value="${d}" ${d===j.jd?'selected':''}>${d}</option>`).join('')}</select><select id="${id}_m" aria-label="ماه" title="ماه" onchange="jdateSync('${id}')${after}">${JALALI_MONTHS.map((mn,i)=>`<option value="${i+1}" ${i+1===j.jm?'selected':''}>${mn}</option>`).join('')}</select><select id="${id}_y" aria-label="سال" title="سال" onchange="jdateSync('${id}')${after}">${years.map(y=>`<option value="${y}" ${y===j.jy?'selected':''}>${y}</option>`).join('')}</select></div>`;const hiddenHtml=`<input type="hidden" id="${id}" value="${esc(hasValue?iso:'')}">`;if(!optional)return rowHtml+hiddenHtml;return `<label class="jdate-toggle"><input type="checkbox" id="${id}_chk" ${hasValue?'checked':''} onchange="jdateToggle('${id}')${after}"> <span>تعیین شود</span></label>${rowHtml}${hiddenHtml}`}
function jdateSync(id){const y=+document.getElementById(id+'_y').value,m=+document.getElementById(id+'_m').value;const dSel=document.getElementById(id+'_d');const len=jalaliMonthLength(y,m);const curD=Math.min(+dSel.value||1,len);if(dSel.options.length!==len)dSel.innerHTML=Array.from({length:len},(_,i)=>i+1).map(d=>`<option value="${d}" ${d===curD?'selected':''}>${d}</option>`).join('');else dSel.value=curD;document.getElementById(id).value=jalaliToISO(y,m,curD)}
function jdateToggle(id){const chk=document.getElementById(id+'_chk').checked;document.getElementById(id+'_row').style.display=chk?'':'none';if(chk)jdateSync(id);else document.getElementById(id).value=''}
function faDateTime(v){if(!v)return '';const d=new Date(v);return isNaN(d)?v:d.toLocaleString('fa-IR-u-ca-persian',{dateStyle:'short',timeStyle:'short'})}
function fmt(n){return Number(n||0).toLocaleString('fa-IR',{maximumFractionDigits:2})}
function digitsOnly(v){return String(v||'').replace(/[^\d]/g,'')}
function fmtThousands(n){return n?Number(n).toLocaleString('en-US'):''}
function onMoneyInput(id){const el=document.getElementById(id);el.value=fmtThousands(digitsOnly(el.value));recalcQuickValue()}
function recalcQuickValue(){const vol=+document.getElementById('quickVolume').value||0;const price=+digitsOnly(document.getElementById('quickPrice').value)||0;document.getElementById('quickValue').value=fmtThousands(vol*price)}
function toast(msg){const t=document.getElementById('toast');t.textContent=msg;t.classList.add('show');clearTimeout(window.__toast);window.__toast=setTimeout(()=>t.classList.remove('show'),1800)}
function currentMember(){const id=window.HIPPO_AUTH?.team_member_id||state.project.currentMemberId;return state.team.find(m=>m.id===id)||state.team[0]}
function roleLabel(id){return ROLE_OPTIONS.find(x=>x[0]===id)?.[1]||id}
function canViewAll(){return canPermission('customers.view_all')}
function memberName(id){if(!id)return 'بدون مسئول';return state.team.find(m=>m.id===id)?.name||String(id)}
function accessRank(level){return ({view:1,call:2,edit:3})[String(level||'')]||0}
function customerAccessLevel(c){if(!c)return '';if(String(window.HIPPO_AUTH?.role)==='manager'&&canPermission('state.view_full'))return 'edit';return String(c._accessLevel||'view')}
/* سطح دسترسی مشتری سقف قطعی عملیات است؛ View هرگز به Call ارتقا پیدا نمی‌کند. */
function interactionAccessLevel(c){return customerAccessLevel(c)}
function canManageOrders(){return String(window.HIPPO_AUTH?.role)==='manager'&&canPermission('state.view_full')&&canPermission('state.save_full')}
function isCallCenterRole(){return String(window.HIPPO_AUTH?.role)==='center_call'}
function canManageReplyOptions(){return String(window.HIPPO_AUTH?.role)==='manager'||String(currentMember()?.access||'')==='sales_manager'}
function replyOptionsManageButton(){return canManageReplyOptions()?`<button type="button" class="reply-option-add" onclick="openReplyOptionModal()" title="افزودن گزینه برای کل تیم" aria-label="افزودن گزینه نتیجه تماس">＋</button>`:''}
function openReplyOptionModal(){
  if(!canManageReplyOptions())return toast('فقط مدیر یا مدیر فروش می‌تواند گزینه جدید اضافه کند');
  openModal(`<div class="modal-head"><h3>افزودن گزینه نتیجه تماس</h3><button class="icon-btn" onclick="closeModal()">×</button></div><div class="modal-grid"><div class="field full"><label>عنوان گزینه *</label><input id="newReplyOptionLabel" maxlength="100" placeholder="مثال: مشتری درخواست تماس کارشناس فروش دارد"></div><div class="field full"><label>دسته‌بندی</label><input id="newReplyOptionCategory" maxlength="60" value="سفارشی"></div><div class="alert info full"><b>نمایش برای کل تیم</b><div class="mini">این گزینه برای مرکز تماس و اعضای فروش نمایش داده می‌شود. انتخاب مرکز تماس به‌صورت خودکار به وظایف بازاریاب مسئول مشتری ارجاع می‌شود.</div></div><div class="full"><button class="btn primary" onclick="createReplyOption()">افزودن برای کل تیم</button></div></div>`)
}
async function createReplyOption(){
  const label=document.getElementById('newReplyOptionLabel')?.value.trim()||'',category=document.getElementById('newReplyOptionCategory')?.value.trim()||'سفارشی';
  if(label.length<2)return toast('عنوان گزینه را وارد کنید');
  const btn=document.querySelector('.modal .btn.primary');if(btn){btn.disabled=true;btn.textContent='در حال ذخیره...'}
  try{
    const r=await fetch('api.php?action=reply_option_create',{method:'POST',credentials:'same-origin',headers:csrfHeaders({'Content-Type':'application/json'}),body:JSON.stringify({label,category,expected_revision:SERVER_REVISION})});
    const j=await r.json().catch(()=>null);
    if(r.status===409&&j?.error==='revision_conflict'){SERVER_REVISION=Number(j.current_revision||SERVER_REVISION);closeModal();await serverLoadState(true);toast('اطلاعات تازه شد؛ دوباره گزینه را اضافه کنید');return}
    if(!r.ok||!j?.ok){const msg=j?.error==='reply_option_exists'?'این گزینه قبلاً ثبت شده است':j?.error==='reply_option_manage_forbidden'?'فقط مدیر یا مدیر فروش مجاز است':'ذخیره گزینه انجام نشد';throw new Error(msg)}
    SERVER_REVISION=Number(j.revision||SERVER_REVISION+1);closeModal();await serverLoadState(true);showPage('activities');toast('گزینه برای کل تیم اضافه شد');
  }catch(e){toast(e?.message||'ذخیره گزینه انجام نشد');if(btn){btn.disabled=false;btn.textContent='افزودن برای کل تیم'}}
}
function stageObj(id){return STAGES.find(s=>s.id===id)||STAGES[0]}
function customerById(id){return state.customers.find(c=>c.id===id)}
function replyById(id){return state.replyLibrary.find(r=>r.id===id)}
/* یک تعامل ممکن است چند دلیل هم‌زمان داشته باشد؛ داده‌ی قدیمی‌تر فقط resultId تکی دارد. */
function interactionResultIds(i){return Array.isArray(i.resultIds)?i.resultIds:(i.resultId?[i.resultId]:[])}
function interactionReplies(i){return interactionResultIds(i).map(id=>replyById(id)).filter(Boolean)}
/* مرحله‌ی بعدی مشتری از پیشرفته‌ترین دلیل انتخاب‌شده می‌آید؛ paused فقط وقتی برنده می‌شود که هیچ دلیل دیگری همراهش نباشد. */
function pickStage(reasons){
  if(!reasons.length)return null;
  const order=STAGES.map(s=>s.id);
  const nonPaused=reasons.filter(r=>r.stage!=='paused');
  const pool=nonPaused.length?nonPaused:reasons;
  return pool.reduce((best,r)=>order.indexOf(r.stage)>order.indexOf(best)?r.stage:best,pool[0].stage);
}
function customerScore(c){return normalizedScore(c)}
function normalizedScore(c){const x=c.score||{};return Math.min(100,Math.max(20,((+x.fit||1)+(+x.volume||1)+(+x.decision||1)+(+x.urgency||1)+(+x.payment||1))*4))}
/* هر مشتری تازه (دستی یا اکسل) با پروفایل امتیاز پیش‌فرض ساخته می‌شود؛ تا وقتی خودت واقعاً عوضش نکنی، امتیاز عدد واقعی نیست. */
const DEFAULT_SCORE={fit:3,volume:3,decision:2,urgency:2,payment:2};
function isScoreReviewed(c){const s=c.score||{};return +s.fit!==DEFAULT_SCORE.fit||+s.volume!==DEFAULT_SCORE.volume||+s.decision!==DEFAULT_SCORE.decision||+s.urgency!==DEFAULT_SCORE.urgency||+s.payment!==DEFAULT_SCORE.payment}
function visibleCustomers(){return state.customers}
function visibleInteractions(){return state.interactions}
function taskCounts(){const ts=state.weeks.flatMap(w=>w.tasks);return {all:ts.length,done:ts.filter(t=>t.status==='done').length,doing:ts.filter(t=>t.status==='in_progress').length,blocked:ts.filter(t=>t.status==='blocked').length}}
function weekProgress(w){const all=w.tasks.length+w.outputs.length;if(!all)return 0;const done=w.tasks.filter(t=>t.status==='done').length+w.outputs.filter(o=>o.done).length;return Math.round(done/all*100)}
function overallProgress(){const arr=state.weeks.map(weekProgress);return Math.round(arr.reduce((a,b)=>a+b,0/Math.max(1,arr.length))/Math.max(1,arr.length))}
function taskStatusLabel(s){return {not_started:'شروع نشده',in_progress:'در حال انجام',done:'انجام‌شده',blocked:'متوقف/مانع',decision:'نیازمند تصمیم'}[s]||s}
function taskStatusClass(s){return s==='done'?'ok':s==='blocked'?'danger':s==='decision'?'warn':s==='in_progress'?'blue':''}
function dueCustomers(){const t=todayISO();return visibleCustomers().filter(c=>c.nextFollowUp&&c.stage!=='won'&&c.stage!=='paused'&&c.nextFollowUp<=t)}
function upcomingCustomers(){const t=todayISO(),n=addDaysISO(7);return visibleCustomers().filter(c=>c.nextFollowUp&&c.nextFollowUp>t&&c.nextFollowUp<=n)}
function customerLastInteraction(id){return state.interactions.filter(i=>i.customerId===id).sort((a,b)=>String(b.date).localeCompare(String(a.date)))[0]}
function isOrderInteraction(i){return interactionResultIds(i).some(id=>ORDER_RESULT_IDS.includes(id))}
function orderInteractions(){return canManageOrders()?visibleInteractions().filter(isOrderInteraction):[]}
function orderStatus(i){const f=i.fulfillment;if(!f||!f.production?.date)return {key:'need_production',label:'در انتظار تولید',color:'warn'};if(!f.delivery?.date)return {key:'need_delivery',label:'تولید شد، منتظر تحویل',color:'blue'};if(!f.outcome?.type)return {key:'need_outcome',label:'تحویل شد، منتظر نتیجه',color:'blue'};if(f.outcome.type==='ok')return {key:'ok',label:'تکمیل شد',color:'ok'};return {key:f.outcome.type,label:OUTCOME_TYPES[f.outcome.type]?.label||'نامشخص',color:'danger'}}
/* خلاصه‌ی سفارش‌های یک مشتری خاص، به ترتیب زمان — برای دیدن یکجا کجای مسیرش گیر کرده. */
function customerOrders(customerId){return state.interactions.filter(i=>i.customerId===customerId&&isOrderInteraction(i)).sort((a,b)=>String(a.date).localeCompare(String(b.date)))}
const CHURN_RISK_DAYS=45;
/* میانگین فاصله‌ی روز بین سفارش‌های قبلی همین مشتری — محاسبه‌ی مستقیم از تاریخ سفارش‌ها، بدون نیاز به AI. */
function customerAvgOrderInterval(customerId){const orders=customerOrders(customerId);if(orders.length<2)return null;const gaps=[];for(let i=1;i<orders.length;i++){const g=(new Date(orders[i].date).getTime()-new Date(orders[i-1].date).getTime())/86400000;if(g>0)gaps.push(g)}return gaps.length?Math.round(gaps.reduce((a,b)=>a+b,0)/gaps.length):null}
/* اگر ≥۲ سفارش موفق داشته باشد از میانگین شخصی‌اش استفاده می‌شود، وگرنه از فاصله‌ی ثابت پیش‌فرض. level=soon یعنی ۸۰٪ فاصله رد شده (یادآوری ملایم)، overdue یعنی از کل فاصله رد شده. */
function customerReorderSignal(customerId){const orders=customerOrders(customerId);if(!orders.length)return null;const last=orders[orders.length-1];if(last.fulfillment?.outcome?.type!=='ok')return null;const days=Math.floor((Date.now()-new Date(last.date).getTime())/86400000);const avg=customerAvgOrderInterval(customerId);const threshold=avg||CHURN_RISK_DAYS;if(days>=threshold)return {level:'overdue',days,avg};if(avg&&days>=Math.round(avg*0.8))return {level:'soon',days,avg};return null}
/* «در خطر ریزش» یعنی: آخرین سفارش ایراد داشت/تکرار نشد، یا طبق سیگنال بالا از موعد سفارش بعدی رد شده. */
function customerChurnRisk(customerId){const orders=customerOrders(customerId);if(!orders.length)return null;const last=orders[orders.length-1];const lastOutcome=last.fulfillment?.outcome?.type;if(lastOutcome==='complaint'||lastOutcome==='no_repeat')return {reason:'آخرین سفارش «'+(OUTCOME_TYPES[lastOutcome]?.label||'')+'» بود'};const signal=customerReorderSignal(customerId);if(signal&&signal.level==='overdue')return {reason:signal.avg?('میانگین فاصله‌ی سفارش‌هاش '+fmt(signal.avg)+' روزه؛ '+fmt(signal.days)+' روز از آخرین سفارش گذشته و تکرار نشده'):('بیش از '+fmt(CHURN_RISK_DAYS)+' روز از آخرین سفارش موفق گذشته و سفارش جدیدی ثبت نشده')};return null}
function reorderReminderCustomers(){return visibleCustomers().map(c=>({c,signal:customerReorderSignal(c.id)})).filter(x=>x.signal).sort((a,b)=>{const rank={overdue:0,soon:1};if(rank[a.signal.level]!==rank[b.signal.level])return rank[a.signal.level]-rank[b.signal.level];return b.signal.days-a.signal.days})}
function reorderReminderSectionHtml(){const list=reorderReminderCustomers();const anyOverdue=list.some(x=>x.signal.level==='overdue');const rows=list.length?list.map(({c,signal})=>{const klass=signal.level==='overdue'?'danger':'warn';const cadence=signal.avg?('میانگین دوره‌ش '+fmt(signal.avg)+' روزه؛ '):('داده‌ی کافی برای میانگین شخصی نیست؛ فاصله‌ی پیش‌فرض '+fmt(CHURN_RISK_DAYS)+' روز؛ ');const statusText=signal.level==='overdue'?'از موعد سفارش بعدی رد شده':'موعد سفارش بعدی نزدیک است';return `<div class="alert ${klass}"><div style="display:flex;justify-content:space-between;gap:8px;align-items:center;flex-wrap:wrap"><div class="clickable" onclick="openCustomerDetail('${c.id}')"><b>${esc(c.name)}</b><div class="mini">${cadence}${fmt(signal.days)} روز از آخرین سفارش — ${statusText}</div></div><button class="btn small primary" onclick="prefillQuick('${c.id}')">ثبت نتیجه</button></div></div>`}).join(''):emptyState('یادآوری تکرار خریدی نیست','بعد از اولین سفارش موفق هر مشتری، اینجا خودکار پر می‌شود.');return `<div class="card" style="margin-top:14px"><h3 style="margin-top:0">یادآوری تکرار خرید <span class="badge ${anyOverdue?'danger':'warn'}">${fmt(list.length)}</span></h3><p class="mini muted" style="margin-top:-4px">بر اساس میانگین فاصله‌ی سفارش‌های قبلی هر مشتری؛ اگر تاریخچه کافی نباشد، فاصله‌ی پیش‌فرض ${fmt(CHURN_RISK_DAYS)} روز.</p>${rows}</div>`}
const ORDINAL_FA=['اول','دوم','سوم','چهارم','پنجم','ششم','هفتم','هشتم','نهم','دهم','یازدهم','دوازدهم'];
function ordinalLabel(n){return n<=ORDINAL_FA.length?'دور '+ORDINAL_FA[n-1]:'دور '+fmt(n)+'ام'}
/* زنجیره‌ی بصری دورهای خرید — کاملاً خودکار از داده‌ی سفارش‌های واقعی؛ هیچ کلیک دستی «رفتن به مرحله بعد» لازم نیست. اگر از موعد دور بعدی رد شده باشد، یک گره «منتظر/گیرکرده» ته زنجیره اضافه می‌شود. */
function customerRoundChainHtml(c){
  const orders=customerOrders(c.id);
  if(!orders.length)return '';
  const nodes=orders.map((i,idx)=>{
    const s=orderStatus(i);
    const cls=s.key==='ok'?'done':(s.key==='complaint'||s.key==='no_repeat')?'danger':'warn';
    const mark=cls==='done'?'✓':(idx+1);
    return `<div class="round-node ${cls}"><div class="rline"></div><div class="rdot" onclick="openFulfillmentModal('${i.id}')" title="${esc(s.label)}">${mark}</div><div class="rlabel">${ordinalLabel(idx+1)}</div><div class="rdate">${faDate(i.date)}</div></div>`;
  });
  const signal=customerReorderSignal(c.id);
  if(signal){
    const cls=signal.level==='overdue'?'danger':'warn';
    const label=signal.level==='overdue'?'گیر کرده':'نزدیک موعد';
    nodes.push(`<div class="round-node ${cls} pending"><div class="rline"></div><div class="rdot">${fmt(signal.days)}</div><div class="rlabel">${label}</div><div class="rdate">روز گذشته</div></div>`);
  }
  return `<div class="round-chain">${nodes.join('')}</div>`;
}
function customerOrdersSummaryHtml(c){const orders=customerOrders(c.id);if(!orders.length)return '';const risk=customerChurnRisk(c.id);return `<div class="card flat" style="margin-bottom:12px;padding:13px;border:1px solid var(--line)"><div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px"><b style="font-size:12px">مسیر خرید (${fmt(orders.length)} دور)</b>${risk?`<span class="badge danger">⚠ در خطر ریزش</span>`:''}</div>${risk?`<div class="alert danger" style="margin-top:8px">${esc(risk.reason)}</div>`:''}${customerRoundChainHtml(c)}</div>`}
function setActiveWeek(n){state.project.activeWeek=n;selectedWeek=n;save();renderAll();toast(`هفته ${n} فعال شد`)}
function selectWeek(n){selectedWeek=n;renderWeeks()}
function changeCurrentUser(id){state.project.currentMemberId=id;save();renderAll()}
function showPage(name,el){
  const aliases={quick:'activities',followups:'work',mytasks:'work',analysis:'reports'};name=aliases[name]||name;currentPage=name;
  document.querySelectorAll('.page').forEach(p=>p.classList.toggle('active',p.id===`page-${name}`));
  document.querySelectorAll('[data-page]').forEach(b=>b.classList.toggle('active',b.dataset.page===name));
  window.scrollTo({top:0,behavior:'smooth'});
  const renderers={dashboard:renderDashboard,customers:renderCustomers,activities:renderActivities,work:renderWork,pipeline:renderPipelineV03,operations:renderOperationsV03,reports:renderReportsV03,ai:renderAIV03,weeks:renderWeeks,formula:renderFormula,settings:renderSettings};renderers[name]?.();
}
function renderAll(){renderDashboard();renderCustomers();renderActivities();renderWork();renderPipelineV03();renderOperationsV03();renderReportsV03();renderAIV03();renderWeeks();renderFormula();renderSettings();updateBadges()}
/* سوییچ نما حذف شده؛ تابع برای سازگاری می‌ماند ولی اگر عنصر نبود بی‌صدا رد می‌شود. */
function renderUserSwitch(){const s=document.getElementById('currentUserSelect');if(!s)return;s.innerHTML=state.team.map(m=>`<option value="${m.id}" ${m.id===state.project.currentMemberId?'selected':''}>${esc(m.name)}</option>`).join('');const b=document.getElementById('currentRoleBadge');if(b)b.textContent=roleLabel(currentMember()?.access)}
function updateBadges(){const mt=state.weeks.flatMap(w=>w.tasks).filter(t=>canViewAll()||t.assignee===currentMember()?.id).filter(t=>t.status!=='done').length;const f=dueCustomers().length;const total=mt+f;['navTaskBadge','navFollowBadge','navWorkBadge'].forEach(id=>{const el=document.getElementById(id);if(el)el.textContent=fmt(total)});}
function stageBadge(stage){const s=stageObj(stage);return `<span class="badge ${s.color}">${s.label}</span>`}
function emptyState(title,text,button=''){return `<div class="empty"><b>${title}</b><span>${text}</span>${button?`<div style="margin-top:12px">${button}</div>`:''}</div>`}
function topObstacle(interactions){const counts={};interactions.forEach(i=>{interactionReplies(i).forEach(r=>{counts[r.category]=(counts[r.category]||0)+1})});const x=Object.entries(counts).sort((a,b)=>b[1]-a[1])[0];return x?{label:x[0],count:x[1]}:{label:'داده کافی نیست',count:0}}
function renderActivities(){renderQuick();const target=document.getElementById('page-activities'),source=document.getElementById('page-quick');target.innerHTML=`<div class="page-heading"><div><h1>مذاکرات و فعالیت‌ها</h1><p>نتیجه تماس، جلسه یا پیام را سریع ثبت کنید؛ تاریخچه مشتری در پرونده او باقی می‌ماند.</p></div></div>${source.innerHTML}`;}
function renderWork(){renderMyTasks();renderFollowups();const tasks=document.getElementById('page-mytasks').innerHTML,follow=document.getElementById('page-followups').innerHTML;document.getElementById('page-work').innerHTML=`<div class="page-heading"><div><h1>پیگیری‌ها و وظایف</h1><p>کارهای امروز و مشتریان نیازمند پیگیری در یک فضای کاری قرار گرفته‌اند.</p></div></div><div class="tabs" aria-label="نمای کارها"><button class="tab active" type="button">همه کارها</button><button class="tab" type="button" onclick="document.getElementById('followupAnchor').scrollIntoView({behavior:'smooth'})">پیگیری مشتریان</button></div><div class="workspace-grid" style="margin-top:16px"><div class="workspace-panel">${tasks}</div><div id="followupAnchor" class="workspace-panel">${follow}</div></div>`;}
function renderPipelineV03(){const cs=visibleCustomers();document.getElementById('page-pipeline').innerHTML=`<div class="page-heading"><div><h1>قیف فروش</h1><p>مشتریان را براساس مرحله فروش ببینید؛ منطق مراحل نسخه قبلی بدون تغییر است.</p></div><button class="btn" onclick="showPage('customers')">فهرست مشتریان</button></div><div class="pipeline-v03">${STAGES.map(s=>{const rows=cs.filter(c=>c.stage===s.id);return `<section class="pipeline-stage"><div class="pipeline-stage-head"><b>${esc(s.label)}</b><span class="badge ${s.color}">${fmt(rows.length)}</span></div><div class="pipeline-stage-list">${rows.slice(0,8).map(c=>`<article class="pipeline-customer" onclick="openCustomerDetail('${c.id}')"><b>${esc(c.name)}</b><div class="mini muted">${esc(c.industry||'بدون صنعت')} · ${memberName(c.assignee)}</div></article>`).join('')||`<div class="mini muted">مشتری در این مرحله نیست.</div>`}</div></section>`}).join('')}</div>`;}
function renderOperationsV03(){const orderIds=['trial_order','purchase'];const orders=state.interactions.filter(i=>interaction_result_ids_js(i).some(id=>orderIds.includes(id))||i.fulfillment).sort((a,b)=>String(b.date).localeCompare(String(a.date)));document.getElementById('page-operations').innerHTML=`<div class="page-heading"><div><h1>ثبت مذاکره</h1><p>نتیجه تماس، درخواست خرید و پیگیری بعدی را ثبت و برای بازاریاب مسئول ارسال کنید.</p></div><button class="btn primary" onclick="showPage('activities')">ثبت مذاکره</button></div><div class="operations-list">${orders.length?orders.map(i=>{const c=customerById(i.customerId),f=i.fulfillment||{},status=f.outcome?.type?OUTCOME_TYPES[f.outcome.type]?.label:(f.deliveredAt?'تحویل‌شده':f.productionStatus||'در انتظار عملیات');return `<article class="operation-row"><div><b>${esc(c?.name||'مشتری حذف‌شده')}</b><p>${esc(interactionReplies(i).map(r=>r.label).join('، ')||'سفارش')} · ${faDateTime(i.date)}</p></div><div><span class="badge ${f.outcome?.type==='complaint'?'danger':f.outcome?.type?'ok':'warn'}">${esc(status)}</span></div><button class="btn small" onclick="openCustomerDetail('${i.customerId}')">مشاهده پرونده</button></article>`}).join(''):emptyState('سفارشی ثبت نشده است','نتیجه سفارش آزمایشی یا خرید از بخش مذاکرات ثبت می‌شود.')}</div>`;}
function renderReportsV03(){renderAnalysis();const source=document.getElementById('page-analysis');document.getElementById('page-reports').innerHTML=`<div class="page-heading"><div><h1>گزارش شخصی</h1><p>خلاصه عملکرد، پیگیری‌ها و نتیجه فعالیت‌های فروش شما.</p></div></div>${source.innerHTML}`;}
function renderAIV03(){const customers=visibleCustomers();document.getElementById('page-ai').innerHTML=`<div class="page-heading"><div><h1>دستیار هوش مصنوعی</h1><p>ابزارهای هوشمند از بخش مدیریت جدا هستند. اجرای واقعی آن‌ها به تنظیم بودن Provider روی سرور وابسته است.</p></div><span class="badge blue">ابزارهای هوشمند</span></div><div class="ai-workspace"><article class="ai-tool"><span class="ai-tool-label">فرآیند فروش</span><h3>تحلیل کل روند</h3><p>گلوگاه قیف، موانع پرتکرار و وضعیت سفارش‌ها را بررسی می‌کند.</p><button class="btn primary" onclick="aiAnalyzeProcess()">شروع تحلیل روند</button></article><article class="ai-tool"><span class="ai-tool-label">مشتری</span><h3>تحلیل پرونده مشتری</h3><p>یک مشتری را انتخاب کنید تا تاریخچه و اقدام بعدی تحلیل شود.</p><select id="aiCustomerSelect" aria-label="انتخاب مشتری">${customers.map(c=>`<option value="${c.id}">${esc(c.name)}</option>`).join('')}</select><button class="btn" style="margin-top:10px" onclick="const id=document.getElementById('aiCustomerSelect').value;if(id)aiAnalyzeCustomer(id)">تحلیل مشتری</button><div id="aiCustomerPanel" style="display:none;margin-top:12px"></div></article><article class="ai-tool"><span class="ai-tool-label">مذاکره</span><h3>پیشنهاد برای مذاکره</h3><p>ابتدا نتیجه مذاکره را ثبت کنید؛ تحلیل AI همان‌جا در کنار فرم نمایش داده می‌شود.</p><button class="btn" onclick="showPage('activities')">رفتن به ثبت مذاکره</button></article></div><div id="aiProcessPanel" style="display:none;margin-top:16px"></div>`;}
function interaction_result_ids_js(i){return Array.isArray(i.resultIds)?i.resultIds:(i.resultId?[i.resultId]:[])}
function renderDashboard(){const c=taskCounts(),p=overallProgress(),active=state.weeks[(state.project.activeWeek||1)-1],customers=visibleCustomers(),ints=visibleInteractions(),due=dueCustomers(),obs=topObstacle(ints.filter(i=>i.week===state.project.activeWeek));const won=customers.filter(x=>x.stage==='won').length;const activeTasks=active.tasks.filter(t=>t.status!=='done'&&(canViewAll()||t.assignee===currentMember()?.id)).slice().sort((a,b)=>mgrScore(b)-mgrScore(a)).slice(0,5);const top=customers.slice().sort((a,b)=>normalizedScore(b)-normalizedScore(a)).slice(0,5);const latest=ints.slice().sort((a,b)=>String(b.date).localeCompare(String(a.date))).slice(0,5);const weekly=buildWeeklyAnalysis(state.project.activeWeek);
 document.getElementById('page-dashboard').innerHTML=`<div class="hero"><div class="hero-content"><div><h1>${esc(state.project.name)}؛ از ثبت ساده تا تصمیم فروش</h1><p>فعالیت‌ها را سریع ثبت کنید، مانع‌های تکرارشونده را ببینید و برای هر مشتری و هفته بعد اقدام مشخص داشته باشید.</p><div class="hero-actions"><button class="btn primary" onclick="showPage('quick')">ثبت مذاکره جدید</button><button class="btn" onclick="openCustomerModal()">افزودن مشتری</button><button class="btn" onclick="showPage('analysis')">گزارش این هفته</button><button class="btn" onclick="exportForAI()">⤓ خروجی برای تحلیل</button></div></div><div class="hero-box"><span>هفته فعال</span><strong>هفته ${active.n}</strong><small>${esc(active.title)}</small></div></div></div>
 <div class="grid stats"><div class="card stat"><div class="stat-icon">✓</div><div><b>${p}٪</b><span>پیشرفت برنامه</span></div></div><div class="card stat"><div class="stat-icon">♙</div><div><b>${fmt(customers.length)}</b><span>مشتری ثبت‌شده</span></div></div><div class="card stat"><div class="stat-icon">✎</div><div><b>${fmt(ints.length)}</b><span>تعامل ثبت‌شده</span></div></div><div class="card stat"><div class="stat-icon">◷</div><div><b>${fmt(due.length)}</b><span>پیگیری سررسید</span></div></div><div class="card stat"><div class="stat-icon">◎</div><div><b>${fmt(won)}</b><span>خرید/تکرار</span></div></div><div class="card stat"><div class="stat-icon">!</div><div><b>${esc(obs.label)}</b><span>مانع پرتکرار</span></div></div></div>
 <div class="section-head"><div><h2>تصویر مدیریتی این هفته</h2><p>اول خلاصه و تصمیم؛ سپس جزئیات.</p></div><span class="badge blue">تحلیل داخلی مبتنی بر قواعد</span></div>
 <div class="analysis-hero"><div class="summary-box"><h3>${esc(weekly.headline)}</h3><p>${esc(weekly.summary)}</p><ul class="decision-list">${weekly.recommendations.slice(0,3).map(x=>`<li>← ${esc(x)}</li>`).join('')||'<li>برای تحلیل، تعامل‌های واقعی را ثبت کنید.</li>'}</ul></div><div class="card"><h3 style="margin-top:0">وضعیت برنامه ۱۲ هفته‌ای</h3><div style="display:flex;justify-content:space-between;font-size:12px"><span>${c.done} از ${c.all} کار تکمیل</span><b>${p}٪</b></div><div class="progress-track" style="margin:7px 0 13px"><div class="progress-bar" style="width:${p}%"></div></div><div class="timeline">${state.weeks.map(w=>`<div class="time-item ${w.n===state.project.activeWeek?'active':''} ${weekProgress(w)===100?'done':''}" onclick="selectedWeek=${w.n};showPage('weeks')"><b>${w.n}</b><small>${weekProgress(w)}٪</small></div>`).join('')}</div></div></div>
 <div class="two-col" style="margin-top:14px"><div class="card"><div class="section-head" style="margin:0 0 10px"><div><h3>کارهای اولویت‌دار هفته ${active.n}</h3><p>براساس نمای کاربر فعال</p></div><button class="btn small" onclick="showPage('mytasks')">همه کارها</button></div>${activeTasks.length?activeTasks.map(t=>`<div class="alert ${t.priority==='urgent'&&t.fromManager?'danger':t.status==='blocked'?'danger':t.status==='in_progress'?'info':'ok'}"><b>${esc(t.text)}</b> ${mgrBadge(t)}<div class="mini">${memberName(t.assignee)} · ${taskStatusLabel(t.status)}</div></div>`).join(''):emptyState('کار باز ندارید','برای این نمای کاربری در هفته فعال کاری باقی نمانده است.')}</div><div class="card"><div class="section-head" style="margin:0 0 10px"><div><h3>پیگیری‌های فوری</h3><p>امروز یا عقب‌افتاده</p></div><button class="btn small" onclick="showPage('followups')">مشاهده</button></div>${due.length?due.slice(0,5).map(x=>`<div class="alert ${x.nextFollowUp<todayISO()?'danger':'warn'} clickable" onclick="openCustomerDetail('${x.id}')"><b>${esc(x.name)}</b><div class="mini">${faDate(x.nextFollowUp)} · ${stageObj(x.stage).label} · ${memberName(x.assignee)}</div></div>`).join(''):emptyState('پیگیری فوری ندارید','زمان پیگیری بعدی مشتریان را در ثبت مذاکره تعیین کنید.')}</div></div>
 <div class="two-col" style="margin-top:14px"><div class="card table-wrap"><div class="section-head" style="margin:0 0 8px"><div><h3>مشتریان اولویت‌دار</h3><p>امتیاز بر اساس تناسب، حجم، تصمیم‌گیرنده، فوریت و پرداخت</p></div></div>${top.length?`<table class="data-table"><thead><tr><th>مشتری</th><th>مرحله</th><th>امتیاز</th><th>پیگیری</th></tr></thead><tbody>${top.map(x=>`<tr class="clickable" onclick="openCustomerDetail('${x.id}')"><td data-label="مشتری"><b>${esc(x.name)}</b><div class="mini muted">${esc(x.industry||'صنعت نامشخص')} · ${esc(x.city||'')}</div></td><td data-label="مرحله">${stageBadge(x.stage)}</td><td data-label="امتیاز"><b>${isScoreReviewed(x)?normalizedScore(x):'—'}</b></td><td data-label="پیگیری">${faDate(x.nextFollowUp)}</td></tr>`).join('')}</tbody></table>`:emptyState('هنوز مشتری ثبت نشده','اولین مشتری را اضافه کنید.',`<button class="btn primary" onclick="openCustomerModal()">افزودن مشتری</button>`)}</div><div class="card"><div class="section-head" style="margin:0 0 8px"><div><h3>آخرین فعالیت‌ها</h3><p>تاریخچه کوتاه فروش</p></div></div>${latest.length?`<div class="timeline-list">${latest.map(i=>{const cu=customerById(i.customerId),rs=interactionReplies(i);return `<div class="interaction-item"><h4>${esc(cu?.name||'مشتری حذف‌شده')} · ${esc(rs.map(r=>r.label).join('، ')||'تعامل')}</h4><p>${faDateTime(i.date)} · ${memberName(i.memberId)}</p><p>${esc(i.analysis?.summary||i.note||'بدون توضیح')}</p></div>`}).join('')}</div>`:emptyState('فعالیتی ثبت نشده','از صفحه ثبت سریع، نتیجه اولین تماس را وارد کنید.')}</div></div>`}
function renderMyTasks(){const all=state.weeks.flatMap(w=>w.tasks.map(t=>({...t,week:w.n,weekTitle:w.title})));const own=(canViewAll()?all:all.filter(t=>t.assignee===currentMember()?.id)).slice().sort((a,b)=>mgrScore(b)-mgrScore(a));const filter=state.settings.taskStatus||'all';const list=own.filter(t=>filter==='all'||t.status===filter);document.getElementById('page-mytasks').innerHTML=`<div class="section-head"><div><h2>کارهای من</h2><p>${canViewAll()?'نمای مدیریتی همه اعضا':`وظایف تخصیص‌یافته به ${esc(currentMember()?.name)}`}</p></div><div class="filters"><select onchange="state.settings.taskStatus=this.value;save();renderMyTasks()"><option value="all">همه وضعیت‌ها</option>${['not_started','in_progress','done','blocked','decision'].map(s=>`<option value="${s}" ${filter===s?'selected':''}>${taskStatusLabel(s)}</option>`).join('')}</select><button class="btn primary" onclick="openTaskModal()">＋ کار جدید</button></div></div><div class="card">${list.length?list.map(t=>`<div class="task"><button class="check ${t.status==='done'?'done':''}" onclick="toggleTask('${t.id}',${t.week})">${t.status==='done'?'✓':''}</button><div><div class="task-title ${t.status==='done'?'done':''}">${esc(t.text)} ${mgrBadge(t)}</div><div class="mini muted">هفته ${t.week} · ${esc(t.weekTitle)}</div></div><select class="status-select" onchange="updateTask('${t.id}',${t.week},'status',this.value)">${['not_started','in_progress','done','blocked','decision'].map(s=>`<option value="${s}" ${s===t.status?'selected':''}>${taskStatusLabel(s)}</option>`).join('')}</select><select class="assignee" onchange="updateTask('${t.id}',${t.week},'assignee',this.value)">${state.team.map(m=>`<option value="${m.id}" ${m.id===t.assignee?'selected':''}>${esc(m.name)}</option>`).join('')}</select><button class="icon-btn" onclick="deleteTask('${t.id}',${t.week})">×</button><div class="task-note"><textarea placeholder="نتیجه یا مانع این کار..." oninput="updateTask('${t.id}',${t.week},'note',this.value,false)">${esc(t.note)}</textarea></div></div>`).join(''):emptyState('کاری با این فیلتر وجود ندارد','وضعیت فیلتر را تغییر دهید یا یک کار جدید بسازید.')}</div>`}
function renderFollowups(){const overdue=dueCustomers().filter(c=>c.nextFollowUp<todayISO()),today=dueCustomers().filter(c=>c.nextFollowUp===todayISO()),up=upcomingCustomers();const section=(title,arr,klass)=>`<div class="card"><h3 style="margin-top:0">${title} <span class="badge ${klass}">${fmt(arr.length)}</span></h3>${arr.length?arr.map(c=>`<div class="alert ${klass==='danger'?'danger':klass==='warn'?'warn':'info'}"><div style="display:flex;justify-content:space-between;gap:8px;align-items:center"><div class="clickable" onclick="openCustomerDetail('${c.id}')"><b>${esc(c.name)}</b><div class="mini">${faDate(c.nextFollowUp)} · ${stageObj(c.stage).label} · ${memberName(c.assignee)}</div></div><button class="btn small primary" onclick="prefillQuick('${c.id}')">ثبت نتیجه</button></div></div>`).join(''):emptyState('موردی وجود ندارد','')}</div>`;document.getElementById('page-followups').innerHTML=`<div class="hero"><div class="hero-content"><div><h1>پیگیری‌ها و یادآوری فروش</h1><p>هیچ مشتری جدی نباید به دلیل فراموشی یا گزارش ناقص رها شود.</p></div><div class="hero-box"><span>سررسید و عقب‌افتاده</span><strong>${fmt(overdue.length+today.length)}</strong></div></div></div><div class="three-col" style="margin-top:14px">${section('عقب‌افتاده',overdue,'danger')}${section('امروز',today,'warn')}${section('۷ روز آینده',up,'blue')}</div>${reorderReminderSectionHtml()}`}
function selectedReasonSummaryHTML(){const reasons=selectedReasonIds.map(id=>replyById(id)).filter(Boolean);if(!reasons.length)return `<div class="selected-reason-summary empty"><span>هنوز نتیجه‌ای انتخاب نشده است.</span></div>`;return `<div class="selected-reason-summary"><b>${fmt(reasons.length)} نتیجه انتخاب شد</b><span>${reasons.map(r=>esc(r.label)).join('، ')}</span></div>`}
function renderQuick(prefill=''){const customers=visibleCustomers();const reasons=selectedReasonIds.map(id=>replyById(id)).filter(Boolean);document.getElementById('page-quick').innerHTML=`<div class="section-head"><div><h2>ثبت سریع مذاکره</h2><p>هدف: ثبت تعامل معمول در کمتر از ۶۰ ثانیه؛ متن طولانی اختیاری است.</p></div><span class="badge ok">۳ انتخاب اصلی: مشتری، نتیجه، پیگیری</span></div><div class="quick-shell"><div class="card"><div class="form-grid"><div class="field"><label>مشتری *</label><select id="quickCustomer"><option value="">انتخاب مشتری</option>${customers.map(c=>`<option value="${c.id}" ${c.id===prefill?'selected':''}>${esc(c.name)}</option>`).join('')}</select></div><div class="field"><label>کانال ارتباط</label><select id="quickChannel">${CHANNELS.map(x=>`<option value="${x[0]}">${x[1]}</option>`).join('')}</select></div><div class="field"><label>مسئول ثبت</label><select id="quickMember">${state.team.map(m=>`<option value="${m.id}" ${m.id===currentMember()?.id?'selected':''}>${esc(m.name)}</option>`).join('')}</select></div><div class="field full"><label class="reply-options-label"><span>نتیجه‌های مذاکره * <span class="mini muted">(می‌توانید چند مورد را هم‌زمان انتخاب کنید)</span></span>${replyOptionsManageButton()}</label><div class="reason-grid">${state.replyLibrary.filter(r=>r.active).map(r=>{const active=selectedReasonIds.includes(r.id);return `<button type="button" class="reason-btn ${active?'active':''}" data-reason-id="${r.id}" aria-pressed="${active?'true':'false'}" onclick="toggleReason('${r.id}')"><span>${esc(r.label)}</span></button>`}).join('')}</div><div id="selectedReasonSummary">${selectedReasonSummaryHTML()}</div>${isCallCenterRole()?'<div class="reply-handoff-note">پس از ثبت، نتیجه به‌صورت خودکار برای بازاریاب مسئول مشتری ارسال می‌شود.</div>':''}</div><div class="field"><label>زمان پیگیری بعدی</label>${jdatePickerHtml('quickFollow',addDaysISO(3))}</div><div class="field"><label>حجم تقریبی اعلام‌شده مشتری</label><input id="quickVolume" type="number" placeholder="مثلاً ۵ تن" oninput="recalcQuickValue()"></div><div class="field"><label>قیمت مورد انتظار مشتری</label><input id="quickPrice" type="text" inputmode="numeric" placeholder="تومان / واحد" oninput="onMoneyInput('quickPrice')"></div><div class="field"><label>ارزش احتمالی معامله <span class="mini muted">(خودکار: حجم × قیمت)</span></label><input id="quickValue" type="text" placeholder="تومان" readonly></div><div class="field span2"><label>توضیح اختیاری</label><textarea id="quickNote" placeholder="فقط نکته‌ای که برای تصمیم بعدی مهم است..."></textarea></div><div class="field"><label>ثبت در هفته</label><select id="quickWeek">${state.weeks.map(w=>`<option value="${w.n}" ${w.n===state.project.activeWeek?'selected':''}>هفته ${w.n}</option>`).join('')}</select></div><div class="full" style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn primary" onclick="saveInteraction()">ثبت و ساخت تحلیل</button><button class="btn" onclick="openCustomerModal()">مشتری جدید</button></div></div></div><div id="quickPreview" class="quick-preview">${quickPreviewHTML(reasons)}</div></div><div class="section-head"><div><h3>ثبت‌های اخیر</h3><p>برای اصلاح یا مشاهده پرونده مشتری</p></div></div><div class="card table-wrap">${recentInteractionTable(8)}</div>`}
function quickPreviewHTML(reasons){if(!reasons||!reasons.length)return '<h3>یک یا چند نتیجه‌ی مذاکره را انتخاب کنید</h3><p>پیشنهاد هوش مصنوعی اینجا نمایش داده می‌شود.</p>';const cats=[...new Set(reasons.map(r=>r.category))],actions=[...new Set(reasons.map(r=>r.action))];return `<div style="display:flex;flex-wrap:wrap;gap:5px">${reasons.map(r=>`<span class="badge" style="background:rgba(255,255,255,.14);color:#fff">${esc(r.label)}</span>`).join('')}</div><h3 style="margin:8px 0 4px">${esc(cats.join(' / '))}</h3><p>${esc(actions.join('؛ '))}</p><button class="btn block" type="button" style="margin-top:10px;background:#fff;color:var(--navy)" onclick="aiAnalyzeNegotiation()">✦ تحلیل با هوش مصنوعی</button><div id="aiPanel" style="margin-top:10px"></div>`}
function toggleReason(id){const i=selectedReasonIds.indexOf(id);if(i>-1)selectedReasonIds.splice(i,1);else selectedReasonIds.push(id);document.querySelectorAll('.reason-btn').forEach(b=>{const buttonId=b.dataset.reasonId||((b.getAttribute('onclick')||'').match(/toggleReason\('([^']+)'\)/)||[])[1]||'';const active=selectedReasonIds.includes(buttonId);b.dataset.reasonId=buttonId;b.classList.toggle('active',active);b.setAttribute('aria-pressed',active?'true':'false');b.style.background=active?'var(--color-primary,var(--brand,#8b2635))':'';b.style.borderColor=active?'var(--color-primary,var(--brand,#8b2635))':'';b.style.color=active?'#fff':'';b.style.boxShadow=active?'0 0 0 3px var(--color-primary-soft,var(--brandSoft,#f7e9ed)),0 7px 16px rgba(80,25,35,.16)':''});document.querySelectorAll('#selectedReasonSummary').forEach(summary=>summary.innerHTML=selectedReasonSummaryHTML());document.querySelectorAll('#quickPreview').forEach(p=>p.innerHTML=quickPreviewHTML(selectedReasonIds.map(id=>replyById(id)).filter(Boolean)))}
function prefillQuick(id){showPage('quick');renderQuick(id)}
function analyzeInteraction(c,reasons,note,data){const ids=reasons.map(r=>r.id);const missing=[];if(!c.estimatedVolume&&!data.volume)missing.push('حجم مصرف/سفارش');if(!c.paymentPreference)missing.push('شرایط پرداخت قابل قبول');if(!c.contact)missing.push('نام تصمیم‌گیرنده');if(ids.some(id=>['price_high','competitor_lower'].includes(id))&&!c.competitor)missing.push('قیمت و شرایط دقیق رقیب');if(ids.includes('transport')&&!c.city)missing.push('مقصد حمل');const signals=[];if(ids.some(id=>['sample_requested','quote_requested','trial_order','purchase','follow_up'].includes(id)))signals.push('تمایل به ادامه مذاکره');if(data.volume>0)signals.push('حجم مورد بحث مشخص شده');if(data.value>0)signals.push('ارزش احتمالی ثبت شده');const score=normalizedScore(c);const confidence=(note?.length>35||missing.length<=1)?'زیاد':(note?.length>8||missing.length<=3)?'متوسط':'کم';const stage=pickStage(reasons);const labels=reasons.map(r=>r.label).join('، ');const categories=[...new Set(reasons.map(r=>r.category))].join('/');const actions=[...new Set(reasons.map(r=>r.action))].join('؛ ');const response=reasons.map(r=>r.response).find(Boolean)||'';return {summary:`${c.name} در مرحله «${stageObj(stage).label}» قرار گرفت؛ نتیجه: ${labels}${note?`؛ نکته ثبت‌شده: ${note}`:''}.`,mainObstacle:categories,buyingSignals:signals,missingData:missing,nextAction:actions,confidence,managerDecisionRequired:!!(reasons.some(r=>r.manager)&&score>=55),suggestedResponse:response}}
function saveInteraction(){
  const customerId=document.getElementById('quickCustomer')?.value;
  if(!customerId)return toast('ابتدا مشتری را انتخاب کنید');
  if(!selectedReasonIds.length)return toast('حداقل یک نتیجه‌ی مذاکره را انتخاب کنید');
  const c=customerById(customerId),level=interactionAccessLevel(c);
  if(accessRank(level)<2)return toast('این حساب فقط اجازه مشاهده این مشتری را دارد');
  const reasons=selectedReasonIds.map(id=>replyById(id)).filter(Boolean),resultIds=selectedReasonIds.slice();
  const note=document.getElementById('quickNote').value.trim(),follow=document.getElementById('quickFollow').value,channel=document.getElementById('quickChannel').value;
  const it={id:'i'+Date.now(),customerId,resultIds,channel,date:new Date().toISOString(),note,nextFollowUp:follow,status:'completed',memberId:window.HIPPO_AUTH?.display_name||SERVER_NAME||''};
  if(level==='edit'){
    const data={volume:+document.getElementById('quickVolume').value||0,price:+digitsOnly(document.getElementById('quickPrice').value)||0,value:+digitsOnly(document.getElementById('quickValue').value)||0};
    it.week=+document.getElementById('quickWeek').value;Object.assign(it,data);it.analysis=analyzeInteraction(c,reasons,note,data);
    const stage=pickStage(reasons);c.stage=stage||c.stage;if(data.volume&&!c.estimatedVolume)c.estimatedVolume=data.volume;
  }
  state.interactions.push(it);c.nextFollowUp=resultIds.some(id=>['purchase','stop','not_fit'].includes(id))&&level==='edit'?'':follow;c.updatedAt=new Date().toISOString();
  selectedReasonIds=[];save();renderAll();showPage('quick');toast(isCallCenterRole()?'نتیجه ثبت و برای بازاریاب مسئول ارسال شد':'مذاکره ثبت شد');
}
/* ===== پیشنهاد هوش مصنوعی (فاز ۲) — فقط پیشنهاد؛ چیزی خودکار ثبت نمی‌شود ===== */
let _aiReply='';
async function aiAnalyzeNegotiation(){
  const customerId=document.getElementById('quickCustomer')?.value;
  if(!customerId)return toast('ابتدا مشتری را انتخاب کنید');
  const c=customerById(customerId);
  const reasons=selectedReasonIds.map(id=>replyById(id)).filter(Boolean);
  const note=document.getElementById('quickNote').value.trim();
  if(!note&&!reasons.length)return toast('حداقل یک نتیجه‌ی مذاکره را انتخاب کنید یا توضیحی بنویسید');
  const panel=document.getElementById('aiPanel');panel.style.display='block';
  panel.innerHTML='<div class="ui-state"><span class="ui-spinner" aria-hidden="true"></span><div><b>در حال تحلیل مذاکره</b><div class="mini muted">داده‌های ثبت‌شده برای ساخت پیشنهاد بررسی می‌شوند.</div></div></div><div class="ui-skeleton" style="margin-top:8px;width:78%"></div>';
  /* تاریخچه‌ی همین مشتری (حداکثر ۱۵ مورد اخیر) هم فرستاده می‌شود تا AI روند را ببیند، نه فقط لحظه‌ی فعلی. */
  const history=state.interactions.filter(i=>i.customerId===customerId).slice().sort((a,b)=>String(b.date).localeCompare(String(a.date))).slice(0,15).map(i=>({date:String(i.date||'').slice(0,10),results:interactionReplies(i).map(r=>r.label),note:i.note||''}));
  const body={customerName:c?.name||'',customerStage:stageObj(c?.stage).label,industry:c?.industry||'',resultLabels:reasons.map(r=>r.label),channel:document.getElementById('quickChannel').value,note,volume:+document.getElementById('quickVolume').value||0,value:+digitsOnly(document.getElementById('quickValue').value)||0,paymentPreference:c?.paymentPreference||'',competitor:c?.competitor||'',history};
  try{
    const resp=await fetch('ai.php?action=analyzeNegotiation',{method:'POST',credentials:'same-origin',headers:csrfHeaders({'Content-Type':'application/json'}),body:JSON.stringify(body)});
    if(resp.status===401){location.href='login.php';return}
    const j=await resp.json().catch(()=>null);
    if(j&&j.ok){panel.innerHTML=aiSuggestionHTML(j.data)}
    else{panel.innerHTML=`<div class="ai-card" style="color:var(--ink)"><span class="ai-label">هوش مصنوعی</span><p style="margin:6px 0 0">${esc(j&&j.message?j.message:'تحلیل هوش مصنوعی در دسترس نبود.')}</p></div>`}
  }catch(e){panel.innerHTML='<div class="ai-card" style="color:var(--ink)"><span class="ai-label">هوش مصنوعی</span><p style="margin:6px 0 0">خطا در ارتباط با سرور هوش مصنوعی.</p></div>'}
}
function aiSuggestionHTML(d){
  _aiReply=d.suggested_reply||'';
  const missing=(d.missing_data||[]).filter(Boolean);
  return `<div class="ai-card" style="color:var(--ink)"><span class="ai-label">پیشنهاد هوش مصنوعی — فقط پیشنهاد، تصمیم نهایی با شماست</span>
  <h4 style="margin:6px 0">${esc(d.summary||'')}</h4>
  <p style="margin:4px 0"><b>اقدام بعدی:</b> ${esc(d.next_action||'—')}</p>
  <p style="margin:4px 0"><b>پیگیری پیشنهادی:</b> ${(+d.follow_up_days>0)?fmt(d.follow_up_days)+' روز دیگر':'مشخص نشده'}</p>
  ${d.manager_decision_required?'<p style="margin:4px 0"><span class="badge warn">نیازمند تصمیم مدیریت</span></p>':''}
  ${missing.length?`<p style="margin:4px 0"><b>داده‌های ناقص:</b> ${esc(missing.join('، '))}</p>`:''}
  <div style="background:#fff;border:1px solid var(--line);border-radius:12px;padding:11px;margin-top:8px"><b style="font-size:12px;color:var(--brand)">پیش‌نویس پاسخ به مشتری</b><div style="margin-top:4px">${esc(d.suggested_reply||'')}</div></div>
  <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;align-items:center"><button class="btn small" type="button" onclick="copyText(_aiReply)">کپی پیش‌نویس پاسخ</button><span class="confidence">اطمینان: ${esc(d.confidence||'کم')}</span></div></div>`;
}
/* ===== تحلیل کل روند فروش با AI (نه یک مذاکره‌ی تکی) — فقط آمار تجمیعی می‌فرستد، نه اطلاعات شخصی مشتری ===== */
function buildProcessSummary(){
  const customers=visibleCustomers();
  const funnel=STAGES.map(s=>({stage:s.label,count:customers.filter(c=>c.stage===s.id).length}));
  const replyStats=state.replyLibrary.filter(r=>r.active).map(r=>{
    const usedBy=new Set(state.interactions.filter(i=>interactionResultIds(i).includes(r.id)).map(i=>i.customerId));
    const used=usedBy.size;
    if(!used)return null;
    const won=[...usedBy].filter(cid=>customerById(cid)?.stage==='won').length;
    return {label:r.label,category:r.category,used,won};
  }).filter(Boolean).sort((a,b)=>b.used-a.used).slice(0,15);
  const orders=orderInteractions();
  const fulfillment={};
  orders.forEach(i=>{const k=orderStatus(i).key;fulfillment[k]=(fulfillment[k]||0)+1});
  return {funnel,replyStats,fulfillment,totalCustomers:customers.length,totalOrders:orders.length};
}
async function aiAnalyzeProcess(){
  const panel=document.getElementById('aiProcessPanel');
  if(!panel)return;
  panel.style.display='block';
  panel.innerHTML='<div class="ui-state"><span class="ui-spinner" aria-hidden="true"></span><div><b>در حال تحلیل روند فروش</b><div class="mini muted">قیف، موانع و وضعیت سفارش‌ها بررسی می‌شوند.</div></div></div><div class="ui-skeleton" style="margin-top:8px;width:84%"></div>';
  const body=buildProcessSummary();
  try{
    const resp=await fetch('ai.php?action=analyzeProcess',{method:'POST',credentials:'same-origin',headers:csrfHeaders({'Content-Type':'application/json'}),body:JSON.stringify(body)});
    if(resp.status===401){location.href='login.php';return}
    const j=await resp.json().catch(()=>null);
    if(j&&j.ok){panel.innerHTML=aiProcessSuggestionHTML(j.data)}
    else{panel.innerHTML=`<div class="ai-card" style="color:var(--ink)"><span class="ai-label">هوش مصنوعی</span><p style="margin:6px 0 0">${esc(j&&j.message?j.message:'تحلیل هوش مصنوعی در دسترس نبود.')}</p></div>`}
  }catch(e){panel.innerHTML='<div class="ai-card" style="color:var(--ink)"><span class="ai-label">هوش مصنوعی</span><p style="margin:6px 0 0">خطا در اتصال به سرویس هوش مصنوعی.</p></div>'}
}
function aiProcessSuggestionHTML(d){
  const recs=(d.top_recommendations||[]).filter(Boolean);
  return `<div class="ai-card" style="color:var(--ink)"><span class="ai-label">تحلیل روند فروش — فقط پیشنهاد، تصمیم نهایی با شماست</span>
  <h4 style="margin:6px 0">گلوگاه: ${esc(d.bottleneck_stage||'—')}</h4>
  <p style="margin:4px 0">${esc(d.bottleneck_reason||'')}</p>
  <p style="margin:4px 0"><b>پاسخ‌های آماده:</b> ${esc(d.reply_effectiveness_note||'—')}</p>
  <p style="margin:4px 0"><b>تولید/تحویل/شکایت:</b> ${esc(d.fulfillment_note||'—')}</p>
  ${recs.length?`<div style="background:#fff;border:1px solid var(--line);border-radius:12px;padding:11px;margin-top:8px"><b style="font-size:12px;color:var(--brand)">پیشنهادها</b><ul style="margin:6px 0 0;padding-right:18px">${recs.map(x=>`<li style="font-size:12px;margin:4px 0">${esc(x)}</li>`).join('')}</ul></div>`:''}
  <div style="margin-top:8px"><span class="confidence">اطمینان: ${esc(d.confidence||'کم')}</span></div></div>`;
}
/* ===== تحلیل یک مشتری خاص با AI — کل تاریخچه‌ی سفارش‌ها/تعامل‌های همین یک مشتری، نه کل سیستم ===== */
function buildCustomerAnalysisPayload(c){
  const orders=customerOrders(c.id).map(i=>({date:String(i.date).slice(0,10),outcome:i.fulfillment?.outcome?.type||'pending',note:i.fulfillment?.outcome?.note||''}));
  const avg=customerAvgOrderInterval(c.id);
  const signal=customerReorderSignal(c.id);
  const ints=state.interactions.filter(i=>i.customerId===c.id).sort((a,b)=>String(b.date).localeCompare(String(a.date))).slice(0,15).map(i=>({date:String(i.date).slice(0,10),results:interactionReplies(i).map(r=>r.label),note:i.note||''}));
  return {customerName:c.name,customerStage:stageObj(c.stage).label,industry:c.industry||'',orders,avgIntervalDays:avg,daysSinceLastOrder:signal?signal.days:null,reorderLevel:signal?signal.level:null,recentInteractions:ints};
}
async function aiAnalyzeCustomer(id){
  const c=customerById(id);if(!c)return;
  const panel=document.getElementById('aiCustomerPanel');
  if(!panel)return;
  panel.style.display='block';
  panel.innerHTML='<div class="ui-state"><span class="ui-spinner" aria-hidden="true"></span><div><b>در حال تحلیل مشتری</b><div class="mini muted">تاریخچه و اقدام بعدی بررسی می‌شوند.</div></div></div><div class="ui-skeleton" style="margin-top:8px;width:72%"></div>';
  const body=buildCustomerAnalysisPayload(c);
  try{
    const resp=await fetch('ai.php?action=analyzeCustomer',{method:'POST',credentials:'same-origin',headers:csrfHeaders({'Content-Type':'application/json'}),body:JSON.stringify(body)});
    if(resp.status===401){location.href='login.php';return}
    const j=await resp.json().catch(()=>null);
    if(j&&j.ok){panel.innerHTML=aiCustomerSuggestionHTML(j.data)}
    else{panel.innerHTML=`<div class="ai-card" style="color:var(--ink)"><span class="ai-label">هوش مصنوعی</span><p style="margin:6px 0 0">${esc(j&&j.message?j.message:'تحلیل هوش مصنوعی در دسترس نبود.')}</p></div>`}
  }catch(e){panel.innerHTML='<div class="ai-card" style="color:var(--ink)"><span class="ai-label">هوش مصنوعی</span><p style="margin:6px 0 0">خطا در اتصال به سرویس هوش مصنوعی.</p></div>'}
}
function aiCustomerSuggestionHTML(d){
  return `<div class="ai-card" style="color:var(--ink)"><span class="ai-label">تحلیل این مشتری — فقط پیشنهاد، تصمیم نهایی با شماست</span>
  <h4 style="margin:6px 0">${esc(d.diagnosis||'')}</h4>
  <p style="margin:4px 0"><b>دلیل احتمالی گیر کردن:</b> ${esc(d.stuck_reason||'—')}</p>
  <p style="margin:4px 0"><b>اقدام پیشنهادی:</b> ${esc(d.next_action||'—')}</p>
  <div style="margin-top:8px"><span class="confidence">اطمینان: ${esc(d.confidence||'کم')}</span></div></div>`;
}
function recentInteractionTable(limit=20,customerId='',weekFilter=''){const list=visibleInteractions().filter(i=>(!customerId||i.customerId===customerId)&&(!weekFilter||+i.week===+weekFilter)).slice().sort((a,b)=>String(b.date).localeCompare(String(a.date))).slice(0,limit);if(!list.length)return emptyState('تعامل ثبت نشده','پس از تماس یا مذاکره، نتیجه را در کمتر از یک دقیقه ثبت کنید.');return `<table class="data-table"><thead><tr><th>تاریخ</th><th>مشتری</th><th>نتیجه</th><th>اقدام بعدی</th><th>مسئول</th></tr></thead><tbody>${list.map(i=>{const c=customerById(i.customerId),rs=interactionReplies(i);return `<tr class="clickable" onclick="openInteractionDetail('${i.id}')"><td data-label="تاریخ">${faDateTime(i.date)}</td><td data-label="مشتری"><b>${esc(c?.name||'حذف‌شده')}</b></td><td data-label="نتیجه">${esc(rs.map(r=>r.label).join('، '))}</td><td data-label="اقدام بعدی">${esc(i.analysis?.nextAction||'')}</td><td data-label="مسئول">${memberName(i.memberId)}</td></tr>`}).join('')}</tbody></table>`}
function setCrmView(view){
  if(!['cards','pipeline','table'].includes(view))view='cards';
  state.settings.crmView=view;save();renderCustomers();
}
function clearCustomerFilters(){state.settings.customerSearch='';state.settings.customerStage='all';save();renderCustomers()}
function crmViewButton(view,label,icon){const active=(state.settings.crmView||'cards')===view;return `<button type="button" class="v07-view-btn ${active?'active':''}" onclick="setCrmView('${view}')" aria-pressed="${active?'true':'false'}"><span>${icon}</span>${label}</button>`}
function customerTableHTML(list){return `<div class="v07-customer-table-wrap"><table class="data-table v07-customer-table"><thead><tr><th>مشتری</th><th>مرحله</th><th>مسئول</th><th>تماس</th><th>پیگیری بعدی</th><th>امتیاز</th><th>اقدام</th></tr></thead><tbody>${list.map(c=>`<tr><td data-label="مشتری"><button class="v07-customer-link" onclick="openCustomerDetail('${c.id}')"><span class="v07-mini-avatar">${esc((c.name||'م').slice(0,1))}</span><span><b>${esc(c.name)}</b><small>${esc(c.industry||'صنعت نامشخص')} · ${esc(c.city||'شهر نامشخص')}</small></span></button></td><td data-label="مرحله">${stageBadge(c.stage)}</td><td data-label="مسئول">${memberName(c.assignee)}</td><td data-label="تماس"><span class="v07-contact-cell">${esc(c.contact||'—')}<small>${esc(c.phone||'')}</small></span></td><td data-label="پیگیری بعدی">${faDate(c.nextFollowUp)}</td><td data-label="امتیاز"><span class="v07-score-pill ${isScoreReviewed(c)?'':'muted'}">${isScoreReviewed(c)?normalizedScore(c):'—'}</span></td><td data-label="اقدام"><div class="v07-inline-actions"><button class="btn small primary" onclick="prefillQuick('${c.id}')">ثبت مذاکره</button><button class="btn small" onclick="openCustomerDetail('${c.id}')">پرونده</button></div></td></tr>`).join('')}</tbody></table></div>`}
function sortPipelineCustomers(list){return list.slice().sort((a,b)=>{const ad=String(a.nextFollowUp||'9999-12-31'),bd=String(b.nextFollowUp||'9999-12-31');if(ad!==bd)return ad.localeCompare(bd);return String(a.name||'').localeCompare(String(b.name||''),'fa')})}
function pipelineFollowupText(c){if(!c.nextFollowUp)return 'بدون پیگیری';const d=String(c.nextFollowUp).slice(0,10),today=todayISO();if(d<today)return `عقب‌افتاده · ${faDate(d)}`;if(d===today)return 'پیگیری امروز';if(d===addDaysISO(1))return 'پیگیری فردا';return `پیگیری ${faDate(d)}`}
function customerPipelineHTML(list){return `<div class="v07-pipeline-guide">مرتب بر اساس نزدیک‌ترین پیگیری؛ برای مشاهده پرونده روی نام مشتری بزنید.</div><div class="v07-crm-board">${STAGES.map(s=>{const xs=sortPipelineCustomers(list.filter(c=>c.stage===s.id));return `<section class="v07-crm-column stage-${s.color}"><header><div><b>${esc(s.label)}</b></div><span class="v07-stage-count ${s.color}">${fmt(xs.length)}</span></header><div class="v07-crm-column-body">${xs.map(c=>`<article class="v07-board-card"><button type="button" class="v07-board-card-name" onclick="openCustomerDetail('${c.id}')">${esc(c.name)}</button><div class="v07-board-card-review"><span>${isScoreReviewed(c)?`بررسی‌شده · امتیاز ${normalizedScore(c)}`:'بررسی‌نشده'}</span><span>${esc(memberName(c.assignee)||'بدون تخصیص')}</span></div><div class="v07-board-card-follow ${c.nextFollowUp&&String(c.nextFollowUp).slice(0,10)<todayISO()?'overdue':''}">${esc(pipelineFollowupText(c))}</div><div class="v07-board-card-actions"><button class="btn small primary" onclick="prefillQuick('${c.id}')">ثبت مذاکره</button><button class="btn small" onclick="openCustomerDetail('${c.id}')">پرونده</button></div></article>`).join('')||`<div class="v07-column-empty">مشتری در این مرحله نیست.</div>`}</div></section>`}).join('')}</div>`}
function renderCustomers(){
  const search=(state.settings.customerSearch||'').toLowerCase(),stage=state.settings.customerStage||'all';
  const all=visibleCustomers();
  const list=all.filter(c=>(stage==='all'||c.stage===stage)&&[c.name,c.industry,c.city,c.contact,c.phone].join(' ').toLowerCase().includes(search));
  const due=all.filter(c=>c.nextFollowUp&&!['won','paused'].includes(c.stage)&&c.nextFollowUp<=todayISO()).length;
  const hot=all.filter(c=>isScoreReviewed(c)&&normalizedScore(c)>=72).length;
  const activeDeals=all.filter(c=>['qualified','sample','negotiation','trial'].includes(c.stage)).length;
  const view=['cards','pipeline','table'].includes(state.settings.crmView)?state.settings.crmView:'cards';
  const body=view==='pipeline'?customerPipelineHTML(list):view==='table'?customerTableHTML(list):(list.length?`<div class="customer-grid v07-customer-grid">${list.map(c=>customerCard(c)).join('')}</div>`:emptyState('مشتری مطابق فیلتر پیدا نشد','فیلتر را تغییر دهید یا مشتری جدید اضافه کنید.',`<button class="btn primary" onclick="openCustomerModal()">افزودن مشتری</button>`));
  document.getElementById('page-customers').innerHTML=`<div class="v07-crm-hero"><div><span class="v04-kicker">CRM یکپارچه</span><h1>مشتریان</h1><p>جست‌وجو، اولویت‌بندی، مرحله فروش و تاریخچه هر مشتری در یک فضای کاری.</p></div><div class="v07-crm-hero-actions"><button class="btn" onclick="openCustomerImportModal()">ورود از Excel</button><button class="btn primary" onclick="openCustomerModal()">＋ مشتری جدید</button></div></div>
  <div class="v07-crm-metrics"><article><span class="v07-metric-icon">م</span><div><small>کل مشتریان</small><b>${fmt(all.length)}</b></div></article><article><span class="v07-metric-icon warn">پ</span><div><small>پیگیری سررسید</small><b>${fmt(due)}</b></div></article><article><span class="v07-metric-icon hot">★</span><div><small>مشتری اولویت‌دار</small><b>${fmt(hot)}</b></div></article><article><span class="v07-metric-icon ok">↗</span><div><small>فرصت فعال</small><b>${fmt(activeDeals)}</b></div></article></div>
  <div class="v07-crm-toolbar"><div class="v07-crm-filters"><label class="v07-search-field"><span>⌕</span><input placeholder="نام، صنعت، شهر، شخص تماس یا شماره..." value="${esc(state.settings.customerSearch||'')}" oninput="state.settings.customerSearch=this.value;save();renderCustomers()"></label><select aria-label="فیلتر مرحله فروش" onchange="state.settings.customerStage=this.value;save();renderCustomers()"><option value="all">همه مراحل</option>${STAGES.map(s=>`<option value="${s.id}" ${stage===s.id?'selected':''}>${s.label}</option>`).join('')}</select>${(search||stage!=='all')?'<button class="btn small" onclick="clearCustomerFilters()">پاک‌کردن فیلتر</button>':''}</div><div class="v07-view-switch" aria-label="نوع نمایش">${crmViewButton('cards','کارت‌ها','▦')}${crmViewButton('pipeline','قیف','▥')}${crmViewButton('table','جدول','☷')}</div></div>
  <div class="v07-crm-result-head"><div><h3>${view==='pipeline'?'نمای قیف فروش':view==='table'?'فهرست مشتریان':'کارت‌های مشتریان'}</h3><p>${view==='pipeline'?'مراحل فروش، وضعیت بررسی، مسئول و نزدیک‌ترین پیگیری هر مشتری نمایش داده می‌شود.':`${fmt(list.length)} مورد از ${fmt(all.length)} مشتری نمایش داده می‌شود.`}</p></div><div class="v07-result-actions"><button class="btn small" onclick="openDuplicateCustomersModal()">مشتریان تکراری</button><button class="btn small" onclick="exportCustomersCSV()">خروجی CSV</button></div></div>${body}${ordersDashboardHtml()}`
}
function customerCard(c){const last=customerLastInteraction(c.id),reviewed=isScoreReviewed(c);return `<article class="customer-card v07-customer-card" onclick="openCustomerDetail('${c.id}')"><div class="v07-card-accent"></div><div class="customer-head"><div class="v07-customer-identity"><span class="v07-card-avatar">${esc((c.name||'م').slice(0,1))}</span><div><h3>${esc(c.name)}</h3><p>${esc(c.industry||'صنعت نامشخص')} · ${esc(c.city||'شهر نامشخص')}</p></div></div><div class="score-ring ${reviewed?'':'unreviewed'}" style="--score:${reviewed?normalizedScore(c):0}" title="${reviewed?'امتیاز':'هنوز بررسی نشده'}"><span>${reviewed?normalizedScore(c):'—'}</span></div></div><div class="customer-meta">${stageBadge(c.stage)}<span class="badge">${memberName(c.assignee)}</span>${c.estimatedVolume?`<span class="badge blue">حجم ${fmt(c.estimatedVolume)}</span>`:''}</div><div class="v07-card-contact"><span><b>${esc(c.contact||'شخص تماس ثبت نشده')}</b><small>${esc(c.phone||'شماره ثبت نشده')}</small></span><span><b>پیگیری بعدی</b><small>${faDate(c.nextFollowUp)}</small></span></div><div class="customer-foot"><span>${last?`آخرین تعامل: ${faDate(last.date)}`:'بدون تعامل'}</span><div class="v07-card-actions"><button class="btn small primary" onclick="event.stopPropagation();prefillQuick('${c.id}')">ثبت مذاکره</button><button class="btn small" onclick="event.stopPropagation();openCustomerDetail('${c.id}')">پرونده</button></div></div></article>`}
function openCustomerModal(id=''){const c=id?customerById(id):{name:'',industry:'',city:'',contact:'',phone:'',address:'',assignee:currentMember()?.id||state.team[0]?.id,stage:'new',source:'',estimatedVolume:'',paymentPreference:'',competitor:'',technicalNeed:'',note:'',nextFollowUp:'',score:{fit:3,volume:3,decision:2,urgency:2,payment:2}};openModal(`<div class="modal-head"><h3>${id?'ویرایش مشتری':'افزودن مشتری'}</h3><button class="icon-btn" onclick="closeModal()">×</button></div><div class="modal-grid"><div class="field"><label>نام شرکت/مشتری *</label><input id="cName" value="${esc(c.name)}"></div><div class="field"><label>صنعت/کاربرد</label><input id="cIndustry" value="${esc(c.industry)}"></div><div class="field"><label>شهر</label><input id="cCity" value="${esc(c.city)}"></div><div class="field"><label>نام تصمیم‌گیرنده/تماس</label><input id="cContact" value="${esc(c.contact)}"></div><div class="field"><label>شماره تماس</label><input id="cPhone" value="${esc(c.phone)}"></div><div class="field full"><label>آدرس</label><input id="cAddress" value="${esc(c.address||'')}"></div><div class="field"><label>منبع آشنایی</label><input id="cSource" value="${esc(c.source)}" placeholder="معرفی، تماس مستقیم، سایت..."></div><div class="field"><label>مسئول مشتری</label><select id="cAssignee">${state.team.map(m=>`<option value="${m.id}" ${m.id===c.assignee?'selected':''}>${esc(m.name)}</option>`).join('')}</select></div><div class="field"><label>مرحله فروش</label><select id="cStage">${STAGES.map(s=>`<option value="${s.id}" ${s.id===c.stage?'selected':''}>${s.label}</option>`).join('')}</select></div><div class="field"><label>حجم مصرف/خرید بالقوه</label><input id="cVolume" type="number" value="${esc(c.estimatedVolume)}"></div><div class="field"><label>پیگیری بعدی</label>${jdatePickerHtml('cFollow',c.nextFollowUp,true)}</div><div class="field"><label>شرایط پرداخت مورد انتظار</label><input id="cPayment" value="${esc(c.paymentPreference)}"></div><div class="field"><label>رقیب/تأمین‌کننده فعلی</label><input id="cCompetitor" value="${esc(c.competitor)}"></div><div class="field full"><label>نیاز فنی یا محصول موردنیاز</label><textarea id="cNeed">${esc(c.technicalNeed)}</textarea></div><div class="field full"><label>یادداشت</label><textarea id="cNote">${esc(c.note)}</textarea></div><div class="full"><b style="font-size:12px">امتیازدهی ۱ تا ۵</b><div class="score-fields" style="margin-top:6px">${scoreSelect('تناسب محصول','fit',c.score?.fit)}${scoreSelect('حجم بالقوه','volume',c.score?.volume)}${scoreSelect('دسترسی تصمیم‌گیرنده','decision',c.score?.decision)}${scoreSelect('فوریت نیاز','urgency',c.score?.urgency)}${scoreSelect('توان پرداخت','payment',c.score?.payment)}</div></div><div class="full" style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn primary" onclick="saveCustomer('${id}')">ذخیره مشتری</button>${id?`<button class="btn danger" onclick="deleteCustomer('${id}')">حذف مشتری</button>`:''}</div></div>`)}
function scoreSelect(label,key,val=2){return `<div class="field"><label>${label}</label><select id="score_${key}">${[1,2,3,4,5].map(n=>`<option value="${n}" ${+val===n?'selected':''}>${n}</option>`).join('')}</select></div>`}
function saveCustomer(id){
  const existing=id?customerById(id):null,level=existing?customerAccessLevel(existing):'edit';
  if(existing&&accessRank(level)<2)return toast('این حساب فقط اجازه مشاهده این مشتری را دارد');
  const name=document.getElementById('cName').value.trim();if(!name)return toast('نام مشتری را وارد کنید');
  let data={contact:document.getElementById('cContact').value.trim(),phone:document.getElementById('cPhone').value.trim(),nextFollowUp:document.getElementById('cFollow').value,updatedAt:new Date().toISOString()};
  if(level==='edit')Object.assign(data,{name,industry:document.getElementById('cIndustry').value.trim(),city:document.getElementById('cCity').value.trim(),source:document.getElementById('cSource').value.trim(),stage:document.getElementById('cStage').value,estimatedVolume:+document.getElementById('cVolume').value||0,technicalNeed:document.getElementById('cNeed').value.trim()});
  if(String(window.HIPPO_AUTH?.role)==='manager'&&canPermission('state.view_full'))Object.assign(data,{address:document.getElementById('cAddress').value.trim(),assignee:document.getElementById('cAssignee').value,paymentPreference:document.getElementById('cPayment').value.trim(),competitor:document.getElementById('cCompetitor').value.trim(),note:document.getElementById('cNote').value.trim(),score:{fit:+document.getElementById('score_fit').value,volume:+document.getElementById('score_volume').value,decision:+document.getElementById('score_decision').value,urgency:+document.getElementById('score_urgency').value,payment:+document.getElementById('score_payment').value}});
  if(id)Object.assign(existing,data);else state.customers.push({id:'c'+Date.now(),createdAt:new Date().toISOString(),...data});
  save();closeModal();renderAll();toast('پرونده مشتری ذخیره شد');
}
function deleteCustomer(id){if(!canPermission('customers.delete'))return toast('مجوز حذف مشتری را ندارید');if(!confirm('مشتری و تمام تعامل‌های او حذف شود؟'))return;state.customers=state.customers.filter(c=>c.id!==id);state.interactions=state.interactions.filter(i=>i.customerId!==id);save();closeModal();renderAll();toast('مشتری حذف شد')}
/* ===== ردیابی سفارش: تولید → تحویل → نتیجه، روی همان تعامل سفارش/خرید ===== */
function openFulfillmentModal(interactionId){if(!canManageOrders())return toast('اطلاعات سفارش فقط برای مدیر مجاز است');const i=state.interactions.find(x=>x.id===interactionId);if(!i)return;const c=customerById(i.customerId);const f=i.fulfillment||{};const p=f.production||{},d=f.delivery||{},o=f.outcome||{};openModal(`<div class="modal-head"><h3>سفارش ${esc(c?.name||'مشتری حذف‌شده')}</h3><button class="icon-btn" onclick="closeModal()">×</button></div><p class="mini muted" style="margin-top:4px">ثبت‌شده ${faDate(i.date)} · ${esc(interactionReplies(i).map(r=>r.label).join('، ')||'سفارش')}</p><div class="modal-grid" style="margin-top:10px"><div class="field full"><b style="font-size:12px">تولید</b></div><div class="field"><label>منبع</label><select id="fSource"><option value="">مشخص نشده</option>${PRODUCTION_SOURCES.map(s=>`<option value="${s[0]}" ${p.source===s[0]?'selected':''}>${s[1]}</option>`).join('')}</select></div><div class="field"><label>مسئول تولید</label><select id="fPAssignee"><option value="">—</option>${state.team.map(m=>`<option value="${m.id}" ${p.assignee===m.id?'selected':''}>${esc(m.name)}</option>`).join('')}</select></div><div class="field"><label>تاریخ تولید</label>${jdatePickerHtml('fPDate',p.date||'',true)}</div><div class="field"><label>شماره بچ/سری تولید</label><input id="fBatch" value="${esc(p.batchNo||'')}"></div><div class="field full"><label>مشخصات فنی</label><textarea id="fSpec">${esc(p.specNote||'')}</textarea></div><div class="field full"><b style="font-size:12px">تحویل</b></div><div class="field"><label>تاریخ تحویل</label>${jdatePickerHtml('fDDate',d.date||'',true)}</div><div class="field"><label>مسئول تحویل</label><select id="fDAssignee"><option value="">—</option>${state.team.map(m=>`<option value="${m.id}" ${d.assignee===m.id?'selected':''}>${esc(m.name)}</option>`).join('')}</select></div><div class="field full"><label>یادداشت تحویل</label><textarea id="fDNote">${esc(d.note||'')}</textarea></div><div class="field full"><b style="font-size:12px">نتیجه</b></div><div class="field"><label>وضعیت نهایی</label><select id="fOutcome"><option value="">در انتظار</option>${Object.entries(OUTCOME_TYPES).map(([k,v])=>`<option value="${k}" ${o.type===k?'selected':''}>${v.label}</option>`).join('')}</select></div><div class="field full"><label>توضیح نتیجه</label><textarea id="fONote" placeholder="مثلاً: چه ایرادی داشت، یا چرا تکرار نخرید">${esc(o.note||'')}</textarea></div><div class="full"><button class="btn primary" onclick="saveFulfillment('${i.id}')">ذخیره سفارش</button></div></div>`,true)}
function saveFulfillment(interactionId){if(!canManageOrders())return toast('ویرایش سفارش فقط برای مدیر مجاز است');const i=state.interactions.find(x=>x.id===interactionId);if(!i)return;i.fulfillment={production:{source:document.getElementById('fSource').value,assignee:document.getElementById('fPAssignee').value,date:document.getElementById('fPDate').value,batchNo:document.getElementById('fBatch').value.trim(),specNote:document.getElementById('fSpec').value.trim()},delivery:{date:document.getElementById('fDDate').value,assignee:document.getElementById('fDAssignee').value,note:document.getElementById('fDNote').value.trim()},outcome:{type:document.getElementById('fOutcome').value,note:document.getElementById('fONote').value.trim()}};save();closeModal();renderCustomers();toast('سفارش به‌روزرسانی شد')}
function orderBadgeHtml(i){if(!isOrderInteraction(i))return '';const s=orderStatus(i);return `<div style="margin-top:5px;display:flex;align-items:center;gap:6px;flex-wrap:wrap"><span class="badge ${s.color}">${s.label}</span>${canManageOrders()?`<button class="btn small" onclick="openFulfillmentModal('${i.id}')">مدیریت سفارش</button>`:''}</div>`}
function ordersDashboardHtml(){const orders=orderInteractions();const withStatus=orders.map(i=>({i,c:customerById(i.customerId),s:orderStatus(i)}));const needProd=withStatus.filter(x=>x.s.key==='need_production').length;const needDeliv=withStatus.filter(x=>x.s.key==='need_delivery').length;const needOutcome=withStatus.filter(x=>x.s.key==='need_outcome').length;const flagged=withStatus.filter(x=>x.s.key==='complaint'||x.s.key==='no_repeat');const rank={complaint:0,no_repeat:0,need_production:1,need_delivery:2,need_outcome:3};const open=withStatus.filter(x=>x.s.key!=='ok').sort((a,b)=>{const ra=rank[a.s.key]??9,rb=rank[b.s.key]??9;return ra!==rb?ra-rb:String(b.i.date).localeCompare(String(a.i.date))});const rows=open.length?open.map(({i,c,s})=>`<div class="order-list-row" onclick="openFulfillmentModal('${i.id}')"><div><b>${esc(c?.name||'مشتری حذف‌شده')}</b><span class="mini">${faDate(i.date)}${(s.key==='complaint'||s.key==='no_repeat')&&i.fulfillment?.outcome?.note?' · '+esc(i.fulfillment.outcome.note):''}</span></div><span class="badge ${s.color}">${s.label}</span></div>`).join(''):emptyState('سفارش بازی وجود ندارد','وقتی نتیجه‌ی یک «ثبت مذاکره» سفارش آزمایشی یا خرید باشد، اینجا برای پیگیری تولید و تحویل نشان داده می‌شود.');return `<div class="section-head"><div><h3>سفارش‌های در جریان</h3><p>از لحظه‌ی ثبت سفارش تا تولید، تحویل و نتیجه‌ی نهایی.</p></div></div><div class="grid stats" style="grid-template-columns:repeat(4,minmax(0,1fr))"><div class="card stat"><div class="stat-icon">↻</div><div><b>${fmt(orders.length)}</b><span>کل سفارش‌ها</span></div></div><div class="card stat"><div class="stat-icon" style="background:var(--warnSoft);color:var(--warn)">⚑</div><div><b>${fmt(needProd)}</b><span>در انتظار تولید</span></div></div><div class="card stat"><div class="stat-icon" style="background:var(--blueSoft);color:var(--blue)">→</div><div><b>${fmt(needDeliv+needOutcome)}</b><span>در مسیر تحویل/نتیجه</span></div></div><div class="card stat"><div class="stat-icon" style="background:var(--dangerSoft);color:var(--danger)">!</div><div><b>${fmt(flagged.length)}</b><span>نیازمند پیگیری</span></div></div></div><div style="margin-top:12px">${rows}</div>`}
/* ===== ورود دسته‌ای مشتری از CSV (خروجی Save-As اکسل) ===== */
/* اکسل/ویندوز فارسی گاهی ی/ک را با معادل عربی (ي/ك) یا ارقام را فارسی ذخیره می‌کند — از چشم نامرئی است ولی مقایسه‌ی دقیق را می‌شکند. */
function faNorm(s){return String(s||'').replace(/[ي]/g,'ی').replace(/[ك]/g,'ک').replace(/[۰-۹]/g,c=>String(c.charCodeAt(0)-1776)).replace(/[٠-٩]/g,c=>String(c.charCodeAt(0)-1632)).replace(/[‌‎‏]/g,' ').replace(/\s+/g,' ').trim()}
function normalizePhone(p){let d=faNorm(p).replace(/\D/g,'');if(d.startsWith('0098'))d=d.slice(4);else if(d.startsWith('98'))d=d.slice(2);if(d&&!d.startsWith('0'))d='0'+d;return d}
function parseCSV(text,forceDelim){text=text.replace(/^﻿/,'');const sepMatch=text.match(/^sep=(.)\r?\n/i);if(sepMatch){forceDelim=forceDelim||sepMatch[1];text=text.slice(sepMatch[0].length)}const firstLine=text.split(/\r?\n/,1)[0]||'';const delim=forceDelim||((firstLine.split(';').length>firstLine.split(',').length)?';':',');const rows=[];let row=[],field='',inQ=false;for(let i=0;i<text.length;i++){const c=text[i];if(inQ){if(c==='"'){if(text[i+1]==='"'){field+='"';i++}else inQ=false}else field+=c}else{if(c==='"')inQ=true;else if(c===delim){row.push(field);field=''}else if(c==='\n'||c==='\r'){if(c==='\r'&&text[i+1]==='\n')i++;row.push(field);field='';rows.push(row);row=[]}else field+=c}}if(field!==''||row.length){row.push(field);rows.push(row)}return rows.filter(r=>r.some(c=>String(c).trim()!==''))}
/* مسیر قدیمی ورود فقط CSV حذف شد؛ مسیر رسمی XLSX/CSV در assets/js/excel-import.js قرار دارد. */
/* ===== شناسایی مشتری تکراری (بر اساس شماره تماس یکسان) ===== */
function findDuplicateCustomerGroups(){const map={};state.customers.forEach(c=>{const p=normalizePhone(c.phone);if(!p)return;(map[p]=map[p]||[]).push(c)});return Object.values(map).filter(g=>g.length>1)}
function openDuplicateCustomersModal(){
  const groups=findDuplicateCustomerGroups();
  if(!groups.length){openModal('<div class="modal-head"><h3>مشتریان تکراری</h3><button class="icon-btn" onclick="closeModal()">×</button></div><div class="empty"><b>موردی پیدا نشد</b><span>هیچ دو مشتری با شماره تماس یکسان نیست.</span></div>');return}
  openModal(`<div class="modal-head"><h3>مشتریان تکراری (${fmt(groups.length)} گروه)</h3><button class="icon-btn" onclick="closeModal()">×</button></div><p style="font-size:12px;color:var(--muted)">این‌ها بر اساس شماره تماس یکسان پیدا شدند. اگر واقعاً یک نفرند، یکی را نگه دار و بقیه را حذف کن.</p>${groups.map(g=>`<div class="card" style="margin-top:10px;padding:10px">${g.map(c=>`<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:6px 0;border-bottom:1px dashed var(--line)"><div><b>${esc(c.name)}</b><div class="mini muted">${esc(c.phone)} · ${stageObj(c.stage).label} · ${esc(c.city||'')}</div></div><button class="btn small danger" onclick="deleteCustomer('${c.id}')">حذف</button></div>`).join('')}</div>`).join('')}`,true);
}
function openCustomerDetail(id){const c=customerById(id);if(!c)return;const ints=state.interactions.filter(i=>i.customerId===id).sort((a,b)=>String(b.date).localeCompare(String(a.date)));const last=ints[0],reviewed=isScoreReviewed(c),editAllowed=accessRank(customerAccessLevel(c))>=2;openModal(`<div class="v07-customer-detail-shell"><div class="v07-detail-hero"><div class="v07-detail-profile"><span class="v07-detail-avatar">${esc((c.name||'م').slice(0,1))}</span><div><span class="v04-kicker">پرونده مشتری</span><h2>${esc(c.name)}</h2><p>${esc(c.industry||'صنعت نامشخص')} · ${esc(c.city||'شهر نامشخص')}</p><div class="v07-detail-badges">${stageBadge(c.stage)}<span class="badge">مسئول: ${memberName(c.assignee)}</span><span class="badge blue">پیگیری: ${faDate(c.nextFollowUp)}</span><span class="badge ${reviewed?'ok':''}">امتیاز: ${reviewed?normalizedScore(c):'بررسی نشده'}</span></div></div></div><button class="icon-btn" onclick="closeModal()">×</button></div><div class="v07-detail-actions"><button class="btn primary" onclick="closeModal();prefillQuick('${c.id}')">＋ ثبت مذاکره</button>${editAllowed?`<button class="btn" onclick="closeModal();openCustomerModal('${c.id}')">ویرایش پرونده</button>`:''}<button class="btn soft" onclick="aiAnalyzeCustomer('${c.id}')">✦ تحلیل مشتری</button></div><div id="aiCustomerPanel" style="display:none"></div><div class="v07-detail-grid"><aside class="v07-detail-sidebar">${customerOrdersSummaryHtml(c)}<section class="v07-detail-section"><div class="v07-detail-section-head"><h4>اطلاعات تماس</h4></div><dl class="v07-detail-list"><div><dt>شخص تماس</dt><dd>${esc(c.contact||'—')}</dd></div><div><dt>شماره تماس</dt><dd>${esc(c.phone||'—')}</dd></div><div><dt>آدرس</dt><dd>${esc(c.address||'—')}</dd></div><div><dt>منبع آشنایی</dt><dd>${esc(c.source||'—')}</dd></div></dl></section><section class="v07-detail-section"><div class="v07-detail-section-head"><h4>پروفایل فروش</h4></div><dl class="v07-detail-list"><div><dt>حجم بالقوه</dt><dd>${c.estimatedVolume?fmt(c.estimatedVolume):'—'}</dd></div><div><dt>شرایط پرداخت</dt><dd>${esc(c.paymentPreference||'—')}</dd></div><div><dt>رقیب فعلی</dt><dd>${esc(c.competitor||'—')}</dd></div><div><dt>نیاز فنی</dt><dd>${esc(c.technicalNeed||'—')}</dd></div></dl></section>${last?.analysis?`<section class="v07-detail-section v07-detail-analysis"><span class="ai-label">آخرین تحلیل</span><h4>${esc(last.analysis.mainObstacle||'')}</h4><p>${esc(last.analysis.summary||'')}</p><p><b>اقدام بعدی:</b> ${esc(last.analysis.nextAction||'')}</p><span class="confidence">اطمینان ${esc(last.analysis.confidence||'')}</span></section>`:''}</aside><main class="v07-detail-main"><div class="v07-detail-section-head"><div><h3>تاریخچه تعامل‌ها</h3><p>${fmt(ints.length)} فعالیت ثبت‌شده برای این مشتری</p></div></div>${ints.length?`<div class="v07-timeline">${ints.map(i=>{const rs=interactionReplies(i);return `<article class="v07-timeline-item"><span class="v07-timeline-dot"></span><div class="v07-timeline-card"><div class="v07-timeline-head"><div><h4>${esc(rs.map(r=>r.label).join('، ')||'تعامل')}</h4><p>${faDateTime(i.date)} · ${memberName(i.memberId)} · ${CHANNELS.find(x=>x[0]===i.channel)?.[1]||''}</p></div>${orderBadgeHtml(i)}</div><p>${esc(i.note||i.analysis?.summary||'بدون توضیح')}</p><div class="v07-timeline-meta"><span class="badge">پیگیری ${faDate(i.nextFollowUp)}</span>${i.analysis?.managerDecisionRequired?'<span class="badge warn">نیازمند تصمیم مدیریت</span>':''}</div></div></article>`}).join('')}</div>`:emptyState('هنوز تعاملی ثبت نشده','نتیجه اولین تماس را ثبت کنید.',`<button class="btn primary" onclick="closeModal();prefillQuick('${c.id}')">ثبت اولین مذاکره</button>`)}</main></div></div>`,true)}
function renderAnalysis(){const week=+state.project.analysisWeek||state.project.activeWeek,a=buildWeeklyAnalysis(week);const max=a.ranked[0]?.[1]||1;const report=state.weeklyReports.find(r=>r.week===week)||{managerNote:'',approved:false};document.getElementById('page-analysis').innerHTML=`<div class="section-head"><div><h2>گزارش شخصی و تحلیل هفتگی</h2><p>مرور عملکرد و تصمیم‌های هفته برای کارشناس فروش.</p></div><div class="filters"><select onchange="state.project.analysisWeek=+this.value;save();renderAnalysis()">${state.weeks.map(w=>`<option value="${w.n}" ${w.n===week?'selected':''}>هفته ${w.n}</option>`).join('')}</select><button class="btn" onclick="copyWeeklyReport(${week})">کپی گزارش</button><button class="btn primary" onclick="saveWeeklyReport(${week})">ذخیره جمع‌بندی</button></div></div><div class="analysis-hero"><div class="summary-box"><h3>${esc(a.headline)}</h3><p>${esc(a.summary)}</p><ul class="decision-list">${a.recommendations.map(x=>`<li>← ${esc(x)}</li>`).join('')}</ul></div><div class="card"><h3 style="margin-top:0">شاخص‌های هفته</h3><div class="result-grid" style="grid-template-columns:1fr 1fr"><div class="result"><small>تعامل</small><b>${a.ints.length}</b></div><div class="result"><small>مشتری</small><b>${a.customers.length}</b></div><div class="result"><small>نمونه/فنی</small><b>${a.samples}</b></div><div class="result"><small>سفارش/خرید</small><b>${a.orders}</b></div></div></div></div><div class="section-head"><div><h3>تحلیل کل روند فروش با AI</h3><p>گلوگاه قیف، اثربخشی پاسخ‌های آماده و وضعیت تولید/تحویل/شکایت — یکجا.</p></div><button class="btn soft" onclick="aiAnalyzeProcess()">✦ تحلیل روند با AI</button></div><div id="aiProcessPanel" style="display:none;margin-bottom:14px"></div><div class="two-col" style="margin-top:14px"><div class="card"><h3 style="margin-top:0">دلایل و موانع پرتکرار</h3>${a.ranked.length?`<div class="bar-list">${a.ranked.slice(0,10).map(([id,n])=>{const r=replyById(id);return `<div class="bar-row"><span>${esc(r?.label||id)}</span><div class="bar-track"><div class="bar-fill" style="width:${Math.round(n/max*100)}%"></div></div><b>${n}</b></div>`}).join('')}</div>`:emptyState('داده‌ای وجود ندارد','تعامل‌های این هفته را ثبت کنید.')}</div><div class="card"><h3 style="margin-top:0">مشتریان و تصمیم‌های مهم</h3>${a.topCustomers.length?a.topCustomers.map(c=>`<div class="alert info clickable" onclick="openCustomerDetail('${c.id}')"><b>${esc(c.name)}</b><div class="mini">${isScoreReviewed(c)?'امتیاز '+normalizedScore(c):'بررسی‌نشده'} · ${stageObj(c.stage).label} · پیگیری ${faDate(c.nextFollowUp)}</div></div>`).join(''):emptyState('مشتری اولویت‌دار مشخص نیست','پس از ثبت تعامل و امتیاز مشتری نمایش داده می‌شود.')}${a.decisions.length?`<div class="alert warn"><b>نیازمند تصمیم مدیریت</b><div class="mini">${esc([...new Set(a.decisions)].join('، '))}</div></div>`:''}</div></div><div class="section-head"><div><h3>یادداشت جمع‌بندی شخصی</h3><p>نتیجه و تصمیم این هفته را برای مرور بعدی ثبت کنید.</p></div></div><div class="card"><div class="field"><label>یادداشت این هفته</label><textarea id="managerNote">${esc(report.managerNote||'')}</textarea></div><label style="display:flex;align-items:center;gap:7px;margin-top:9px;font-size:12px"><input id="reportApproved" type="checkbox" ${report.approved?'checked':''}> جمع‌بندی این هفته ثبت شد</label></div><div class="section-head"><div><h3>تعامل‌های هفته ${week}</h3></div><button class="btn small" onclick="exportInteractionsCSV(${week})">خروجی CSV</button></div><div class="card table-wrap">${a.ints.length?recentInteractionTable(100,'',week):emptyState('ثبت مذاکره‌ای وجود ندارد','')}</div>`}
function weeklyReportText(week){const a=buildWeeklyAnalysis(week);return `گزارش هفته ${week}\n${a.headline}\n${a.summary}\n\nاقدامات پیشنهادی:\n${a.recommendations.map((x,i)=>`${i+1}. ${x}`).join('\n')}\n\nمشتریان اولویت‌دار: ${a.topCustomers.map(c=>c.name).join('، ')||'ثبت نشده'}`}
function copyWeeklyReport(w){copyText(weeklyReportText(w))}
function saveWeeklyReport(w){const note=document.getElementById('managerNote').value.trim(),approved=document.getElementById('reportApproved').checked;let r=state.weeklyReports.find(x=>x.week===w);if(r)Object.assign(r,{managerNote:note,approved,updatedAt:new Date().toISOString(),snapshot:buildWeeklyAnalysis(w)});else state.weeklyReports.push({week:w,managerNote:note,approved,createdAt:new Date().toISOString(),snapshot:buildWeeklyAnalysis(w)});save();toast('جمع‌بندی هفتگی ذخیره شد')}
function renderWeeks(){const w=state.weeks[selectedWeek-1],p=weekProgress(w);document.getElementById('page-weeks').innerHTML=`<div class="section-head"><div><h2>برنامه اجرایی ۱۲ هفته‌ای</h2><p>هر کار باید قابل اجرا، قابل اندازه‌گیری و دارای نتیجه ثبت‌شده باشد.</p></div><button class="btn primary" onclick="setActiveWeek(${w.n})">تعیین هفته ${w.n} به‌عنوان فعال</button></div><div class="week-selector">${state.weeks.map(x=>`<button class="week-chip ${x.n===selectedWeek?'active':''}" onclick="selectWeek(${x.n})">هفته ${x.n} · ${weekProgress(x)}٪</button>`).join('')}</div><div class="hero"><div class="hero-content"><div><h1>هفته ${w.n}: ${esc(w.title)}</h1><p>${esc(w.subtitle)}</p></div><div class="hero-box"><span>پیشرفت این هفته</span><strong>${p}٪</strong></div></div></div><div class="two-col" style="margin-top:14px"><div class="card"><h3 style="margin-top:0">هدف هفته</h3><p>${esc(w.goal)}</p></div><div class="card"><h3 style="margin-top:0">اصل تصمیم</h3><p>${esc(w.principle)}</p></div></div><div class="section-head"><div><h3>کارهای اجرایی</h3><p>مسئول، وضعیت و نتیجه هر کار را ثبت کنید.</p></div><button class="btn small" onclick="openTaskModal(${w.n})">＋ کار جدید</button></div><div class="card">${w.tasks.map(t=>`<div class="task"><button class="check ${t.status==='done'?'done':''}" onclick="toggleTask('${t.id}',${w.n})">${t.status==='done'?'✓':''}</button><div class="task-title ${t.status==='done'?'done':''}">${esc(t.text)} ${mgrBadge(t)}</div><select class="status-select" onchange="updateTask('${t.id}',${w.n},'status',this.value)">${['not_started','in_progress','done','blocked','decision'].map(s=>`<option value="${s}" ${s===t.status?'selected':''}>${taskStatusLabel(s)}</option>`).join('')}</select><select class="assignee" onchange="updateTask('${t.id}',${w.n},'assignee',this.value)">${state.team.map(m=>`<option value="${m.id}" ${m.id===t.assignee?'selected':''}>${esc(m.name)}</option>`).join('')}</select><button class="icon-btn" onclick="deleteTask('${t.id}',${w.n})">×</button><div class="task-note"><textarea placeholder="نتیجه، مانع یا تصمیم این کار..." oninput="updateTask('${t.id}',${w.n},'note',this.value,false)">${esc(t.note)}</textarea></div></div>`).join('')}</div><div class="section-head"><div><h3>خروجی‌های قابل تحویل</h3></div></div><div class="outputs">${w.outputs.map(o=>`<label class="output"><input type="checkbox" ${o.done?'checked':''} onchange="toggleOutput(${w.n},'${o.id}',this.checked)"><span>${esc(o.text)}</span></label>`).join('')}</div><div class="section-head"><div><h3>شاخص‌های هفته</h3><p>هدف پیشنهادی را با عدد واقعی مقایسه کنید.</p></div></div><div class="metric-grid">${w.metrics.map(m=>`<div class="metric"><label>${esc(m.name)}</label><b>هدف: ${esc(m.target)}</b><input placeholder="عدد واقعی" value="${esc(m.actual)}" oninput="updateMetric(${w.n},'${m.id}',this.value)"></div>`).join('')}</div><div class="section-head"><div><h3>جمع‌بندی هفته</h3></div></div><div class="card notes"><textarea placeholder="چه چیزی یاد گرفتیم؟ چه چیزی باید هفته بعد تغییر کند؟" oninput="state.weeks[${w.n-1}].notes=this.value;save()">${esc(w.notes)}</textarea></div>`}
function updateTask(id,wn,key,val,rerender=true){const t=state.weeks[wn-1].tasks.find(x=>x.id===id);if(!t)return;t[key]=val;save();if(rerender)renderAll()}
function toggleTask(id,wn){const t=state.weeks[wn-1].tasks.find(x=>x.id===id);t.status=t.status==='done'?'not_started':'done';save();renderAll()}
function deleteTask(id,wn){const w=state.weeks[wn-1],t=w.tasks.find(x=>x.id===id);if(!t?.custom&&!confirm('این کار پایه برنامه است. حذف شود؟'))return;if(t?.custom||confirm('حذف شود؟')){w.tasks=w.tasks.filter(x=>x.id!==id);save();renderAll()}}
function toggleOutput(wn,id,done){state.weeks[wn-1].outputs.find(o=>o.id===id).done=done;save();renderAll()}
function updateMetric(wn,id,val){state.weeks[wn-1].metrics.find(m=>m.id===id).actual=val;save()}
function openTaskModal(week=selectedWeek){openModal(`<div class="modal-head"><h3>افزودن کار جدید</h3><button class="icon-btn" onclick="closeModal()">×</button></div><div class="modal-grid"><div class="field"><label>هفته</label><select id="newTaskWeek">${state.weeks.map(w=>`<option value="${w.n}" ${w.n===week?'selected':''}>هفته ${w.n}</option>`).join('')}</select></div><div class="field"><label>مسئول</label><select id="newTaskAssignee">${state.team.map(m=>`<option value="${m.id}">${esc(m.name)}</option>`).join('')}</select></div><div class="field full"><label>شرح کار</label><textarea id="newTaskText" placeholder="کار دقیق، قابل اجرا و قابل اندازه‌گیری..."></textarea></div><div class="full"><button class="btn primary" onclick="addTask()">افزودن کار</button></div></div>`)}
function addTask(){const wn=+document.getElementById('newTaskWeek').value,text=document.getElementById('newTaskText').value.trim(),assignee=document.getElementById('newTaskAssignee').value;if(!text)return toast('شرح کار را وارد کنید');state.weeks[wn-1].tasks.push({id:'custom'+Date.now(),text,status:'not_started',assignee,note:'',custom:true});selectedWeek=wn;save();closeModal();renderAll();showPage('weeks')}
/* ===== کار سریع از طرف مدیر (نقش viewer) ===== */
function mgrScore(t){return t&&t.fromManager?(t.priority==='urgent'?2:1):0}
function mgrBadge(t){if(!t||!t.fromManager)return'';return `<span class="mgr-tag ${t.priority==='urgent'?'urgent':''}">${t.priority==='urgent'?'🔴 فوری از مدیر':'👤 از مدیر'}</span>`}
let _mgrPriority='normal';
function openManagerTaskModal(){
  _mgrPriority='normal';
  openModal(`<div class="modal-head"><h3>ثبت کار برای امین</h3><button class="icon-btn" onclick="closeModal()">×</button></div>
  <div class="modal-grid">
  <div class="field full"><label>شرح کار</label><textarea id="mgrTaskText" placeholder="مثلاً: فردا ساعت ۹ جلسه با ..."></textarea></div>
  <div class="field full"><label>اولویت</label><div style="display:flex;gap:8px">
  <button type="button" id="mgrPrioNormal" class="btn soft" onclick="setMgrPriority('normal')" style="flex:1">عادی</button>
  <button type="button" id="mgrPrioUrgent" class="btn soft" onclick="setMgrPriority('urgent')" style="flex:1">🔴 فوری</button>
  </div></div>
  <div class="full"><button class="btn primary" id="mgrTaskSubmitBtn" onclick="submitManagerTask()">ثبت کار برای امین</button></div>
  </div>`);
  setMgrPriority('normal');
}
function setMgrPriority(p){_mgrPriority=p;const n=document.getElementById('mgrPrioNormal'),u=document.getElementById('mgrPrioUrgent');if(n)n.style.cssText=p==='normal'?'flex:1;background:var(--color-primary);color:#fff;border-color:var(--color-primary)':'flex:1';if(u)u.style.cssText=p==='urgent'?'flex:1;background:#c0392b;color:#fff;border-color:#c0392b':'flex:1'}
async function submitManagerTask(){
  const ta=document.getElementById('mgrTaskText'),text=ta?ta.value.trim():'';
  if(!text)return toast('شرح کار را وارد کنید');
  const btn=document.getElementById('mgrTaskSubmitBtn');
  if(btn){btn.disabled=true;btn.textContent='در حال ثبت...'}
  try{
    const r=await fetch('api.php?action=addManagerTask',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({text,priority:_mgrPriority})});
    const j=await r.json().catch(()=>null);
    if(j&&j.ok){closeModal();toast('کار برای امین ثبت شد');await serverLoadState()}
    else{toast('ثبت نشد؛ دوباره تلاش کن')}
  }catch(e){toast('خطا در ارتباط با سرور')}
  if(btn){btn.disabled=false;btn.textContent='ثبت کار برای امین'}
}

function renderTeam(){const all=state.weeks.flatMap(w=>w.tasks.map(t=>({...t,week:w.n})));document.getElementById('page-team').innerHTML=`<div class="section-head"><div><h2>اعضا، نقش‌ها و نمای دسترسی</h2><p>نقش کاربران از ورود و Session تشخیص داده می‌شود. مدیریت دسترسی واقعی در داشبورد مدیر نمایش داده می‌شود.</p></div><button class="btn primary" onclick="openMemberModal()">＋ عضو جدید</button></div><div class="team-grid">${state.team.map(m=>{const ts=all.filter(t=>t.assignee===m.id),cs=state.customers.filter(c=>c.assignee===m.id);return `<div class="member"><div class="member-head"><div class="avatar">${esc(m.name.slice(0,1))}</div><div><h3>${esc(m.name)}</h3><p>${esc(m.role)}</p></div></div><div class="member-stats"><span class="badge">${roleLabel(m.access)}</span><span class="badge">${ts.length} کار</span><span class="badge blue">${cs.length} مشتری</span><span class="badge ok">${ts.filter(t=>t.status==='done').length} تکمیل</span></div><div class="member-actions"><button class="btn small" onclick="openMemberModal('${m.id}')">ویرایش</button>${state.team.length>1?`<button class="btn danger small" onclick="deleteMember('${m.id}')">حذف</button>`:''}</div></div>`}).join('')}</div><div class="section-head"><div><h3>خلاصه بار کاری</h3></div></div><div class="card table-wrap"><table class="data-table"><thead><tr><th>عضو</th><th>نقش دسترسی</th><th>کار باز</th><th>مشتری</th><th>تعامل</th><th>مانع</th></tr></thead><tbody>${state.team.map(m=>{const ts=all.filter(t=>t.assignee===m.id),cs=state.customers.filter(c=>c.assignee===m.id),ins=state.interactions.filter(i=>i.memberId===m.id);return `<tr><td data-label="عضو"><b>${esc(m.name)}</b></td><td data-label="نقش">${roleLabel(m.access)}</td><td data-label="کار باز">${ts.filter(t=>t.status!=='done').length}</td><td data-label="مشتری">${cs.length}</td><td data-label="تعامل">${ins.length}</td><td data-label="مانع">${ts.filter(t=>t.status==='blocked').length}</td></tr>`}).join('')}</tbody></table></div>`}
function openMemberModal(id=''){const m=id?state.team.find(x=>x.id===id):{name:'',role:'',access:'marketer'};openModal(`<div class="modal-head"><h3>${id?'ویرایش عضو':'افزودن عضو'}</h3><button class="icon-btn" onclick="closeModal()">×</button></div><div class="modal-grid"><div class="field"><label>نام</label><input id="memberName" value="${esc(m.name)}"></div><div class="field"><label>عنوان شغلی</label><input id="memberRole" value="${esc(m.role)}"></div><div class="field full"><label>نقش دسترسی</label><select id="memberAccess">${ROLE_OPTIONS.map(x=>`<option value="${x[0]}" ${m.access===x[0]?'selected':''}>${x[1]}</option>`).join('')}</select></div><div class="full"><button class="btn primary" onclick="saveMember('${id}')">ذخیره عضو</button></div></div>`)}
function saveMember(id){const name=document.getElementById('memberName').value.trim(),role=document.getElementById('memberRole').value.trim(),access=document.getElementById('memberAccess').value;if(!name)return toast('نام را وارد کنید');if(id)Object.assign(state.team.find(m=>m.id===id),{name,role,access});else state.team.push({id:'m'+Date.now(),name,role,access});save();closeModal();renderAll()}
function deleteMember(id){if(!confirm('عضو حذف شود؟ کارها و مشتریان او به اولین عضو منتقل می‌شوند.'))return;const fallback=state.team.find(m=>m.id!==id)?.id||'';state.weeks.forEach(w=>w.tasks.forEach(t=>{if(t.assignee===id)t.assignee=fallback}));state.customers.forEach(c=>{if(c.assignee===id)c.assignee=fallback});state.interactions.forEach(i=>{if(i.memberId===id)i.memberId=fallback});state.team=state.team.filter(m=>m.id!==id);if(state.project.currentMemberId===id)state.project.currentMemberId=fallback;save();renderAll()}
function renderFormula(){if(!state.formula){const el=document.getElementById('page-formula');if(el)el.innerHTML=emptyState('این بخش در دسترس نیست','اطلاعات مالی و مدیریتی برای این حساب ارسال نشده است.');return}const f=state.formula,available=+f.initialInventory + +f.production,end=available-+f.confirmedSales,totalRevenue=+f.cashQty*+f.cashPrice + +f.creditQty*+f.creditPrice,cashIn=+f.cashQty*+f.cashPrice + (+f.creditQty*+f.creditPrice*(+f.collectionRate/100)),materialCost=+f.materialQty*+f.materialPrice,cashFlow=cashIn-materialCost-(+f.operatingExpenses),weightedQty=+f.cashQty + +f.creditQty,avgPrice=weightedQty?totalRevenue/weightedQty:0;document.getElementById('page-formula').innerHTML=`<div class="hero"><div class="hero-content"><div><h1>فرمول تصمیم‌گیری پایان هفته دوازدهم</h1><p>قیمت و فروش از قبل تحمیل نمی‌شود؛ موجودی، تولید، وصول، خرید مواد اولیه و جریان نقدی کنار هم سنجیده می‌شوند.</p></div></div></div><div class="section-head"><div><h2>مدل عملیاتی و نقدینگی</h2><p>همه اعداد را با یک واحد ثابت وارد کنید.</p></div><button class="btn primary" onclick="saveNow()">ذخیره محاسبات</button></div><div class="formula-grid"><div class="formula-box"><h3>موجودی، تولید و فروش</h3><div class="formula-line">موجودی اول دوره + تولید قابل تحویل − فروش قطعی = موجودی پایان دوره</div><div class="input-grid">${numField('موجودی اول دوره','initialInventory',f.initialInventory)}${numField('تولید قابل تحویل','production',f.production)}${numField('فروش قطعی','confirmedSales',f.confirmedSales)}</div><div class="result-grid"><div class="result"><small>کالای در دسترس</small><b>${fmt(available)}</b></div><div class="result"><small>موجودی پایان دوره</small><b>${fmt(end)}</b></div><div class="result"><small>وضعیت</small><b>${end<0?'کمبود':end===0?'تعادل':'موجودی باقی‌مانده'}</b></div></div></div><div class="formula-box"><h3>جریان نقدی واقعی</h3><div class="formula-line">نقد دریافتی − خرید نقدی مواد اولیه − هزینه‌های جاری = جریان نقدی</div><div class="input-grid">${numField('مقدار فروش نقدی','cashQty',f.cashQty)}${numField('قیمت فروش نقدی','cashPrice',f.cashPrice)}${numField('مقدار فروش اعتباری','creditQty',f.creditQty)}${numField('قیمت فروش اعتباری','creditPrice',f.creditPrice)}${numField('درصد وصول فعلی','collectionRate',f.collectionRate)}${numField('مقدار خرید مواد','materialQty',f.materialQty)}${numField('قیمت مواد اولیه','materialPrice',f.materialPrice)}${numField('هزینه جاری','operatingExpenses',f.operatingExpenses)}</div><div class="result-grid"><div class="result"><small>فروش کل</small><b>${fmt(totalRevenue)}</b></div><div class="result"><small>نقد ورودی</small><b>${fmt(cashIn)}</b></div><div class="result"><small>جریان نقدی</small><b>${fmt(cashFlow)}</b></div><div class="result"><small>قیمت میانگین</small><b>${fmt(avgPrice)}</b></div></div></div></div><div class="section-head"><div><h2>سناریوهای قابل مقایسه</h2><p>مثال قیمت کمتر و نقدی فقط یک سناریو است؛ تصمیم نهایی با داده واقعی گرفته می‌شود.</p></div></div><div class="card table-wrap"><table class="scenario-table"><thead><tr><th>سناریو</th><th>قیمت</th><th>مقدار</th><th>درصد نقدی</th><th>حاشیه سود</th><th>نقد ورودی</th></tr></thead><tbody>${f.scenarios.map((s,i)=>`<tr><td><b>${esc(s.name)}</b></td><td><input type="number" value="${s.price}" onchange="updateScenario(${i},'price',this.value)"></td><td><input type="number" value="${s.qty}" onchange="updateScenario(${i},'qty',this.value)"></td><td><input type="number" value="${s.cashRate}" onchange="updateScenario(${i},'cashRate',this.value)"></td><td><input type="number" value="${s.margin}" onchange="updateScenario(${i},'margin',this.value)"></td><td>${fmt(s.price*s.qty*s.cashRate/100)}</td></tr>`).join('')}</tbody></table></div><div class="section-head"><div><h3>جمع‌بندی مدل فروش پیشنهادی</h3></div></div><div class="card notes"><textarea placeholder="قیمت هدف، شرایط پرداخت، حجم، مشتری اولویت‌دار، خرید مواد اولیه و ریسک‌ها..." oninput="state.formula.notes=this.value;save()">${esc(f.notes)}</textarea></div>`}
function numField(label,key,val){return `<div class="field"><label>${label}</label><input type="number" value="${val}" onchange="state.formula.${key}=+this.value;save();renderFormula()"></div>`}
function updateScenario(i,key,val){state.formula.scenarios[i][key]=+val;save();renderFormula()}
function renderSettings(){document.getElementById('page-settings').innerHTML=`<div class="section-head"><div><h2>تنظیمات، انتقال داده و راهنمای نسخه</h2><p>نسخه پایلوت متصل به سرور برای تست واقعی فرآیند و کشف نیازهای محصول.</p></div></div><div class="two-col"><div class="card"><h3 style="margin-top:0">اطلاعات پروژه</h3><div class="field"><label>نام پروژه/شرکت</label><input value="${esc(state.project.name)}" onchange="state.project.name=this.value;save();renderDashboard()"></div><div class="field" style="margin-top:9px"><label>تاریخ شروع</label>${jdatePickerHtml('projStart',state.project.startDate,true,"state.project.startDate=document.getElementById('projStart').value;save()")}</div><div class="field" style="margin-top:9px"><label>هفته فعال</label><select onchange="setActiveWeek(+this.value)">${state.weeks.map(w=>`<option value="${w.n}" ${w.n===state.project.activeWeek?'selected':''}>هفته ${w.n}</option>`).join('')}</select></div><p class="mini muted">آخرین ذخیره: ${esc(state.project.lastSaved||'هنوز ذخیره نشده')}</p></div><div class="card"><h3 style="margin-top:0">پشتیبان و خروجی</h3><p class="mini muted">داده ابتدا در مرورگر و سپس با Revision روی سرور ذخیره می‌شود. برای انتقال، بازیابی اضطراری یا تحلیل نیز فایل خروجی بگیرید.</p><div style="display:flex;gap:7px;flex-wrap:wrap"><button class="btn primary" onclick="exportForAI()">⤓ خروجی برای تحلیل هوش مصنوعی</button><button class="btn" onclick="exportData()">دانلود پشتیبان JSON</button>${String(window.HIPPO_AUTH?.role)==='manager'&&canPermission('backups.view')&&canPermission('state.view_full')?`<button class="btn warn" onclick="openServerBackups()">نسخه‌های سرور و بازیابی</button>`:''}<label class="btn" for="importFile2">ورود پشتیبان</label><input id="importFile2" type="file" accept="application/json" hidden onchange="importData(event)"><button class="btn" onclick="exportCustomersCSV()">CSV مشتریان</button><button class="btn" onclick="exportInteractionsCSV()">CSV تعامل‌ها</button><button class="btn soft" onclick="seedDemoCustomers()">＋ افزودن ۲۰ مشتری نمونه</button><button class="btn" onclick="window.print()">چاپ صفحه فعال</button><button class="btn danger" onclick="resetData()">بازنشانی</button></div></div></div><div class="section-head"><div><h3>چه چیزهایی در این نسخه اضافه شده؟</h3></div></div><div class="three-col"><div class="card"><h3 style="margin-top:0">CRM ساده</h3><p class="mini">پرونده مشتری، قیف فروش، امتیاز، مسئول، پیگیری و تاریخچه تعامل.</p></div><div class="card"><h3 style="margin-top:0">ثبت زیر ۶۰ ثانیه</h3><p class="mini">دلایل و پاسخ‌های آماده، توضیح اختیاری، پیگیری و به‌روزرسانی خودکار مرحله.</p></div><div class="card"><h3 style="margin-top:0">تحلیل هفتگی</h3><p class="mini">مانع پرتکرار، مشتری اولویت‌دار، اقدام هفته بعد و موارد نیازمند تصمیم مدیریت.</p></div></div><div class="section-head"><div><h3>محدودیت‌های صادقانه</h3></div></div><div class="card"><div class="alert warn"><b>نقش از حساب ورود تشخیص داده می‌شود</b><div class="mini">در این نسخه انتخاب نمایشی نقش حذف شده و دسترسی رابط براساس Session تعیین می‌شود.</div></div><div class="alert info"><b>API هوش مصنوعی وصل نیست</b><div class="mini">تحلیل‌های فعلی از قواعد، امتیاز و دلایل ثبت‌شده ساخته می‌شوند. بعداً می‌توان همین ساختار را به API متصل کرد تا خلاصه و پیشنهاد دقیق‌تر شود.</div></div><div class="alert ok"><b>برای پایلوت مناسب است</b><div class="mini">همین نسخه می‌تواند نشان دهد بازاریاب چه چیزی ثبت می‌کند، مدیر چه گزارشی می‌خواند و چه قابلیت‌هایی واقعاً ارزش ساخت آنلاین دارند.</div></div></div><div class="section-head"><div><h3>اصول غیرقابل مذاکره محصول</h3></div></div><div class="outputs">${['اول نتیجه، بعد هزینه','ثبت فعالیت در کمتر از یک دقیقه','اجرای مرحله‌ای و آزمایش واقعی','تحلیل باید به اقدام مشخص برسد','خلاصه قبل از جزئیات','انسان در حلقه تصمیم','زبان ساده فروش، نه اصطلاحات پیچیده','موبایل‌محور و حداقل کلیک'].map(x=>`<div class="output"><span>✓</span><b>${x}</b></div>`).join('')}</div>`}
const renderSettingsBaseV03=renderSettings;
renderSettings=function(){
  renderSettingsBaseV03();
  document.getElementById('page-settings').insertAdjacentHTML('beforeend',`<div class="section-head"><div><h3>تنظیمات رابط</h3><p>این کنترل‌ها فقط نمونه عملی اجزای Design System هستند و منطق کسب‌وکار را تغییر نمی‌دهند.</p></div></div><div class="card"><div class="two-col"><div><b>نمای فشرده فهرست‌ها</b><p class="mini muted">برای نمایش تعداد بیشتر ردیف‌ها در صفحه.</p><label class="ui-switch"><input type="checkbox" onchange="document.body.classList.toggle('compact-ui',this.checked)"><span class="ui-switch-track"></span><span>فعال</span></label></div><fieldset style="border:0;padding:0;margin:0"><legend style="font-size:12px;font-weight:800;margin-bottom:8px">تراکم نمایش</legend><label style="display:flex;align-items:center;gap:8px;margin:8px 0"><input type="radio" name="density" checked> استاندارد</label><label style="display:flex;align-items:center;gap:8px;margin:8px 0"><input type="radio" name="density"> راحت</label></fieldset></div><div class="pagination" aria-label="نمونه صفحه‌بندی"><button class="active">۱</button><button>۲</button><button>۳</button><button aria-label="صفحه بعد">‹</button></div><div style="margin-top:16px"><button class="btn danger" onclick="V03UI.confirmDialog({title:'بازنشانی تنظیمات رابط',message:'تنظیمات نمایشی این مرورگر به حالت استاندارد برگردد؟',confirmText:'بازنشانی',danger:true,onConfirm:()=>{document.body.classList.remove('compact-ui');toast('تنظیمات رابط بازنشانی شد')}})">بازنشانی تنظیمات رابط</button></div></div>`);
};
function openModal(content,wide=false){const mc=document.getElementById('modalContent');mc.className='modal-card'+(wide?' wide':'');mc.innerHTML=content;document.getElementById('modal').classList.add('open')}
function closeModal(){document.getElementById('modal').classList.remove('open')}
function copyReplyText(id){copyText(replyById(id)?.response||'')}
function copyInteractionResponse(id){copyText(state.interactions.find(i=>i.id===id)?.analysis?.suggestedResponse||'')}
function copyText(text){if(navigator.clipboard?.writeText){navigator.clipboard.writeText(text).then(()=>toast('متن کپی شد')).catch(()=>fallbackCopy(text))}else fallbackCopy(text)}
function fallbackCopy(text){const t=document.createElement('textarea');t.value=text;document.body.appendChild(t);t.select();document.execCommand('copy');t.remove();toast('متن کپی شد')}
function exportData(){save();const blob=new Blob([JSON.stringify(recoveryEnvelope(),null,2)],{type:'application/json;charset=utf-8'}),a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=`hippo-sales-assistant-${new Date().toISOString().slice(0,10)}.json`;a.click();URL.revokeObjectURL(a.href)}
function aiSummary(){const cs=state.customers,ins=state.interactions;const byStage={};STAGES.forEach(s=>byStage[s.label]=cs.filter(c=>c.stage===s.id).length);const obs=topObstacle(ins);return {عنوان:'خروجی داده دستیار فروش Hippo Plast برای تحلیل',تاریخ_خروجی:new Date().toLocaleString('fa-IR'),نام_پروژه:state.project.name,هفته_فعال:state.project.activeWeek,پیشرفت_برنامه_درصد:overallProgress(),تعداد_مشتری:cs.length,تعداد_تعامل:ins.length,مشتری_بر_حسب_مرحله:byStage,مانع_پرتکرار:obs.label,پیگیری_سررسید:dueCustomers().length,راهنما:'این فایل را در گفتگوی Claude آپلود کنید و بخواهید داده‌ها را تحلیل کند و پیشنهاد اقدام بدهد.'}}
function exportForAI(){save();const payload={meta:aiSummary(),data:state};const blob=new Blob([JSON.stringify(payload,null,2)],{type:'application/json;charset=utf-8'}),a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=`hippo-tahlil-${new Date().toISOString().slice(0,10)}.json`;a.click();URL.revokeObjectURL(a.href);toast('فایل تحلیل ساخته شد؛ آن را برای هوش مصنوعی آپلود کنید')}
function importData(e){const f=e.target.files[0];if(!f)return;const r=new FileReader();r.onload=()=>{try{const parsed=JSON.parse(r.result),meta=parsed?._hippo_recovery,data=parsed?.data;const isManager=String(window.HIPPO_AUTH?.role)==='manager'&&canPermission('state.save_full');if(!meta&&!isManager)throw new Error('scope_missing');if(meta&&(Number(meta.user_id)!==Number(window.HIPPO_AUTH?.id)||String(meta.role)!==String(window.HIPPO_AUTH?.role)||String(meta.permission_fingerprint)!==String(window.HIPPO_AUTH?.permission_fingerprint)))throw new Error('scope_mismatch');state=sanitizeStateForCurrentScope(mergeState(data||parsed));selectedWeek=state.project.activeWeek||1;save();renderAll();toast('اطلاعات همین حساب وارد شد')}catch(err){alert('این فایل Recovery متعلق به حساب یا سطح دسترسی فعلی نیست.')}};r.readAsText(f);e.target.value=''}
/* خروجی مرکزی CSV: UTF-8 با BOM، Quote استاندارد و خنثی‌سازی Formula Injection برای Excel و LibreOffice. */
function csvSafeCell(v){const raw=String(v??'');const probe=raw.replace(/^[\u0000-\u0020\u007f-\u009f]+/,'');return /^[=+\-@]/.test(probe)||/^[\t\r\n]/.test(raw)?"'"+raw:raw}
function csvDownload(name,rows){const q=v=>'"'+csvSafeCell(v).replace(/"/g,'""')+'"',text='\uFEFF'+rows.map(r=>r.map(q).join(',')).join('\r\n'),blob=new Blob([new TextEncoder().encode(text)],{type:'text/csv;charset=utf-8'}),a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=name;a.click();URL.revokeObjectURL(a.href)}
function exportCustomersCSV(){csvDownload('hippo-customers.csv',[['نام','صنعت','شهر','تماس','تلفن','مرحله','مسئول','حجم بالقوه','پیگیری','امتیاز'],...state.customers.map(c=>[c.name,c.industry,c.city,c.contact,c.phone,stageObj(c.stage).label,memberName(c.assignee),c.estimatedVolume,c.nextFollowUp,normalizedScore(c)])]);toast('خروجی مشتریان ساخته شد')}
function exportInteractionsCSV(week=''){const xs=state.interactions.filter(i=>!week||+i.week===+week);csvDownload('hippo-interactions.csv',[['تاریخ','هفته','مشتری','کانال','نتیجه','یادداشت','حجم','قیمت','ارزش احتمالی','پیگیری','مسئول','اقدام بعدی'],...xs.map(i=>[i.date,i.week,customerById(i.customerId)?.name,CHANNELS.find(x=>x[0]===i.channel)?.[1],interactionReplies(i).map(r=>r.label).join('، '),i.note,i.volume,i.price,i.value,i.nextFollowUp,memberName(i.memberId),i.analysis?.nextAction])]);toast('خروجی تعامل‌ها ساخته شد')}
function resetData(){if(!confirm('تمام اطلاعات ثبت‌شده این حساب حذف و نسخه اولیه جایگزین شود؟'))return;localStorage.removeItem(STORAGE_KEY);localStorage.removeItem(RECOVERY_KEY);try{_idb?.close()}catch(e){};deleteIndexedDb(IDB_NAME);state=sanitizeStateForCurrentScope(buildInitial());selectedWeek=1;save();renderAll();toast('اطلاعات بازنشانی شد')}
/* ===== ۲۰ مشتری نمونه برای دیدن پر همه‌ی قابلیت‌ها — فقط push می‌کند، هیچ داده‌ی موجودی را پاک یا جایگزین نمی‌کند. اجرای دوباره خودش را نگه می‌دارد (پاک نمی‌کند، فقط دوباره اضافه نمی‌کند). */
function seedDemoCustomers(){
  if(state.customers.some(c=>c.id==='demo-1')){toast('نمونه‌ها قبلاً اضافه شده‌اند');return}
  const okScore={fit:3,volume:3,decision:2,urgency:2,payment:2};
  const mk=(id,name,industry,city,stage,createdAt,extra={})=>({id,name,industry,city,contact:extra.contact||'',phone:extra.phone||('0912'+(3000000+ +id.replace('demo-',''))),address:'',source:'نمونه',assignee:'m1',stage,estimatedVolume:extra.estimatedVolume||0,nextFollowUp:extra.nextFollowUp||'',paymentPreference:extra.paymentPreference||'',competitor:extra.competitor||'',technicalNeed:extra.technicalNeed||'',note:extra.note||'',score:extra.score||{...okScore},createdAt:createdAt+'T09:00:00',updatedAt:createdAt+'T09:00:00'});
  const mkInt=(id,customerId,week,date,resultIds,note,extra={})=>({id,customerId,resultIds,memberId:'m1',channel:extra.channel||'call',date:date+'T11:00:00',week,note,nextFollowUp:extra.nextFollowUp||'',volume:extra.volume||0,price:extra.price||0,value:extra.value||0,analysis:extra.analysis||{},...(extra.fulfillment?{fulfillment:extra.fulfillment}:{})});

  const customers=[
    mk('demo-1','پلاستیک تهران پلیمر','بسته‌بندی صنعتی','تهران','new','2026-07-20',{nextFollowUp:addDaysISO(1)}),
    mk('demo-2','نساجی صنعتی شرق','نساجی','مشهد','new','2026-07-22',{nextFollowUp:addDaysISO(3)}),
    mk('demo-3','بسته‌بندی نوین پارس','بسته‌بندی مواد غذایی','کرج','new','2026-07-24',{}),
    mk('demo-4','صنایع نایلون البرز','تولید نایلون','کرج','contacted','2026-06-10',{nextFollowUp:addDaysISO(-2),score:{fit:4,volume:3,decision:2,urgency:2,payment:2}}),
    mk('demo-5','تولیدی پلی‌اتیلن کاوه','پلیمر','ساوه','contacted','2026-06-18',{nextFollowUp:addDaysISO(2)}),
    mk('demo-6','شیمی پلیمر ایرانیان','شیمیایی','اصفهان','qualified','2026-05-15',{score:{fit:4,volume:4,decision:3,urgency:2,payment:3},nextFollowUp:addDaysISO(4)}),
    mk('demo-7','صنایع بسته‌بندی رازی','بسته‌بندی دارویی','تهران','qualified','2026-05-28',{nextFollowUp:addDaysISO(-5)}),
    mk('demo-8','پلاستیک سازان جنوب','قطعات پلاستیکی','اهواز','sample','2026-06-02',{technicalNeed:'استرچ فیلم ۱۷ میکرون',nextFollowUp:addDaysISO(6)}),
    mk('demo-9','نایلون تولید قزوین','تولید نایلون','قزوین','sample','2026-06-20',{technicalNeed:'شرینک فیلم حرارتی',nextFollowUp:addDaysISO(3)}),
    mk('demo-10','صنایع فیلم استرچ مرکزی','بسته‌بندی صنعتی','اراک','negotiation','2026-04-20',{competitor:'تأمین‌کننده محلی اراک',score:{fit:4,volume:4,decision:3,urgency:3,payment:2},nextFollowUp:addDaysISO(-1)}),
    mk('demo-11','بسته‌بندی صادراتی البرز','صادرات','کرج','negotiation','2026-05-02',{paymentPreference:'اعتباری ۳۰ روزه',nextFollowUp:addDaysISO(2)}),
    mk('demo-12','تولیدی گرانول سبز','بازیافت پلاستیک','قزوین','negotiation','2026-06-25',{nextFollowUp:addDaysISO(-3)}),
    mk('demo-13','پلیمر صنعت کیان','قطعات تزریقی','قزوین','trial','2026-06-05',{estimatedVolume:3,score:{fit:4,volume:3,decision:3,urgency:3,payment:3},nextFollowUp:addDaysISO(5)}),
    mk('demo-14','بسته‌بندی مدرن تبریز','بسته‌بندی مواد غذایی','تبریز','trial','2026-07-01',{estimatedVolume:2,nextFollowUp:addDaysISO(1)}),
    mk('demo-15','صنایع پلیمر کاسپین','بسته‌بندی صنعتی','رشت','won','2026-05-20',{estimatedVolume:8,score:{fit:5,volume:4,decision:4,urgency:3,payment:4}}),
    mk('demo-16','تولیدی نایلون گلستان','تولید نایلون','گرگان','won','2026-03-28',{estimatedVolume:5,score:{fit:4,volume:4,decision:3,urgency:2,payment:3}}),
    mk('demo-17','بسته‌بندی آریا','بسته‌بندی صنعتی','تهران','won','2026-07-05',{estimatedVolume:2}),
    mk('demo-18','صنایع فلزی توس','قطعات فلزی','مشهد','won','2026-07-20',{estimatedVolume:4}),
    mk('demo-19','شیمی سازان البرز','شیمیایی','کرج','won','2026-05-25',{estimatedVolume:3,score:{fit:4,volume:3,decision:3,urgency:2,payment:3}}),
    mk('demo-20','بازرگانی امید شرق','واردات مواد اولیه','تهران','paused','2026-04-05',{note:'قیمت رقیب پایین‌تر بود، منطبق با بودجه‌شان نبودیم.'}),
  ];

  const interactions=[
    mkInt('demo-i1','demo-4',2,'2026-06-10',['decision_maker'],'در دسترس نبود، دوباره تماس گرفته می‌شود.'),
    mkInt('demo-i2','demo-6',3,'2026-05-15',['follow_up'],'نیاز فنی تأیید شد، منتظر تصمیم نهایی.'),
    mkInt('demo-i3','demo-8',3,'2026-06-02',['sample_requested'],'نمونه استرچ فیلم ارسال شد.'),
    mkInt('demo-i4','demo-9',4,'2026-06-20',['sample_requested','quality'],'نمونه شرینک فیلم برای تست حرارتی رفت.'),
    mkInt('demo-i5','demo-10',1,'2026-04-20',['price_high'],'قیمت نسبت به تأمین‌کننده محلی بالاتر دیده می‌شود.',{analysis:{summary:'مانع اصلی قیمت است.',mainObstacle:'قیمت',nextAction:'سناریوی تخفیف حجمی را با مدیریت بررسی کنید.',confidence:'متوسط',managerDecisionRequired:true}}),
    mkInt('demo-i6','demo-10',5,'2026-06-15',['competitor_lower'],'رقیب محلی ۸ درصد پایین‌تر پیشنهاد داده.',{analysis:{managerDecisionRequired:true}}),
    mkInt('demo-i7','demo-11',5,'2026-05-02',['payment'],'درخواست اعتبار ۳۰ روزه دارند.',{analysis:{managerDecisionRequired:true}}),
    mkInt('demo-i8','demo-12',6,'2026-06-25',['transport'],'هزینه حمل به قزوین مسئله‌ست.'),
    mkInt('demo-i9','demo-13',4,'2026-06-05',['trial_order'],'سفارش آزمایشی ۳ تن ثبت شد.',{value:24000000}),
    mkInt('demo-i10','demo-14',6,'2026-07-01',['trial_order'],'سفارش آزمایشی ۲ تن.',{value:15000000}),
    mkInt('demo-i11','demo-20',1,'2026-04-05',['not_fit'],'نیازشان با محصول ما منطبق نبود.'),
    // خرید اول: صنایع پلیمر کاسپین — ۳ دور کامل و سالم
    mkInt('demo-i12','demo-15',5,'2026-06-01',['purchase'],'خرید اول.',{value:35000000,fulfillment:{production:{source:'fresh',assignee:'m3',date:'2026-06-03',batchNo:'B-2201',specNote:''},delivery:{date:'2026-06-07',assignee:'m3',note:''},outcome:{type:'ok',note:''}}}),
    mkInt('demo-i13','demo-15',5,'2026-06-26',['purchase'],'خرید دوم.',{value:38000000,fulfillment:{production:{source:'fresh',assignee:'m3',date:'2026-06-28',batchNo:'B-2245',specNote:''},delivery:{date:'2026-07-01',assignee:'m3',note:''},outcome:{type:'ok',note:''}}}),
    mkInt('demo-i14','demo-15',6,'2026-07-20',['purchase'],'خرید سوم.',{value:41000000,fulfillment:{production:{source:'fresh',assignee:'m3',date:'2026-07-21',batchNo:'B-2290',specNote:''},delivery:{date:'2026-07-24',assignee:'m3',note:''},outcome:{type:'ok',note:''}}}),
    // تولیدی نایلون گلستان — ۲ بار خرید، بعد بیش از ۴۵ روز نخریده (سناریوی درخواستی)
    mkInt('demo-i15','demo-16',1,'2026-03-28',['purchase'],'خرید اول.',{value:22000000,fulfillment:{production:{source:'fresh',assignee:'m3',date:'2026-03-30',batchNo:'B-1180',specNote:''},delivery:{date:'2026-04-02',assignee:'m3',note:''},outcome:{type:'ok',note:''}}}),
    mkInt('demo-i16','demo-16',2,'2026-05-05',['purchase'],'خرید دوم.',{value:25000000,fulfillment:{production:{source:'fresh',assignee:'m3',date:'2026-05-07',batchNo:'B-1340',specNote:''},delivery:{date:'2026-05-10',assignee:'m3',note:''},outcome:{type:'ok',note:''}}}),
    // بسته‌بندی آریا — یک سفارش با ایراد کیفیت
    mkInt('demo-i17','demo-17',6,'2026-07-05',['purchase'],'خرید.',{value:14000000,fulfillment:{production:{source:'stock',assignee:'m3',date:'2026-07-06',batchNo:'B-2260',specNote:''},delivery:{date:'2026-07-08',assignee:'m3',note:''},outcome:{type:'complaint',note:'ضخامت فیلم غیریکنواخت گزارش شد.'}}}),
    // صنایع فلزی توس — سفارش هنوز در تولید
    mkInt('demo-i18','demo-18',6,'2026-07-23',['purchase'],'خرید، هنوز آماده نشده.',{value:28000000,fulfillment:{production:{source:'',assignee:'',date:'',batchNo:'',specNote:''},delivery:{date:'',assignee:'',note:''},outcome:{type:'',note:''}}}),
    // شیمی سازان البرز — ۲ خرید سالم، الان نزدیک موعد سفارش بعدی
    mkInt('demo-i19','demo-19',3,'2026-05-30',['purchase'],'خرید اول.',{value:18000000,fulfillment:{production:{source:'fresh',assignee:'m3',date:'2026-06-01',batchNo:'B-1410',specNote:''},delivery:{date:'2026-06-04',assignee:'m3',note:''},outcome:{type:'ok',note:''}}}),
    mkInt('demo-i20','demo-19',4,'2026-06-29',['purchase'],'خرید دوم.',{value:19500000,fulfillment:{production:{source:'fresh',assignee:'m3',date:'2026-07-01',batchNo:'B-1520',specNote:''},delivery:{date:'2026-07-04',assignee:'m3',note:''},outcome:{type:'ok',note:''}}}),
  ];

  state.customers.push(...customers);
  state.interactions.push(...interactions);
  save();renderAll();
  toast('۲۰ مشتری نمونه اضافه شد');
}
serverLoadState();
</script><script src="assets/js/ui.js"></script>
<script src="assets/js/navigation.js"></script>
<script src="assets/js/modal.js"></script>
<script src="assets/js/notifications.js"></script>
<script src="assets/js/v04-pages.js?v=0609"></script>
<script src="assets/js/v04-1-final.js"></script>
<script src="assets/vendor/jszip/jszip.js"></script>
<script src="assets/js/excel-import.js"></script>
<script src="assets/js/v05-app.js"></script>
<script src="assets/js/planning-tasks-integration.js"></script>
<script src="assets/js/v07-customer-profile.js?v=0740"></script>
<script src="assets/js/v07-dashboard-reports.js?v=0770"></script>
<script src="assets/js/v07-settings-deployment.js?v=0770"></script>
<script src="assets/js/v09-1-configurable-forms.js?v=0910"></script>
</body></html>
