(()=>{'use strict';
const ROLE_LABELS={manager:'مدیر',marketer:'بازاریاب',center_call:'مرکز تماس'};
const ENTITY_LABELS={customer:'فرم مشتری',interaction:'فرم ثبت مذاکره'};
const $=s=>document.querySelector(s);
const authRole=()=>String(window.HIPPO_AUTH?.role||'');
const isManager=()=>authRole()==='manager'&&!!window.HIPPO_AUTH?.permissions?.['settings.manage'];
const canFullSave=()=>authRole()==='manager'&&!!window.HIPPO_AUTH?.permissions?.['state.save_full'];
const cfg=(entity,key)=>state.formConfig?.[entity]?.[key]||{};
const roleVisible=(entity,key)=>{const f=cfg(entity,key);return f.enabled!==false&&(f.roles?.[authRole()]!==false)};
const required=(entity,key)=>roleVisible(entity,key)&&!!cfg(entity,key).required;
const masterGroup=key=>state.masterData?.[key]||{label:key,addMode:'manager_only',options:[]};
const masterOptions=(key,includePending=false)=>((masterGroup(key).options)||[]).filter(o=>o&&((includePending&&o.status==='pending')||(o.active!==false&&String(o.status||'active')==='active')));
const fieldLabel=(entity,key,fallback)=>cfg(entity,key).label||fallback||key;
const star=(entity,key)=>required(entity,key)?' <span class="v091-required">*</span>':'';
const val=id=>document.getElementById(id)?.value??'';
const text=id=>String(val(id)).trim();
const num=id=>Number(val(id)||0)||0;
const addModeLabel=m=>({manager_only:'فقط مدیر',approval:'پیشنهاد با تأیید مدیر',direct:'افزودن مستقیم کاربر'})[m]||'فقط مدیر';
const optionByValue=(key,value)=>masterOptions(key,true).find(o=>String(o.label)===String(value)||String(o.id)===String(value));

function userCanAdd(key){
  if(isManager()||String(currentMember()?.access||'')==='sales_manager')return true;
  return ['direct','approval'].includes(String(masterGroup(key).addMode||'manager_only'));
}
function masterSelectHtml(key,id,current='',entity='',fieldKey='',disabled=false){
  const group=masterGroup(key),options=masterOptions(key),known=optionByValue(key,current);
  const legacy=current&&!known?`<option value="${esc(current)}" selected>${esc(current)} (ثبت قبلی)</option>`:'';
  const plus=userCanAdd(key)&&!disabled?`<button type="button" class="v091-add-option" onclick="V091.openAddOption('${key}','${id}')" title="افزودن گزینه">＋</button>`:'';
  return `<div class="v091-select-wrap"><select id="${id}" ${disabled?'disabled':''}><option value="">انتخاب کنید…</option>${legacy}${options.map(o=>`<option value="${esc(o.label)}" ${String(current)===String(o.label)?'selected':''}>${esc(o.label)}</option>`).join('')}</select>${plus}</div>`;
}
function customerEditable(key,level){
  if(level==='call')return ['contact','phone','nextFollowUp'].includes(key);
  if(level!=='edit')return false;
  if(['address','assignee','paymentPreference','competitor','note','score'].includes(key))return authRole()==='manager';
  return true;
}
function fieldWrap(entity,key,html,klass=''){if(!roleVisible(entity,key))return '';return `<div class="field ${klass}" data-config-field="${entity}.${key}"><label>${esc(fieldLabel(entity,key))}${star(entity,key)}</label>${html}</div>`}
function inputHtml(id,value='',type='text',disabled=false,attrs=''){return `<input id="${id}" type="${type}" value="${esc(value??'')}" ${disabled?'disabled':''} ${attrs}>`}
function textareaHtml(id,value='',disabled=false){return `<textarea id="${id}" ${disabled?'disabled':''}>${esc(value??'')}</textarea>`}

window.openCustomerModal=function(id=''){
  const existing=id?customerById(id):null;
  if(id&&!existing)return toast('پرونده مشتری پیدا نشد');
  if(!id&&!window.HIPPO_AUTH?.permissions?.['customers.create'])return toast('مجوز ثبت مشتری را ندارید');
  const level=existing?customerAccessLevel(existing):'edit';
  if(existing&&accessRank(level)<2)return toast('این حساب فقط اجازه مشاهده این مشتری را دارد');
  const c=existing||{name:'',industry:'',city:'',contact:'',phone:'',address:'',assignee:window.HIPPO_AUTH?.team_member_id||currentMember()?.id||state.team[0]?.id,stage:'new',source:'',estimatedVolume:'',paymentPreference:'',competitor:'',technicalNeed:'',note:'',nextFollowUp:'',productGroup:'',consumptionType:'',packaging:'',currency:'',score:{fit:3,volume:3,decision:2,urgency:2,payment:2}};
  const editable=k=>customerEditable(k,level);
  const body=[
    fieldWrap('customer','name',inputHtml('cName',c.name,'text',!editable('name'),'autocomplete="organization"')),
    fieldWrap('customer','phone',inputHtml('cPhone',c.phone,'text',!editable('phone'),'inputmode="tel"')),
    fieldWrap('customer','contact',inputHtml('cContact',c.contact,'text',!editable('contact'))),
    fieldWrap('customer','industry',masterSelectHtml('industry','cIndustry',c.industry,'customer','industry',!editable('industry'))),
    fieldWrap('customer','source',masterSelectHtml('source','cSource',c.source,'customer','source',!editable('source'))),
    fieldWrap('customer','productGroup',masterSelectHtml('productGroup','cProductGroup',c.productGroup,'customer','productGroup',!editable('productGroup'))),
    fieldWrap('customer','consumptionType',masterSelectHtml('consumptionType','cConsumptionType',c.consumptionType,'customer','consumptionType',!editable('consumptionType'))),
    fieldWrap('customer','packaging',masterSelectHtml('packaging','cPackaging',c.packaging,'customer','packaging',!editable('packaging'))),
    fieldWrap('customer','currency',masterSelectHtml('currency','cCurrency',c.currency,'customer','currency',!editable('currency'))),
    fieldWrap('customer','city',inputHtml('cCity',c.city,'text',!editable('city'))),
    fieldWrap('customer','address',inputHtml('cAddress',c.address,'text',!editable('address')),'full'),
    fieldWrap('customer','assignee',`<select id="cAssignee" ${!editable('assignee')?'disabled':''}>${state.team.map(m=>`<option value="${esc(m.id)}" ${String(m.id)===String(c.assignee)?'selected':''}>${esc(m.name)}</option>`).join('')}</select>`),
    fieldWrap('customer','stage',`<select id="cStage" ${!editable('stage')?'disabled':''}>${STAGES.map(s=>`<option value="${s.id}" ${s.id===c.stage?'selected':''}>${esc(s.label)}</option>`).join('')}</select>`),
    fieldWrap('customer','estimatedVolume',inputHtml('cVolume',c.estimatedVolume,'number',!editable('estimatedVolume'),'min="0" step="any"')),
    roleVisible('customer','nextFollowUp')?`<div class="field" data-config-field="customer.nextFollowUp"><label>${esc(fieldLabel('customer','nextFollowUp'))}${star('customer','nextFollowUp')}</label>${editable('nextFollowUp')?jdatePickerHtml('cFollow',c.nextFollowUp,true):`<input id="cFollow" value="${esc(c.nextFollowUp||'')}" disabled>`}</div>`:'',
    fieldWrap('customer','paymentPreference',inputHtml('cPayment',c.paymentPreference,'text',!editable('paymentPreference'))),
    fieldWrap('customer','competitor',inputHtml('cCompetitor',c.competitor,'text',!editable('competitor'))),
    fieldWrap('customer','technicalNeed',textareaHtml('cNeed',c.technicalNeed,!editable('technicalNeed')),'full'),
    fieldWrap('customer','note',textareaHtml('cNote',c.note,!editable('note')),'full'),
    roleVisible('customer','score')?`<div class="full" data-config-field="customer.score"><b class="v091-score-title">${esc(fieldLabel('customer','score'))}</b><div class="score-fields">${scoreSelect('تناسب محصول','fit',c.score?.fit)}${scoreSelect('حجم بالقوه','volume',c.score?.volume)}${scoreSelect('دسترسی تصمیم‌گیرنده','decision',c.score?.decision)}${scoreSelect('فوریت نیاز','urgency',c.score?.urgency)}${scoreSelect('توان پرداخت','payment',c.score?.payment)}</div></div>`:''
  ].join('');
  openModal(`<div class="modal-head"><div><span class="v04-kicker">فرم قابل تنظیم</span><h3>${id?'ویرایش مشتری':'افزودن مشتری'}</h3></div><button class="icon-btn" onclick="closeModal()">×</button></div><div class="v091-form-note">فیلدهای این فرم از بخش «فرم‌ها و اطلاعات پایه» توسط مدیر روشن، خاموش و اجباری می‌شوند.</div><div class="modal-grid">${body}<div class="full v091-form-actions"><button class="btn primary" onclick="saveCustomer('${id}')">ذخیره مشتری</button>${id&&window.HIPPO_AUTH?.permissions?.['customers.delete']?`<button class="btn danger" onclick="deleteCustomer('${id}')">حذف مشتری</button>`:''}</div></div>`,true);
  if(!window.HIPPO_AUTH?.permissions?.['customers.assign']){const a=$('#cAssignee');if(a){a.value=window.HIPPO_AUTH?.team_member_id||a.value;a.disabled=true}}
  if(authRole()!=='manager')document.querySelectorAll('#modalContent [id^="score_"]').forEach(x=>x.disabled=true);
};

function requireField(entity,key,id,label){if(!required(entity,key))return true;const element=document.getElementById(id);if(!element||String(element.value||'').trim()===''){toast(`${label||fieldLabel(entity,key)} را وارد کنید`);element?.focus();return false}return true}
window.saveCustomer=function(id){
  const existing=id?customerById(id):null,level=existing?customerAccessLevel(existing):'edit';
  if(existing&&accessRank(level)<2)return toast('این حساب فقط اجازه مشاهده این مشتری را دارد');
  const checks=[['name','cName'],['phone','cPhone'],['contact','cContact'],['industry','cIndustry'],['source','cSource'],['productGroup','cProductGroup'],['consumptionType','cConsumptionType'],['packaging','cPackaging'],['currency','cCurrency'],['city','cCity'],['address','cAddress'],['assignee','cAssignee'],['stage','cStage'],['estimatedVolume','cVolume'],['nextFollowUp','cFollow'],['paymentPreference','cPayment'],['competitor','cCompetitor'],['technicalNeed','cNeed'],['note','cNote']];
  for(const [key,inputId] of checks)if(roleVisible('customer',key)&&customerEditable(key,level)&&!requireField('customer',key,inputId))return;
  const name=text('cName');if(!name)return toast('نام مشتری را وارد کنید');
  const data={updatedAt:new Date().toISOString()};
  const assign=(key,id,transform=x=>x)=>{if(roleVisible('customer',key)&&customerEditable(key,level)&&document.getElementById(id))data[key]=transform(val(id))};
  assign('contact','cContact',x=>String(x).trim());assign('phone','cPhone',x=>String(x).trim());assign('nextFollowUp','cFollow',x=>String(x));
  if(level==='edit'){
    data.name=name;assign('industry','cIndustry',x=>String(x).trim());assign('city','cCity',x=>String(x).trim());assign('source','cSource',x=>String(x).trim());assign('stage','cStage',String);assign('estimatedVolume','cVolume',x=>Number(x)||0);assign('technicalNeed','cNeed',x=>String(x).trim());assign('productGroup','cProductGroup',x=>String(x).trim());assign('consumptionType','cConsumptionType',x=>String(x).trim());assign('packaging','cPackaging',x=>String(x).trim());assign('currency','cCurrency',x=>String(x).trim());
  }
  if(authRole()==='manager'&&window.HIPPO_AUTH?.permissions?.['state.view_full']){
    assign('address','cAddress',x=>String(x).trim());assign('assignee','cAssignee',String);assign('paymentPreference','cPayment',x=>String(x).trim());assign('competitor','cCompetitor',x=>String(x).trim());assign('note','cNote',x=>String(x).trim());
    if(roleVisible('customer','score')&&document.getElementById('score_fit'))data.score={fit:num('score_fit'),volume:num('score_volume'),decision:num('score_decision'),urgency:num('score_urgency'),payment:num('score_payment')};
  }
  if(id)Object.assign(existing,data);else state.customers.push({id:'c'+Date.now(),createdAt:new Date().toISOString(),...data});
  save();closeModal();renderAll();toast('پرونده مشتری ذخیره شد');
};

function tuneInteractionField(key,id){const el=document.getElementById(id);if(!el)return;const field=el.closest('.field');if(field)field.style.display=roleVisible('interaction',key)?'':'none';if(roleVisible('interaction',key)){const label=field?.querySelector('label');if(label&&required('interaction',key)&&!label.querySelector('.v091-required'))label.insertAdjacentHTML('beforeend',' <span class="v091-required">*</span>')}}
function interactionMasterFields(){return ['contactFor','route','currency'].filter(k=>roleVisible('interaction',k)).map(key=>fieldWrap('interaction',key,masterSelectHtml(cfg('interaction',key).masterKey,`quick_${key}`,'','interaction',key,false))).join('')}
const previousRenderActivities=window.renderActivities;
window.renderActivities=function(){
  previousRenderActivities.apply(this,arguments);
  tuneInteractionField('customer','quickCustomer');tuneInteractionField('channel','quickChannel');tuneInteractionField('nextFollowUp','quickFollow');tuneInteractionField('week','quickWeek');tuneInteractionField('volume','quickVolume');tuneInteractionField('price','quickPrice');tuneInteractionField('member','quickMember');tuneInteractionField('note','quickNote');
  const reasonGrid=document.querySelector('#page-activities .v04-reason-grid,#page-activities .reason-grid');if(reasonGrid){const host=reasonGrid.closest('.field');if(host)host.style.display=roleVisible('interaction','results')?'':'none'}
  const grid=document.querySelector('#page-activities .v04-form-grid,#page-activities .form-grid');
  if(grid&&!document.getElementById('v091InteractionMasterFields')){const note=document.getElementById('quickNote')?.closest('.field');const html=`<div id="v091InteractionMasterFields" class="v091-injected-fields">${interactionMasterFields()}</div>`;if(note)note.insertAdjacentHTML('beforebegin',html);else grid.insertAdjacentHTML('beforeend',html)}
};

window.saveInteraction=function(){
  const customerId=val('quickCustomer');if(!customerId)return toast('ابتدا مشتری را انتخاب کنید');
  if(!selectedReasonIds.length)return toast('حداقل یک نتیجه مذاکره را انتخاب کنید');
  const c=customerById(customerId),level=interactionAccessLevel(c);if(accessRank(level)<2)return toast('برای این مشتری دسترسی «ثبت تماس» یا «ویرایش» لازم است');
  const requiredChecks=[['channel','quickChannel'],['contactFor','quick_contactFor'],['route','quick_route'],['currency','quick_currency'],['nextFollowUp','quickFollow'],['week','quickWeek'],['volume','quickVolume'],['price','quickPrice'],['member','quickMember'],['note','quickNote']];
  for(const [key,id] of requiredChecks)if(roleVisible('interaction',key)&&!requireField('interaction',key,id))return;
  const reasons=selectedReasonIds.map(id=>replyById(id)).filter(Boolean),resultIds=selectedReasonIds.slice();
  const note=roleVisible('interaction','note')?text('quickNote'):'',follow=roleVisible('interaction','nextFollowUp')?val('quickFollow'):'',channel=roleVisible('interaction','channel')?val('quickChannel')||'call':'call';
  const it={id:'i'+Date.now(),customerId,resultIds,channel,date:new Date().toISOString(),note,nextFollowUp:follow,status:'completed',memberId:window.HIPPO_AUTH?.display_name||SERVER_NAME||''};
  ['contactFor','route','currency'].forEach(key=>{if(roleVisible('interaction',key))it[key]=text(`quick_${key}`)});
  if(level==='edit'){
    const data={volume:roleVisible('interaction','volume')?num('quickVolume'):0,price:roleVisible('interaction','price')?Number(digitsOnly(val('quickPrice')))||0:0,value:roleVisible('interaction','price')?Number(digitsOnly(val('quickValue')))||0:0};
    if(roleVisible('interaction','week'))it.week=num('quickWeek')||state.project.activeWeek;Object.assign(it,data);it.analysis=analyzeInteraction(c,reasons,note,data);const stage=pickStage(reasons);c.stage=stage||c.stage;if(data.volume&&!c.estimatedVolume)c.estimatedVolume=data.volume;
  }
  state.interactions.push(it);c.nextFollowUp=resultIds.some(id=>['purchase','stop','not_fit'].includes(id))&&level==='edit'?'':follow;c.updatedAt=new Date().toISOString();
  selectedReasonIds=[];save();renderAll();showPage('activities');toast(isCallCenterRole()?'نتیجه ثبت و برای بازاریاب مسئول ارسال شد':'مذاکره ثبت شد');
};

window.openInteractionDetail=function(id){
  const i=(state.interactions||[]).find(x=>String(x.id)===String(id));if(!i)return toast('مذاکره پیدا نشد');const c=customerById(i.customerId),rows=[];
  rows.push(['مشتری',c?.name||'—'],['تاریخ',faDateTime(i.date)],['کانال',CHANNELS.find(x=>x[0]===i.channel)?.[1]||i.channel||'—'],['نتیجه',interactionReplies(i).map(r=>r.label).join('، ')||'—']);
  if(i.contactFor)rows.push(['ارتباط برای',i.contactFor]);if(i.route)rows.push(['مسیر ارتباط',i.route]);if(i.currency)rows.push(['نوع ارز',i.currency]);if(i.nextFollowUp)rows.push(['پیگیری بعدی',faDate(i.nextFollowUp)]);if(i.volume)rows.push(['حجم',fmt(i.volume)]);if(i.price)rows.push(['قیمت',fmt(i.price)]);if(i.note)rows.push(['یادداشت',i.note]);
  openModal(`<div class="modal-head"><div><span class="v04-kicker">جزئیات مذاکره</span><h3>${esc(c?.name||'مشتری')}</h3></div><button class="icon-btn" onclick="closeModal()">×</button></div><div class="v091-detail-list">${rows.map(([a,b])=>`<div><span>${esc(a)}</span><b>${esc(String(b))}</b></div>`).join('')}</div>`,true);
};

const previousOpenCustomerDetail=window.openCustomerDetail;
window.openCustomerDetail=function(id){previousOpenCustomerDetail(id);setTimeout(()=>{const c=customerById(id);if(!c)return;const panel=document.querySelector('.v073-profile-panel[data-panel="overview"],.v04-profile-panel[data-profile-panel="overview"]');if(!panel||panel.querySelector('.v091-profile-base'))return;const values=[['نحوه آشنایی',c.source],['زمینه فعالیت',c.industry],['گروه محصولات',c.productGroup],['نوع مصرف',c.consumptionType],['بسته‌بندی',c.packaging],['نوع ارز',c.currency]].filter(x=>x[1]);if(values.length)panel.insertAdjacentHTML('beforeend',`<section class="v091-profile-base"><h3>اطلاعات پایه</h3><div>${values.map(([k,v])=>`<span><small>${esc(k)}</small><b>${esc(v)}</b></span>`).join('')}</div></section>`)},0)};

window.V091={
 openAddOption(key,selectId){const group=masterGroup(key);if(!userCanAdd(key))return toast('افزودن گزینه برای این فیلد غیرفعال است');openModal(`<div class="modal-head"><div><span class="v04-kicker">${esc(group.label||key)}</span><h3>افزودن گزینه جدید</h3></div><button class="icon-btn" onclick="closeModal()">×</button></div><div class="modal-grid"><div class="field full"><label>عنوان گزینه *</label><input id="v091NewOption" maxlength="100" placeholder="عنوان جدید"></div><div class="alert info full"><b>${esc(addModeLabel(group.addMode))}</b><div class="mini">${group.addMode==='approval'&&!isManager()?'این پیشنهاد پس از تأیید مدیر برای تیم فعال می‌شود.':'گزینه پس از ثبت در فهرست همین فیلد قرار می‌گیرد.'}</div></div><div class="full"><button class="btn primary" onclick="V091.createOption('${key}','${selectId}')">ثبت گزینه</button></div></div>`);},
 async createOption(key,selectId){const label=text('v091NewOption');if(label.length<2)return toast('عنوان گزینه را وارد کنید');const btn=document.querySelector('#modalContent .btn.primary');if(btn){btn.disabled=true;btn.textContent='در حال ذخیره…'}try{const r=await fetch('api.php?action=base_option_create',{method:'POST',credentials:'same-origin',headers:csrfHeaders({'Content-Type':'application/json'}),body:JSON.stringify({field_key:key,label,expected_revision:SERVER_REVISION})});const j=await r.json().catch(()=>null);if(r.status===409&&j?.error==='revision_conflict'){SERVER_REVISION=Number(j.current_revision||SERVER_REVISION);closeModal();await serverLoadState(true);return toast('نسخه تازه شد؛ دوباره گزینه را اضافه کنید')}if(!r.ok||!j?.ok){const messages={base_option_exists:'این گزینه قبلاً وجود دارد',base_option_add_forbidden:'افزودن گزینه برای این فیلد مجاز نیست',invalid_base_option_label:'عنوان گزینه معتبر نیست'};throw new Error(messages[j?.error]||'ذخیره گزینه انجام نشد')}SERVER_REVISION=Number(j.revision||SERVER_REVISION+1);STATE_CONTEXT_TOKEN=String(j.state_context_token||STATE_CONTEXT_TOKEN);state.masterData=state.masterData||{};state.masterData[key]=state.masterData[key]||{label:key,addMode:'manager_only',options:[]};state.masterData[key].options=state.masterData[key].options||[];state.masterData[key].options.push(j.option);persistLocalState();closeModal();if(j.option.status==='pending')toast('پیشنهاد برای تأیید مدیر ثبت شد');else{const select=document.getElementById(selectId);if(select){select.insertAdjacentHTML('beforeend',`<option value="${esc(j.option.label)}" selected>${esc(j.option.label)}</option>`)}toast('گزینه جدید اضافه شد')}}catch(e){toast(e?.message||'ذخیره گزینه انجام نشد');if(btn){btn.disabled=false;btn.textContent='ثبت گزینه'}}},
 updateField(entity,key,prop,value){if(!isManager()||!canFullSave())return toast('فقط مدیر مجاز است');const f=state.formConfig?.[entity]?.[key];if(!f)return;f[prop]=value;if(prop==='enabled'&&f.locked)f.enabled=true;if(prop==='required'&&f.locked)f.required=true;save();renderBaseInfo();},
 updateRole(entity,key,role,value){if(!isManager()||!canFullSave())return;const f=state.formConfig?.[entity]?.[key];if(!f)return;f.roles=f.roles||{};f.roles[role]=!!value;save();renderBaseInfo();},
 updateAddMode(key,value){if(!isManager()||!canFullSave())return;masterGroup(key).addMode=value;save();renderBaseInfo();},
 setOptionStatus(key,id,status){if(!isManager()||!canFullSave())return;const o=(masterGroup(key).options||[]).find(x=>String(x.id)===String(id));if(!o)return;o.status=status;o.active=status==='active';o.reviewedBy=window.HIPPO_AUTH?.display_name||'';o.reviewedAt=new Date().toISOString();save();renderBaseInfo();},
 renameOption(key,id){if(!isManager()||!canFullSave())return;const o=(masterGroup(key).options||[]).find(x=>String(x.id)===String(id));if(!o)return;const next=prompt('عنوان جدید',o.label||'');if(!next||next.trim().length<2)return;o.label=next.trim().slice(0,100);save();renderBaseInfo();},
 resetDefaults(){if(!isManager()||!canFullSave())return;if(!confirm('تنظیمات فرم و اطلاعات پایه به پیش‌فرض V09.1 برگردد؟ داده مشتریان حذف نمی‌شود.'))return;state.formConfig=cloneDefaultFormConfig();state.masterData=cloneDefaultMasterData();save();renderBaseInfo();toast('تنظیمات پیش‌فرض بازیابی شد')}
};

function fieldCard(entity,key,f){return `<article class="v091-field-card ${f.enabled===false?'disabled':''}"><header><div><b>${esc(f.label||key)}</b><small>${esc(key)}${f.masterKey?` · فهرست ${esc(masterGroup(f.masterKey).label)}`:''}</small></div>${f.locked?'<span class="badge warn">سیستمی</span>':''}</header><div class="v091-field-switches"><label><input type="checkbox" ${f.enabled!==false?'checked':''} ${f.locked?'disabled':''} onchange="V091.updateField('${entity}','${key}','enabled',this.checked)"> نمایش</label><label><input type="checkbox" ${f.required?'checked':''} ${f.locked?'disabled':''} onchange="V091.updateField('${entity}','${key}','required',this.checked)"> اجباری</label></div><div class="v091-role-grid">${FORM_ROLE_KEYS.map(role=>`<label><input type="checkbox" ${f.roles?.[role]!==false?'checked':''} ${f.locked?'disabled':''} onchange="V091.updateRole('${entity}','${key}','${role}',this.checked)">${ROLE_LABELS[role]}</label>`).join('')}</div></article>`}
function optionRow(key,o){const status=String(o.status||'active');return `<div class="v091-option-row ${status}"><div><b>${esc(o.label||'')}</b><small>${o.system?'پیش‌فرض سیستم':esc(o.createdBy||'افزوده‌شده توسط تیم')} · ${status==='pending'?'در انتظار تأیید':status==='rejected'?'ردشده':o.active===false?'غیرفعال':'فعال'}</small></div><div>${status==='pending'?`<button class="btn small primary" onclick="V091.setOptionStatus('${key}','${o.id}','active')">تأیید</button><button class="btn small danger" onclick="V091.setOptionStatus('${key}','${o.id}','rejected')">رد</button>`:`<button class="btn small" onclick="V091.renameOption('${key}','${o.id}')">ویرایش نام</button><button class="btn small ${o.active===false?'primary':'warn'}" onclick="V091.setOptionStatus('${key}','${o.id}','${o.active===false?'active':'inactive'}')">${o.active===false?'فعال‌کردن':'غیرفعال‌کردن'}</button>`}</div></div>`}
window.renderBaseInfo=function(){const root=document.getElementById('page-baseinfo');if(!root)return;if(!isManager()){root.innerHTML=emptyState('دسترسی محدود','مدیریت فرم‌ها و اطلاعات پایه فقط برای مدیر مجاز است.');return}root.innerHTML=`<div class="v091-hero"><div><span class="v04-kicker">پیکربندی پروژه</span><h1>فرم‌ها و اطلاعات پایه</h1><p>فیلدها را روشن یا خاموش کنید، اجباری‌بودن و نمایش برای نقش‌ها را تعیین کنید و گزینه‌های مشترک تیم را مدیریت کنید.</p></div><button class="btn" onclick="V091.resetDefaults()">بازگشت به پیش‌فرض</button></div><div class="alert info"><b>مرز امنیتی ثابت است</b><div class="mini">این صفحه فقط ظاهر و الزام فرم را تنظیم می‌کند. Permission و سقف View / Call / Edit همچنان در Backend اعمال می‌شود.</div></div>${Object.entries(state.formConfig||{}).map(([entity,fields])=>`<section class="v091-section"><div class="v091-section-head"><div><h2>${ENTITY_LABELS[entity]||entity}</h2><p>${Object.values(fields).filter(f=>f.enabled!==false).length.toLocaleString('fa-IR')} فیلد فعال</p></div></div><div class="v091-fields-grid">${Object.entries(fields).map(([key,f])=>fieldCard(entity,key,f)).join('')}</div></section>`).join('')}<section class="v091-section"><div class="v091-section-head"><div><h2>فهرست‌های اطلاعات پایه</h2><p>گزینه‌های فعال در فرم‌ها نمایش داده می‌شوند؛ غیرفعال‌کردن گزینه، اطلاعات قبلی را حذف نمی‌کند.</p></div></div><div class="v091-master-grid">${Object.entries(state.masterData||{}).map(([key,group])=>`<article class="v091-master-card"><header><div><h3>${esc(group.label||key)}</h3><small>${masterOptions(key).length.toLocaleString('fa-IR')} گزینه فعال · ${(group.options||[]).filter(o=>o.status==='pending').length.toLocaleString('fa-IR')} در انتظار</small></div><button class="v091-add-option" onclick="V091.openAddOption('${key}','')">＋</button></header><div class="field"><label>افزودن توسط کاربران</label><select onchange="V091.updateAddMode('${key}',this.value)"><option value="manager_only" ${group.addMode==='manager_only'?'selected':''}>فقط مدیر</option><option value="approval" ${group.addMode==='approval'?'selected':''}>پیشنهاد با تأیید مدیر</option><option value="direct" ${group.addMode==='direct'?'selected':''}>افزودن مستقیم</option></select></div><div class="v091-options-list">${(group.options||[]).map(o=>optionRow(key,o)).join('')}</div></article>`).join('')}</div></section>`};

const previousShowPage=window.showPage;
window.showPage=function(name,el){if(name==='baseinfo'){currentPage='baseinfo';document.querySelectorAll('.page').forEach(p=>p.classList.toggle('active',p.id==='page-baseinfo'));document.querySelectorAll('[data-page]').forEach(b=>b.classList.toggle('active',b.dataset.page==='baseinfo'));window.scrollTo({top:0,behavior:'smooth'});renderBaseInfo();return}return previousShowPage(name,el)};
const previousRenderAll=window.renderAll;
window.renderAll=function(){previousRenderAll.apply(this,arguments);if(currentPage==='baseinfo')renderBaseInfo()};
})();
