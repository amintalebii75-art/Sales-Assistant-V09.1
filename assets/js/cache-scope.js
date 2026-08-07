(()=>{'use strict';
const LEGACY=['hippoSalesAssistantOffline_v2','hippoPlast12WeekDashboard_v1','hippoSalesAssistantOffline_v2_recovery'];
function clearLegacy(){try{LEGACY.forEach(k=>localStorage.removeItem(k))}catch(e){}}
function clearScopedLocal(){try{for(let i=localStorage.length-1;i>=0;i--){const k=localStorage.key(i)||'';if(k.startsWith('hippoSales:user:')||k.startsWith('hippoPilot:user:'))localStorage.removeItem(k)}}catch(e){}}
function deleteDb(name){return new Promise(r=>{try{const q=indexedDB.deleteDatabase(name);q.onsuccess=q.onerror=q.onblocked=()=>r()}catch(e){r()}})}
async function clearIndexed(){try{if(indexedDB.databases){const dbs=await indexedDB.databases();await Promise.all((dbs||[]).map(d=>String(d.name||'')).filter(n=>n==='hippoSalesDB'||n.startsWith('hippoSalesDB-')).map(deleteDb))}else{await deleteDb('hippoSalesDB')}}catch(e){}}
async function clearAll(){clearLegacy();clearScopedLocal();await clearIndexed()}
window.HippoCacheSecurity={clearLegacy,clearAll};
})();
