'use strict';
const CACHE='hippo-static-v09-1-0';
const STATIC=[
  './offline.html','./manifest.webmanifest',
  './assets/css/tokens.css','./assets/css/base.css','./assets/css/components.css','./assets/css/layout.css','./assets/css/pages.css','./assets/css/responsive.css','./assets/css/v07-ariana.css','./assets/css/pwa.css','./assets/css/v09-1-configurable-forms.css',
  './assets/js/pwa.js','./assets/js/v09-1-configurable-forms.js','./assets/icons/icon-192.png','./assets/icons/icon-512.png'
];
self.addEventListener('install',event=>{event.waitUntil(caches.open(CACHE).then(c=>c.addAll(STATIC)).then(()=>self.skipWaiting()))});
self.addEventListener('activate',event=>{event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim()))});
self.addEventListener('fetch',event=>{
  const req=event.request;
  if(req.method!=='GET')return;
  const url=new URL(req.url);
  if(url.origin!==location.origin)return;
  const path=url.pathname.toLowerCase();
  // Never cache authenticated pages, APIs or any PHP response.
  if(path.endsWith('.php')||path.includes('/api')||path.includes('users_api')||path.includes('planning_api')){
    if(req.mode==='navigate') event.respondWith(fetch(req,{cache:'no-store'}).catch(()=>caches.match('./offline.html')));
    return;
  }
  event.respondWith(caches.match(req).then(hit=>hit||fetch(req).then(res=>{
    if(res.ok&&['style','script','image','font'].includes(req.destination)){
      const copy=res.clone();caches.open(CACHE).then(c=>c.put(req,copy));
    }
    return res;
  })));
});
