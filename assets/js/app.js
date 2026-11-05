/* Path: assets/js/app.js — Vanilla JS (ES6+), ไม่มี inline script เพื่อให้ผ่าน CSP */
'use strict';
(() => {
  const root = document.documentElement;

  const setTheme = (theme) => {
    root.setAttribute('data-theme', theme);
    try { localStorage.setItem('lnw-theme', theme); } catch (e) { /* private mode */ }
    document.querySelectorAll('[data-theme-toggle]').forEach((b) => b.setAttribute('aria-pressed', String(theme === 'dark')));
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', theme === 'dark' ? '#000000' : '#ffffff');
  };

  document.addEventListener('DOMContentLoaded', () => {
    setTheme(root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');
    document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
      btn.addEventListener('click', () => setTheme(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'));
    });

    // Mobile navigation
    const navBtn = document.querySelector('[data-nav-toggle]');
    const menu = navBtn ? document.getElementById(navBtn.getAttribute('aria-controls')) : null;
    if (navBtn && menu) {
      navBtn.addEventListener('click', () => {
        const open = menu.classList.toggle('is-open');
        navBtn.setAttribute('aria-expanded', String(open));
      });
    }

    // Show / hide password
    document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
      const input = document.getElementById(btn.dataset.togglePassword);
      if (!input) return;
      btn.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', String(show));
      });
    });

    // Dismiss alerts
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-dismiss]');
      if (btn) btn.closest('.alert')?.remove();
    });

    // Confirm + prevent double submit
    document.querySelectorAll('form').forEach((form) => {
      form.addEventListener('submit', (e) => {
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
          e.preventDefault();
          return;
        }
        window.setTimeout(() => {
          if (!e.defaultPrevented) {
            form.querySelectorAll('button[type="submit"]').forEach((b) => { b.disabled = true; b.classList.add('is-loading'); });
          }
        }, 0);
      });
    });
  });

  // ปุ่มกลับมาใช้ได้เมื่อกด Back (bfcache)
  window.addEventListener('pageshow', () => {
    document.querySelectorAll('button.is-loading').forEach((b) => { b.disabled = false; b.classList.remove('is-loading'); });
  });
})();