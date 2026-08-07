document.addEventListener('keydown',e=>{
  const modal=document.getElementById('modal');if(!modal?.classList.contains('open')||e.key!=='Tab')return;
  const items=[...modal.querySelectorAll('button,input,select,textarea,a[href]')].filter(x=>!x.disabled&&x.offsetParent!==null);if(!items.length)return;
  const first=items[0],last=items[items.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus()}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus()}
});
