<?php
/** Path: admin/vault.php — สถานะระบบเข้ารหัส + วิธีสร้างไฟล์ key (สิทธิ์ system.check) */
declare(strict_types=1);
require __DIR__ . '/includes/guard.php';

admin_require('system.check');

$keyFile = Vault::keyFile();
$relKey = str_starts_with($keyFile, LNW_ROOT) ? substr($keyFile, strlen(LNW_ROOT) + 1) : $keyFile;
$ready = Vault::ready();
$gcm = function_exists('openssl_get_cipher_methods') && in_array('aes-256-gcm', openssl_get_cipher_methods(), true);
$insideWebRoot = str_starts_with(realpath($keyFile) ?: $keyFile, LNW_ROOT);

$checks = [
    [Vault::enabled() ? 'ok' : 'warn', 'vault.enabled', Vault::enabled() ? 'เปิดใช้งาน (รับทราบข้อจำกัดแล้ว)' : 'ปิดอยู่ — เพิ่ม/แก้ไขบัญชีเกมไม่ได้'],
    [$gcm ? 'ok' : 'error', 'AES-256-GCM', $gcm ? 'รองรับ' : 'ไม่รองรับ'],
    [is_file($keyFile) ? 'ok' : 'error', 'ไฟล์ key', $relKey . (is_file($keyFile) ? '' : ' — ไม่พบ')],
    [$insideWebRoot ? 'warn' : 'ok', 'ตำแหน่งไฟล์ key', $insideWebRoot ? 'อยู่ใน Web Root (ข้อจำกัดของ InfinityFree) — ป้องกันด้วย .htaccess' : 'อยู่นอก Web Root'],
    [$ready ? 'ok' : 'error', 'สถานะ Vault', $ready ? 'พร้อม · key ปัจจุบันเวอร์ชัน ' . Vault::currentVersion() : (string) Vault::error()],
];
if ($ready) {
    $ok = Vault::selfTest();
    $checks[] = [$ok ? 'ok' : 'error', 'ทดสอบเข้ารหัส/ถอดรหัส', $ok ? 'ผ่าน (รวมถึงตรวจว่า AAD ผิดต้องถอดไม่ได้)' : 'ไม่ผ่าน — ดู storage/logs'];
}

$byVersion = DB::all(
    "SELECT key_version, COUNT(*) AS n FROM game_accounts
     WHERE secret_payload <> '' AND secret_payload <> 'DEMO-PLACEHOLDER' AND secret_purged_at IS NULL
     GROUP BY key_version ORDER BY key_version"
);
$known = Vault::versions();
foreach ($byVersion as $row) {
    $has = in_array((int) $row['key_version'], $known, true);
    $checks[] = [$has || !$ready ? ($has ? 'ok' : 'warn') : 'error', 'ข้อมูลที่ใช้ key เวอร์ชัน ' . (int) $row['key_version'],
        number_format((int) $row['n']) . ' รายการ' . ($has ? '' : ' — ไม่พบ key เวอร์ชันนี้ในไฟล์')];
}

$template = is_file($keyFile) ? null : Vault::keyFileTemplate(base64_encode(random_bytes(32)));
$nextVersion = $ready ? max($known) + 1 : null;
$labels = ['ok' => 'ผ่าน', 'warn' => 'เตือน', 'error' => 'ไม่ผ่าน'];

$pageTitle = 'การเข้ารหัส';
$activeNav = 'vault';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <h1>การเข้ารหัสข้อมูลบัญชีเกม</h1>
  <p class="muted">AES-256-GCM · key แยกจากฐานข้อมูลและแยกจาก config.php</p>
</div>

<section class="card">
  <h2 class="h3 card-title"><?= icon('shield-check') ?> สถานะ</h2>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>สถานะ</th><th>รายการ</th><th>รายละเอียด</th></tr></thead>
      <tbody>
      <?php foreach ($checks as [$st, $label, $detail]): ?>
        <tr><td><span class="badge badge-<?= e($st) ?>"><?= e($labels[$st]) ?></span></td><td><?= e($label) ?></td><td><?= e($detail) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card section-sm">
  <h2 class="h3 card-title"><?= icon('alert-triangle') ?> ข้อจำกัดที่ต้องรับทราบก่อนเปิดใช้</h2>
  <ul class="check-list">
    <li><?= icon('check') ?><span>ป้องกันได้: ฐานข้อมูลรั่ว (SQL Injection, ไฟล์ Backup หลุด) และ config.php หลุดเพียงไฟล์เดียว</span></li>
    <li><?= icon('x') ?><span>ป้องกันไม่ได้: ผู้โจมตีเข้าถึงไฟล์บนเซิร์ฟเวอร์ (รหัส FTP/บัญชี InfinityFree รั่ว) เพราะ key อยู่บนเซิร์ฟเวอร์เดียวกัน</span></li>
    <li><?= icon('x') ?><span>ถ้าไฟล์ key หาย ข้อมูลบัญชีเกมที่เข้ารหัสไว้จะกู้คืนไม่ได้ — ต้องสำรองไฟล์ key แยกจาก Backup ฐานข้อมูล</span></li>
    <li><?= icon('check') ?><span>ลดความเสี่ยง: ใช้รหัส FTP/InfinityFree ที่แข็งแรง, ลบข้อมูลลับหลังส่งมอบ (ตั้งค่าใน 3B), แนะนำลูกค้าเปลี่ยนรหัสผ่านทันที</span></li>
  </ul>
</section>

<?php if ($template !== null): ?>
<section class="card section-sm">
  <h2 class="h3 card-title"><?= icon('key-round') ?> สร้างไฟล์ key</h2>
  <ol class="steps">
    <li>สร้างไฟล์ <span class="mono"><?= e($relKey) ?></span> ด้วยเนื้อหาด้านล่าง (สุ่มใหม่ทุกครั้งที่โหลดหน้านี้ — ใช้ชุดเดียวเท่านั้น)</li>
    <li>อัปโหลดผ่าน FTP แล้ว<strong>สำรองไฟล์นี้ไว้ในที่ปลอดภัย</strong> เช่น Password Manager</li>
    <li>แก้ <span class="mono">config/config.php</span>: <span class="mono">'vault' =&gt; ['enabled' =&gt; true, ...]</span></li>
    <li>รีเฟรชหน้านี้ ทุกแถวต้อง "ผ่าน" (ยกเว้นตำแหน่งไฟล์ ซึ่งเป็นข้อจำกัดของ Hosting)</li>
  </ol>
  <pre class="code-block"><?= e($template) ?></pre>
</section>
<?php elseif ($nextVersion !== null): ?>
<section class="card section-sm">
  <h2 class="h3 card-title"><?= icon('key-round') ?> หมุนเวียน key (เมื่อสงสัยว่า key รั่ว)</h2>
  <p class="muted small">เพิ่มบรรทัดนี้ใน <span class="mono">keys</span> แล้วเปลี่ยน <span class="mono">'current' =&gt; <?= (int) $nextVersion ?></span> · ข้อมูลเดิมยังถอดรหัสด้วย key เก่าได้ ห้ามลบ key เก่าจนกว่าจะไม่มีข้อมูลที่ใช้เวอร์ชันนั้นเหลืออยู่</p>
  <pre class="code-block"><?= e((int) $nextVersion . " => '" . base64_encode(random_bytes(32)) . "',") ?></pre>
</section>
<?php endif; ?>

<section class="card section-sm">
  <h2 class="h3 card-title"><?= icon('lock') ?> ทดสอบว่าไฟล์ key เปิดจาก Browser ไม่ได้</h2>
  <div class="table-wrap">
    <table class="table" data-probe-table>
      <thead><tr><th>URL</th><th>ผล</th></tr></thead>
      <tbody>
        <tr data-probe="<?= e(url('config/vault.key.php')) ?>"><td class="mono">/config/vault.key.php</td><td data-probe-result><span class="badge badge-neutral">กำลังทดสอบ…</span></td></tr>
      </tbody>
    </table>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>