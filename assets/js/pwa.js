(()=>{'use strict';
let promptEvent=null;
const standalone=window.matchMedia?.('(display-mode: standalone)').matches||window.navigator.standalone===true;
if(standalone)document.documentElement.classList.add('pwa-mode-standalone');
function button(){let b=document.getElementById('hippoPwaInstall');if(b)return b;b=document.createElement('button');b.id='hippoPwaInstall';b.type='button';b.className='pwa-install-button';b.hidden=true;b.innerHTML='<span aria-hidden="true">＋</span><span>نصب روی گوشی</span>';b.addEventListener('click',async()=>{if(!promptEvent)return;b.disabled=true;promptEvent.prompt();try{await promptEvent.userChoice}catch(e){}promptEvent=null;b.hidden=true;b.disabled=false});document.body.appendChild(b);return b}
window.addEventListener('beforeinstallprompt',e=>{e.preventDefault();promptEvent=e;button().hidden=false});
window.addEventListener('appinstalled',()=>{promptEvent=null;const b=document.getElementById('hippoPwaInstall');if(b)b.hidden=true});
window.addEventListener('DOMContentLoaded',()=>{if('serviceWorker'in navigator&&location.protocol!=='file:')navigator.serviceWorker.register('./sw.js',{scope:'./'}).catch(()=>{});button()});
})();
