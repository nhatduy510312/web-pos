'use strict';
// Progressive enhancement: without JS, each thumbnail still links to its full photo.
(() => {
  const links=[...document.querySelectorAll('[data-gallery-photo]')];
  if(!links.length || typeof HTMLDialogElement==='undefined' || !HTMLDialogElement.prototype.showModal)return;
  const en=document.documentElement.lang==='en';
  const dialog=document.createElement('dialog');dialog.className='photo-dialog';
  dialog.setAttribute('aria-label',en?'Photo viewer':'Xem ảnh không gian Ghé');
  dialog.innerHTML='<div class="photo-dialog-toolbar"><button type="button" data-photo-prev>←</button><span data-photo-count aria-live="polite"></span><button type="button" data-photo-next>→</button><button type="button" data-photo-close autofocus>×</button></div><img data-photo-full alt=""><p data-photo-caption></p>';
  document.body.append(dialog);
  const full=dialog.querySelector('[data-photo-full]'),caption=dialog.querySelector('[data-photo-caption]'),count=dialog.querySelector('[data-photo-count]');
  const close=dialog.querySelector('[data-photo-close]'),previous=dialog.querySelector('[data-photo-prev]'),next=dialog.querySelector('[data-photo-next]');
  close.setAttribute('aria-label',en?'Close photo':'Đóng ảnh');previous.setAttribute('aria-label',en?'Previous photo':'Ảnh trước');next.setAttribute('aria-label',en?'Next photo':'Ảnh tiếp theo');
  let index=0,opener=null,overflow='';
  function show(position){
    index=(position+links.length)%links.length;const link=links[index];
    full.alt=link.dataset.caption;full.src=link.href;caption.textContent=link.dataset.caption;
    count.textContent=`${index+1} / ${links.length}`;
    previous.disabled=next.disabled=links.length<2;
  }
  full.addEventListener('error',()=>{caption.textContent=en?'This photo could not be loaded. Please try again.':'Chưa tải được ảnh. Bạn vui lòng thử lại.';});
  links.forEach((link,position)=>link.addEventListener('click',event=>{
    if(event.ctrlKey || event.metaKey || event.shiftKey || event.altKey)return;
    event.preventDefault();opener=link;show(position);overflow=document.documentElement.style.overflow;document.documentElement.style.overflow='hidden';dialog.showModal();
  }));
  close.addEventListener('click',()=>dialog.close());previous.addEventListener('click',()=>show(index-1));next.addEventListener('click',()=>show(index+1));
  dialog.addEventListener('keydown',event=>{if(event.key==='ArrowLeft'){event.preventDefault();show(index-1);}if(event.key==='ArrowRight'){event.preventDefault();show(index+1);}});
  dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close();});
  dialog.addEventListener('close',()=>{document.documentElement.style.overflow=overflow;full.removeAttribute('src');opener?.focus({preventScroll:true});});
})();
const menuSearch = document.getElementById('public-menu-search');
if (menuSearch) {
  document.querySelector('[data-menu-tools]').hidden = false;
  const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D').toLowerCase().trim();
  const items = [...document.querySelectorAll('[data-menu-item]')];
  menuSearch.addEventListener('input', () => {
    const query = normalize(menuSearch.value);
    let count = 0;
    items.forEach(item => { item.hidden = !normalize(item.dataset.search).includes(query); if (!item.hidden) count++; });
    document.querySelectorAll('[data-menu-section]').forEach(section => { section.hidden = ![...section.querySelectorAll('[data-menu-item]')].some(item => !item.hidden); });
    document.getElementById('menu-empty').hidden = count !== 0;
    document.getElementById('menu-result').textContent = query ? (document.documentElement.lang === 'en' ? `${count} matching item${count === 1 ? '' : 's'}` : `${count} món phù hợp`) : '';
  });
  document.querySelectorAll('.category-nav a').forEach(link => link.addEventListener('click', () => {
    menuSearch.value = ''; menuSearch.dispatchEvent(new Event('input'));
  }));
}
