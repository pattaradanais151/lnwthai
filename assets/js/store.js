/* Path: assets/js/store.js — Storefront interactions (Vanilla JS, ผ่าน CSP) */
'use strict';
(() => {
  document.addEventListener('DOMContentLoaded', () => {
    // Product gallery
    document.querySelectorAll('[data-gallery]').forEach((gallery) => {
      const main = gallery.querySelector('[data-gallery-main]');
      const thumbs = gallery.querySelectorAll('[data-gallery-thumb]');
      if (!main) return;
      thumbs.forEach((thumb) => {
        thumb.addEventListener('click', () => {
          main.src = thumb.dataset.src;
          main.alt = thumb.dataset.alt || '';
          thumbs.forEach((t) => t.setAttribute('aria-current', String(t === thumb)));
        });
      });
    });

    // Auto-submit (เช่น ตัวเลือกการเรียงลำดับ)
    document.querySelectorAll('[data-autosubmit]').forEach((el) => {
      el.addEventListener('change', () => {
        const form = el.form;
        if (!form) return;
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
      });
    });

    // Mobile filter panel
    document.querySelectorAll('[data-filter-toggle]').forEach((btn) => {
      const panel = document.getElementById(btn.getAttribute('aria-controls'));
      if (!panel) return;
      btn.addEventListener('click', () => {
        const open = panel.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', String(open));
      });
    });
  });
})();