window.V03Nav={
  openDrawer(){const d=document.getElementById('mobileDrawer');if(!d)return;d.classList.add('open');d.setAttribute('aria-hidden','false');document.body.style.overflow='hidden';d.querySelector('button')?.focus()},
  closeDrawer(){const d=document.getElementById('mobileDrawer');if(!d)return;d.classList.remove('open');d.setAttribute('aria-hidden','true');document.body.style.overflow=''},
  toggleSidebar(){document.body.classList.toggle('sidebar-collapsed');try{localStorage.setItem('v03SidebarCollapsed',document.body.classList.contains('sidebar-collapsed')?'1':'0')}catch(e){}}
};
try{if(localStorage.getItem('v03SidebarCollapsed')==='1')document.body.classList.add('sidebar-collapsed')}catch(e){}
