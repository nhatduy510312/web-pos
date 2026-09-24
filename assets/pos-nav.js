(function () {
  'use strict';

  const nav = document.querySelector('[data-pos-navigation]');
  if (!nav) return;

  const toggle = nav.querySelector('[data-nav-toggle]');
  const menu = nav.querySelector('#posNavigationMenu');
  const csrfToken = nav.dataset.csrfToken || '';

  if (toggle && menu) {
    toggle.addEventListener('click', function () {
      const isOpen = nav.classList.toggle('nav-open');
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

  document.querySelectorAll('form[method="post"]').forEach(function (form) {
    if (csrfToken && !form.querySelector('input[name="csrf_token"]')) {
      const token = document.createElement('input');
      token.type = 'hidden';
      token.name = 'csrf_token';
      token.value = csrfToken;
      form.appendChild(token);
    }

    form.addEventListener('submit', function () {
      const submitter = form.querySelector('button[type="submit"]:focus, input[type="submit"]:focus');
      if (!submitter || submitter.dataset.allowMultipleSubmit === 'true') return;
      window.setTimeout(function () {
        submitter.disabled = true;
        submitter.setAttribute('aria-disabled', 'true');
      }, 0);
    });
  });

  const clock = nav.querySelector('#navClock');
  if (clock) {
    const tick = function () {
      const date = new Date();
      const pad = function (number) { return String(number).padStart(2, '0'); };
      clock.textContent = pad(date.getHours()) + ':' + pad(date.getMinutes()) + ' · '
        + pad(date.getDate()) + '/' + pad(date.getMonth() + 1);
    };
    tick();
    window.setInterval(tick, 30000);
  }
})();
