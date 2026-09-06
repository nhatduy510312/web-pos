'use strict';
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
