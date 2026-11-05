/* Path: assets/js/admin.js — ทดสอบการปิดกั้นไฟล์ภายในจาก Browser (หน้า System Check) */
'use strict';
(() => {
  const setResult = (cell, pass, note) => {
    const badge = document.createElement('span');
    badge.className = 'badge ' + (pass === true ? 'badge-ok' : pass === false ? 'badge-error' : 'badge-warn');
    badge.textContent = pass === true ? 'ผ่าน' : pass === false ? 'ไม่ผ่าน' : 'ตรวจเอง';
    const text = document.createElement('span');
    text.className = 'muted small';
    text.textContent = ' ' + note;
    cell.replaceChildren(badge, text);
  };

  const probe = async (row) => {
    const cell = row.querySelector('[data-probe-result]');
    try {
      const res = await fetch(row.dataset.probe, { cache: 'no-store', redirect: 'manual', credentials: 'same-origin' });
      if (res.type === 'opaqueredirect') {
        setResult(cell, null, 'ถูก Redirect — เปิด URL ด้วยตนเองเพื่อตรวจ');
      } else if ([401, 403, 404].includes(res.status)) {
        setResult(cell, true, `HTTP ${res.status} (ถูกบล็อก)`);
      } else if (res.status === 200) {
        const body = (await res.text()).trim();
        if (body === '') setResult(cell, true, 'HTTP 200 แต่ไม่มีเนื้อหา (สคริปต์ปฏิเสธการรันตรง)');
        else setResult(cell, false, `HTTP 200 และมีเนื้อหา ${body.length} ไบต์ — เข้าถึงได้!`);
      } else {
        setResult(cell, null, `HTTP ${res.status}`);
      }
    } catch (err) {
      setResult(cell, null, 'ทดสอบไม่ได้: ' + err.message);
    }
  };

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-probe-table] tr[data-probe]').forEach(probe);
  });
})();