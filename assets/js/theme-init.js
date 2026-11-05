/* Path: assets/js/theme-init.js — โหลดใน <head> แบบ blocking เพื่อป้องกันหน้าจอกะพริบ */
(function () {
  var t = 'light';
  try {
    t = localStorage.getItem('lnw-theme');
    if (t !== 'light' && t !== 'dark') {
      t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
  } catch (e) { t = 'light'; }
  document.documentElement.setAttribute('data-theme', t);
})();