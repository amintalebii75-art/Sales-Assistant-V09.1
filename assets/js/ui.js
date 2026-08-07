window.V03UI={
  toggleDropdown(id){const el=document.getElementById(id);if(!el)return;document.querySelectorAll('.ui-dropdown.open').forEach(x=>{if(x!==el)x.classList.remove('open')});el.classList.toggle('open')},
  closeDropdowns(){document.querySelectorAll('.ui-dropdown.open').forEach(x=>x.classList.remove('open'))},
  confirmDialog({title='تأیید عملیات',message='آیا مطمئن هستید؟',confirmText='تأیید',danger=false,onConfirm=()=>{}}={}){
    const old=document.getElementById('v03ConfirmDialog');if(old)old.remove();
    const root=document.createElement('div');root.id='v03ConfirmDialog';root.className='confirm-dialog open';root.innerHTML=`<div class="confirm-dialog-backdrop"></div><section class="confirm-dialog-card" role="alertdialog" aria-modal="true" aria-labelledby="v03ConfirmTitle"><h3 id="v03ConfirmTitle">${title}</h3><p>${message}</p><div class="confirm-dialog-actions"><button class="btn" data-cancel>انصراف</button><button class="btn ${danger?'danger':'primary'}" data-confirm>${confirmText}</button></div></section>`;
    document.body.appendChild(root);const close=()=>root.remove();root.querySelector('[data-cancel]').onclick=close;root.querySelector('.confirm-dialog-backdrop').onclick=close;root.querySelector('[data-confirm]').onclick=()=>{close();onConfirm()};root.querySelector('[data-confirm]').focus();
  }
};
document.addEventListener('click',e=>{if(!e.target.closest('.ui-dropdown'))V03UI.closeDropdowns()});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){V03UI.closeDropdowns();window.V03Nav?.closeDrawer?.();if(document.getElementById('modal')?.classList.contains('open')&&typeof closeModal==='function')closeModal()}});
