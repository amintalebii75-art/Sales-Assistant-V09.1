/*
 * V07.3.0 — Ariana customer profile and negotiation handoff
 * Presentation and navigation layer only. Existing permissions and backend rules stay authoritative.
 */
(function(){
  'use strict';

  const htmlSafe=(value)=>typeof esc==='function'?esc(value):String(value??'').replace(/[&<>"']/g,(ch)=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[ch]));
  const valueOrDash=(value)=>value===0?'۰':(value?htmlSafe(value):'—');
  const channelLabel=(id)=>((typeof CHANNELS!=='undefined'?CHANNELS:[]).find(x=>x[0]===id)||[])[1]||'تعامل';
  const levelLabel=(level)=>({view:'فقط مشاهده',call:'ثبت مذاکره',edit:'ویرایش پرونده'}[level]||'بدون دسترسی');
  const levelTone=(level)=>({view:'neutral',call:'blue',edit:'ok'}[level]||'neutral');
  const followTone=(date)=>!date?'neutral':date<todayISO()?'danger':date===todayISO()?'warn':'blue';
  const roleCan=(permission)=>typeof canPermission==='function'&&canPermission(permission);
  const canViewOrders=()=>String(window.HIPPO_AUTH?.role)==='manager'||roleCan('orders.view_all')||roleCan('orders.view_own');

  function accessFor(c){
    const level=typeof customerAccessLevel==='function'?customerAccessLevel(c):'view';
    const canCall=typeof interactionAccessLevel==='function'&&typeof accessRank==='function'&&accessRank(interactionAccessLevel(c))>=accessRank('call')&&roleCan('interactions.create');
    const canEdit=typeof accessRank==='function'&&accessRank(level)>=accessRank('edit')&&(String(window.HIPPO_AUTH?.role)==='manager'||roleCan('customers.edit_all')||roleCan('customers.edit_own'));
    return {level,canCall,canEdit};
  }

  function resultLabels(interaction){
    try{return interactionReplies(interaction).map(r=>r.label).filter(Boolean)}catch(e){return []}
  }

  function orderState(interaction){
    if(typeof isOrderInteraction==='function'&&!isOrderInteraction(interaction))return '';
    try{
      const status=orderStatus(interaction);
      return `<span class="badge ${htmlSafe(status.color||'warn')}">${htmlSafe(status.label||'سفارش')}</span>`;
    }catch(e){return '<span class="badge warn">سفارش</span>'}
  }

  function nextActionText(c,last){
    if(last?.analysis?.nextAction)return last.analysis.nextAction;
    if(c.nextFollowUp)return `پیگیری برنامه‌ریزی‌شده در ${faDate(c.nextFollowUp)}`;
    if(!last)return 'ثبت اولین تماس یا مذاکره با مشتری';
    return 'برای این مشتری زمان پیگیری بعدی تعیین نشده است.';
  }

  function metricHtml(label,value,meta,tone=''){
    return `<article class="v073-profile-metric ${tone}"><small>${htmlSafe(label)}</small><b>${htmlSafe(value)}</b><span>${htmlSafe(meta)}</span></article>`;
  }

  function profileOverview(c,ints,last,orders,access){
    const reviewed=typeof isScoreReviewed==='function'&&isScoreReviewed(c);
    const score=reviewed&&typeof normalizedScore==='function'?normalizedScore(c):null;
    return `<div class="v073-overview-grid">
      <div class="v073-overview-main">
        <section class="v073-section v073-next-action">
          <div class="v073-section-head"><div><span class="v073-eyebrow">اقدام پیشنهادی</span><h3>${htmlSafe(nextActionText(c,last))}</h3></div>${c.nextFollowUp?`<span class="badge ${followTone(c.nextFollowUp)}">${faDate(c.nextFollowUp)}</span>`:''}</div>
          <p>${last?.analysis?.summary?htmlSafe(last.analysis.summary):'این پیشنهاد از آخرین تعامل و تاریخ پیگیری پرونده ساخته شده است.'}</p>
          <div class="v073-action-row">${access.canCall?`<button class="btn primary" onclick="closeModal();prefillQuick('${c.id}')">ثبت نتیجه مذاکره</button>`:''}<button class="btn" onclick="v073ShowProfileTab('timeline')">مشاهده تاریخچه</button></div>
        </section>
        <section class="v073-section">
          <div class="v073-section-head"><div><span class="v073-eyebrow">آخرین وضعیت</span><h3>خلاصه پرونده فروش</h3></div></div>
          <div class="v073-info-grid">
            <div><span>محصول یا نیاز فنی</span><b>${valueOrDash(c.technicalNeed)}</b></div>
            <div><span>حجم احتمالی</span><b>${c.estimatedVolume?fmt(c.estimatedVolume):'—'}</b></div>
            <div><span>شرایط پرداخت</span><b>${valueOrDash(c.paymentPreference)}</b></div>
            <div><span>رقیب فعلی</span><b>${valueOrDash(c.competitor)}</b></div>
            <div><span>منبع آشنایی</span><b>${valueOrDash(c.source)}</b></div>
            <div><span>امتیاز مشتری</span><b>${score===null?'بررسی نشده':fmt(score)}</b></div>
          </div>
        </section>
        ${last?.analysis?`<section class="v073-section v073-analysis-card"><div class="v073-section-head"><div><span class="v073-eyebrow">آخرین تحلیل ثبت‌شده</span><h3>${valueOrDash(last.analysis.mainObstacle)}</h3></div><span class="badge blue">اطمینان ${valueOrDash(last.analysis.confidence)}</span></div><p>${valueOrDash(last.analysis.summary)}</p>${last.analysis.buyingSignals?.length?`<div class="v073-chip-row">${last.analysis.buyingSignals.map(x=>`<span>${htmlSafe(x)}</span>`).join('')}</div>`:''}</section>`:''}
      </div>
      <aside class="v073-overview-side">
        <section class="v073-section"><div class="v073-section-head"><h3>اطلاعات تماس</h3></div><dl class="v073-definition-list"><div><dt>شخص تماس</dt><dd>${valueOrDash(c.contact)}</dd></div><div><dt>شماره تماس</dt><dd>${valueOrDash(c.phone)}</dd></div><div><dt>شهر</dt><dd>${valueOrDash(c.city)}</dd></div><div><dt>آدرس</dt><dd>${valueOrDash(c.address)}</dd></div></dl></section>
        <section class="v073-section"><div class="v073-section-head"><h3>خلاصه فعالیت</h3></div><dl class="v073-definition-list"><div><dt>تعداد تعامل</dt><dd>${fmt(ints.length)}</dd></div><div><dt>آخرین تعامل</dt><dd>${last?faDateTime(last.date):'ثبت نشده'}</dd></div><div><dt>تعداد سفارش</dt><dd>${fmt(orders.length)}</dd></div><div><dt>سطح دسترسی شما</dt><dd><span class="badge ${levelTone(access.level)}">${levelLabel(access.level)}</span></dd></div></dl></section>
      </aside>
    </div>`;
  }

  function profileTimeline(c,ints){
    if(!ints.length)return `<div class="v073-empty"><b>هنوز تعاملی ثبت نشده است</b><span>اولین تماس یا مذاکره را برای این مشتری ثبت کنید.</span><button class="btn primary" onclick="closeModal();prefillQuick('${c.id}')">ثبت اولین مذاکره</button></div>`;
    return `<div class="v073-timeline">${ints.map((i,index)=>{
      const labels=resultLabels(i);
      return `<article class="v073-timeline-item">
        <div class="v073-timeline-rail"><span>${fmt(ints.length-index)}</span></div>
        <div class="v073-timeline-card">
          <div class="v073-timeline-head"><div><h3>${htmlSafe(labels.join('، ')||'تعامل')}</h3><p>${faDateTime(i.date)} · ${htmlSafe(memberName(i.memberId))} · ${htmlSafe(channelLabel(i.channel))}</p></div>${orderState(i)}</div>
          ${labels.length?`<div class="v073-chip-row">${labels.map(x=>`<span>${htmlSafe(x)}</span>`).join('')}</div>`:''}
          <p class="v073-note">${htmlSafe(i.note||i.analysis?.summary||'بدون توضیح')}</p>
          <div class="v073-timeline-footer"><span class="badge ${followTone(i.nextFollowUp)}">پیگیری ${i.nextFollowUp?faDate(i.nextFollowUp):'تعیین نشده'}</span>${i.analysis?.managerDecisionRequired?'<span class="badge warn">نیازمند تصمیم مدیریت</span>':''}<button class="btn small" onclick="openInteractionDetail('${i.id}')">جزئیات</button></div>
        </div>
      </article>`;
    }).join('')}</div>`;
  }

  function profileFollowups(c,ints,access){
    const rows=ints.filter(i=>i.nextFollowUp).map(i=>({date:i.nextFollowUp,interaction:i})).sort((a,b)=>String(b.date).localeCompare(String(a.date)));
    return `<div class="v073-followup-layout">
      <section class="v073-section v073-followup-current"><span class="v073-eyebrow">پیگیری فعال پرونده</span><h3>${c.nextFollowUp?faDate(c.nextFollowUp):'تعیین نشده'}</h3><p>${c.nextFollowUp&&c.nextFollowUp<todayISO()?'این پیگیری عقب‌افتاده است و باید تعیین تکلیف شود.':'زمان پیگیری از آخرین ثبت معتبر پرونده نمایش داده می‌شود.'}</p>${access.canCall?`<button class="btn primary" onclick="closeModal();prefillQuick('${c.id}')">ثبت نتیجه و زمان جدید</button>`:''}</section>
      <section class="v073-section"><div class="v073-section-head"><div><span class="v073-eyebrow">سابقه زمان‌بندی</span><h3>پیگیری‌های ثبت‌شده</h3></div><span class="badge">${fmt(rows.length)} مورد</span></div>${rows.length?`<div class="v073-followup-list">${rows.map(x=>`<div><span class="badge ${followTone(x.date)}">${faDate(x.date)}</span><p>${htmlSafe(resultLabels(x.interaction).join('، ')||'تعامل')} · ${faDateTime(x.interaction.date)}</p></div>`).join('')}</div>`:'<p class="muted">سابقه پیگیری ثبت نشده است.</p>'}</section>
    </div>`;
  }

  function profileOrders(orders){
    if(!canViewOrders())return `<div class="v073-empty"><b>این بخش برای نقش شما قابل مشاهده نیست</b><span>اطلاعات سفارش فقط براساس Permissionهای فعلی نمایش داده می‌شود.</span></div>`;
    if(!orders.length)return `<div class="v073-empty"><b>سفارشی در پرونده وجود ندارد</b><span>سفارش آزمایشی یا خرید پس از ثبت نتیجه معتبر نمایش داده می‌شود.</span></div>`;
    return `<div class="v073-order-list">${orders.slice().reverse().map(i=>`<article><div><h3>${htmlSafe(resultLabels(i).join('، ')||'سفارش')}</h3><p>${faDateTime(i.date)} · حجم ${fmt(i.volume||0)} · ارزش ${fmt(i.value||0)}</p></div><div>${orderState(i)}${typeof canManageOrders==='function'&&canManageOrders()?`<button class="btn small" onclick="openFulfillmentModal('${i.id}')">مدیریت عملیات</button>`:''}</div></article>`).join('')}</div>`;
  }

  function profileNotes(c,ints,access){
    const notes=ints.filter(i=>String(i.note||'').trim()).slice(0,10);
    return `<div class="v073-notes-grid"><section class="v073-section"><div class="v073-section-head"><div><span class="v073-eyebrow">یادداشت اصلی</span><h3>خلاصه ثابت پرونده</h3></div>${access.canEdit?`<button class="btn small" onclick="closeModal();openCustomerModal('${c.id}')">ویرایش</button>`:''}</div><p class="v073-long-note">${htmlSafe(c.note||'یادداشت ثابتی برای این مشتری ثبت نشده است.')}</p></section><section class="v073-section"><div class="v073-section-head"><div><span class="v073-eyebrow">یادداشت‌های تعامل</span><h3>آخرین نکات ثبت‌شده</h3></div><span class="badge">${fmt(notes.length)}</span></div>${notes.length?`<div class="v073-note-list">${notes.map(i=>`<div><b>${faDateTime(i.date)}</b><p>${htmlSafe(i.note)}</p></div>`).join('')}</div>`:'<p class="muted">یادداشت تعاملی وجود ندارد.</p>'}</section></div>`;
  }

  function panelHtml(tab,c,ints,last,orders,access){
    if(tab==='timeline')return profileTimeline(c,ints);
    if(tab==='followups')return profileFollowups(c,ints,access);
    if(tab==='orders')return profileOrders(orders);
    if(tab==='notes')return profileNotes(c,ints,access);
    return profileOverview(c,ints,last,orders,access);
  }

  window.v073ShowProfileTab=function(tab){
    document.querySelectorAll('.v073-profile-tab').forEach(btn=>btn.classList.toggle('active',btn.dataset.tab===tab));
    document.querySelectorAll('.v073-profile-panel').forEach(panel=>panel.classList.toggle('active',panel.dataset.panel===tab));
  };

  window.openCustomerDetail=function(id){
    const c=customerById(id);if(!c)return;
    const ints=(state.interactions||[]).filter(i=>i.customerId===id).sort((a,b)=>String(b.date).localeCompare(String(a.date)));
    const orders=typeof customerOrders==='function'?customerOrders(c.id):[];
    const last=ints[0]||null;
    const access=accessFor(c);
    const reviewed=typeof isScoreReviewed==='function'&&isScoreReviewed(c);
    const score=reviewed&&typeof normalizedScore==='function'?normalizedScore(c):null;
    const activeCount=ints.filter(i=>i.nextFollowUp&&i.nextFollowUp>=todayISO()).length;
    const tabs=[['overview','نمای کلی'],['timeline','تاریخچه'],['followups','پیگیری‌ها'],...(canViewOrders()?[['orders','سفارش‌ها']]:[]),['notes','یادداشت‌ها']];

    openModal(`<div class="v073-customer-workspace">
      <header class="v073-profile-hero">
        <div class="v073-profile-title"><span class="v073-profile-avatar">${htmlSafe((c.name||'م').slice(0,1))}</span><div><span class="v073-eyebrow">پرونده یکپارچه مشتری</span><h2>${htmlSafe(c.name)}</h2><p>${htmlSafe(c.industry||'صنعت ثبت نشده')} · ${htmlSafe(c.city||'شهر ثبت نشده')} · مسئول ${htmlSafe(memberName(c.assignee))}</p><div class="v073-profile-badges">${stageBadge(c.stage)}<span class="badge ${followTone(c.nextFollowUp)}">پیگیری ${c.nextFollowUp?faDate(c.nextFollowUp):'تعیین نشده'}</span><span class="badge ${levelTone(access.level)}">${levelLabel(access.level)}</span></div></div></div>
        <button class="icon-btn v073-close" aria-label="بستن" onclick="closeModal()">×</button>
      </header>
      <div class="v073-profile-actions">${access.canCall?`<button class="btn primary" onclick="closeModal();prefillQuick('${c.id}')">＋ ثبت مذاکره</button>`:''}${access.canEdit?`<button class="btn" onclick="closeModal();openCustomerModal('${c.id}')">ویرایش پرونده</button>`:''}<button class="btn soft" onclick="aiAnalyzeCustomer('${c.id}')">✦ تحلیل مشتری</button><div id="aiCustomerPanel" style="display:none"></div></div>
      <div class="v073-profile-metrics">${metricHtml('تعامل ثبت‌شده',fmt(ints.length),last?`آخرین: ${faDateTime(last.date)}`:'هنوز ثبت نشده')}${metricHtml('پیگیری فعال',fmt(activeCount),c.nextFollowUp?faDate(c.nextFollowUp):'زمان ندارد',c.nextFollowUp&&c.nextFollowUp<todayISO()?'danger':'')}${metricHtml('سفارش پرونده',fmt(orders.length),orders.length?'آزمایشی و خرید':'بدون سفارش')}${metricHtml('امتیاز مشتری',score===null?'—':fmt(score),score===null?'بررسی نشده':'امتیاز ثبت‌شده')}
      </div>
      <nav class="v073-profile-tabs" role="tablist">${tabs.map(([id,label],index)=>`<button class="v073-profile-tab ${index===0?'active':''}" data-tab="${id}" onclick="v073ShowProfileTab('${id}')">${label}</button>`).join('')}</nav>
      <main class="v073-profile-panels">${tabs.map(([id],index)=>`<section class="v073-profile-panel ${index===0?'active':''}" data-panel="${id}">${panelHtml(id,c,ints,last,orders,access)}</section>`).join('')}</main>
    </div>`,true);
    document.getElementById('modal')?.classList.add('customer-profile-open','v073-profile-open');
  };

  function negotiationContextHtml(c){
    if(!c)return `<div class="v073-negotiation-context empty"><span>ابتدا یک مشتری را انتخاب کنید تا خلاصه پرونده اینجا نمایش داده شود.</span></div>`;
    const access=accessFor(c);
    const last=(state.interactions||[]).filter(i=>i.customerId===c.id).sort((a,b)=>String(b.date).localeCompare(String(a.date)))[0];
    return `<div class="v073-negotiation-context"><div class="v073-negotiation-person"><span class="v073-mini-avatar">${htmlSafe((c.name||'م').slice(0,1))}</span><div><b>${htmlSafe(c.name)}</b><small>${htmlSafe(c.industry||'صنعت ثبت نشده')} · ${htmlSafe(memberName(c.assignee))}</small></div></div><div class="v073-negotiation-facts">${stageBadge(c.stage)}<span class="badge ${followTone(c.nextFollowUp)}">پیگیری ${c.nextFollowUp?faDate(c.nextFollowUp):'ندارد'}</span><span class="badge ${levelTone(access.level)}">${levelLabel(access.level)}</span></div><div class="v073-negotiation-last"><span>آخرین تعامل</span><b>${last?htmlSafe(resultLabels(last).join('، ')||'تعامل'):'ثبت نشده'}</b><small>${last?faDateTime(last.date):'—'}</small></div><button type="button" class="btn small" onclick="openCustomerDetail('${c.id}')">پرونده مشتری</button></div>`;
  }

  window.v073UpdateNegotiationContext=function(){
    const select=document.getElementById('quickCustomer');
    const host=document.getElementById('v073NegotiationCustomerContext');
    if(!select||!host)return;
    host.innerHTML=negotiationContextHtml(select.value?customerById(select.value):null);
  };

  const originalRenderActivities=window.renderActivities;
  if(typeof originalRenderActivities==='function'){
    window.renderActivities=function(){
      originalRenderActivities.apply(this,arguments);
      const select=document.getElementById('quickCustomer');
      if(!select)return;
      const field=select.closest('.field');
      if(field&&!document.getElementById('v073NegotiationCustomerContext'))field.insertAdjacentHTML('afterend','<div class="field full" id="v073NegotiationCustomerContext"></div>');
      select.addEventListener('change',window.v073UpdateNegotiationContext);
      if(window.V073_PENDING_CUSTOMER){select.value=String(window.V073_PENDING_CUSTOMER);window.V073_PENDING_CUSTOMER='';}
      window.v073UpdateNegotiationContext();
    };
  }

  window.prefillQuick=function(id){
    window.V073_PENDING_CUSTOMER=String(id||'');
    showPage('activities');
    const select=document.getElementById('quickCustomer');
    if(select){select.value=String(id||'');window.V073_PENDING_CUSTOMER='';window.v073UpdateNegotiationContext();}
  };

  try{
    if(currentPage==='activities')window.renderActivities();
  }catch(error){console.error('V07.3 customer profile initialization failed',error)}
})();
