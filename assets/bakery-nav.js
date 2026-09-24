(function () {
  'use strict';
  const nav = document.querySelector('[data-bakery-navigation]');
  if (!nav) return;

  const toggle = nav.querySelector('[data-bakery-nav-toggle]');
  if (toggle) {
    toggle.addEventListener('click', function () {
      const isOpen = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  }

  nav.querySelectorAll('details').forEach(function (details) {
    details.addEventListener('toggle', function () {
      if (!details.open) return;
      nav.querySelectorAll('details[open]').forEach(function (other) {
        if (other !== details) other.removeAttribute('open');
      });
    });
  });

  document.addEventListener('click', function (event) {
    if (!nav.contains(event.target)) {
      nav.querySelectorAll('details[open]').forEach(function (details) {
        details.removeAttribute('open');
      });
    }
  });
})();
