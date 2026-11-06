<?php
/**
 * Lnwthai — System diagnostics
 * Path: admin/system-check.php
 * สิทธิ์ที่ต้องมี: system.check (ค่าเริ่มต้น: Owner เท่านั้น)
 * ต้องใช้คู่กับ includes/SupabaseStorage.php เวอร์ชันที่มีเมธอด transport()
 */
declare(strict_types=1);
require __DIR__ . '/includes/guard.php';

admin_require('system.check');

$checks = [];
$add = static function (string $status, string $label, string $detail) use (&$checks): void {
    $checks[] = ['status' => $status, 'label' => $label, 'detail' => $detail];
};

// ------------------------------------------------------------ PHP
$add(PHP_VERSION_ID >= 80100 ? 'ok' : 'error', 'PHP Version', PHP_VERSION . ' (ต้องการ 8.1+)');

$extNotes = [
    'gd'   => 'แนะนำ (ใช้ล้าง metadata รูปภาพ)',
    'curl' => 'แนะนำ (ถ้าไม่มี ระบบจะใช้ PHP HTTPS stream แทน)',
];
foreach (['pdo_mysql' => true, 'openssl' => true, 'mbstring' => true, 'curl' => false, 'fileinfo' => true, 'json' => true, 'gd' => false] as $ext => $required) {
    $loaded = extension_loaded($ext);
    $add(
        $loaded ? 'ok' : ($required ? 'error' : 'warn'),
        "Extension: {$ext}",
        $loaded ? 'พร้อมใช้งาน' : ($required ? 'จำเป็นต้องมี' : ($extNotes[$ext] ?? 'แนะนำ'))
    );
}

$transport = SupabaseStorage::transport();
$add($transport !== null ? 'ok' : 'error', 'HTTPS transport (Supabase)', match ($transport) {
    'curl'   => 'PHP cURL',
    'stream' => 'PHP HTTPS stream (allow_url_fopen)',
    default  => 'ไม่มีทั้ง cURL และ allow_url_fopen — เชื่อมต่อ Supabase ไม่ได้',
});

$gcm = function_exists('openssl_get_cipher_methods') && in_array('aes-256-gcm', openssl_get_cipher_methods(), true);
$add($gcm ? 'ok' : 'error', 'AES-256-GCM', $gcm ? 'รองรับ (ใช้เข้ารหัสบัญชีเกมใน Phase 3)' : 'ไม่รองรับ');

// ------------------------------------------------------------ Database
$requiredTables = ['users', 'roles', 'permissions', 'user_roles', 'role_permissions', 'user_permissions', 'user_sessions',
    'login_attempts', 'games', 'categories', 'products', 'game_accounts', 'product_images', 'orders', 'order_items',
    'payment_submissions', 'refunds', 'order_status_history', 'settings', 'banners', 'audit_logs'];
try {
    $add('ok', 'Database', (string) DB::value('SELECT VERSION()'));

    $tables = DB::run('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $missing = array_diff($requiredTables, $tables);
    $add($missing === [] ? 'ok' : 'error', 'โครงสร้างตาราง',
        $missing === [] ? 'ครบ ' . count($requiredTables) . ' ตาราง' : 'ขาด: ' . implode(', ', $missing));

    $owners = (int) DB::value("SELECT COUNT(*) FROM users WHERE owner_lock = 1 AND account_type = 'staff'");
    $add($owners === 1 ? 'ok' : 'error', 'บัญชี Owner', "พบ {$owners} บัญชี (ต้องมี 1)");

    $unlocked = (int) DB::value(
        "SELECT COUNT(DISTINCT u.id) FROM users u
         JOIN user_roles ur ON ur.user_id = u.id
         JOIN roles r ON r.id = ur.role_id AND r.slug = 'owner'
         WHERE u.owner_lock IS NULL OR u.account_type <> 'staff'"
    );
    $add($unlocked === 0 ? 'ok' : 'error', 'บทบาท Owner ที่ไม่ถูกล็อก',
        $unlocked === 0 ? 'ไม่พบ' : "พบ {$unlocked} บัญชี — บัญชีเหล่านี้ไม่ได้รับสิทธิ์ใด ๆ จนกว่าจะแก้ด้วย SQL");

    $add('ok', 'Roles / Permissions',
        DB::value('SELECT COUNT(*) FROM roles') . ' roles · ' . DB::value('SELECT COUNT(*) FROM permissions') . ' permissions');

    $drift = abs(strtotime((string) DB::value('SELECT NOW()')) - time());
    $add($drift <= 120 ? 'ok' : 'warn', 'Timezone PHP ↔ DB', 'ต่างกัน ' . $drift . ' วินาที (' . date_default_timezone_get() . ')');
} catch (Throwable $e) {
    error_log('System check DB: ' . $e->getMessage());
    $add('error', 'Database', 'ตรวจสอบไม่สำเร็จ ดู storage/logs');
}

// ------------------------------------------------------------ Security config
$add(strlen((string) config('security.app_key', '')) >= 64 ? 'ok' : 'error', 'security.app_key', 'ต้องยาว ≥ 64 ตัวอักษร');
$add(config('app.debug') ? 'error' : 'ok', 'Debug mode', config('app.debug') ? 'เปิดอยู่ — ต้องปิดบน Production' : 'ปิด');
$add(is_https() ? 'ok' : 'warn', 'HTTPS', is_https() ? 'ใช้งาน HTTPS (Secure cookies เปิด)' : 'ยังไม่ใช่ HTTPS');
$add(config('install.enabled') ? 'error' : 'ok', 'install.enabled', config('install.enabled') ? 'ยังเปิดอยู่ — ต้องตั้งเป็น false' : 'ปิดแล้ว');

$hasLock = is_file(LNW_ROOT . '/storage/install.lock');
$add($hasLock ? 'ok' : 'warn', 'install.lock', $hasLock ? 'มีไฟล์ล็อก' : 'ไม่พบไฟล์ล็อก');

$hasInstallDir = is_dir(LNW_ROOT . '/install');
$add($hasInstallDir ? 'warn' : 'ok', 'โฟลเดอร์ install/', $hasInstallDir ? 'ยังอยู่ — ควรลบทิ้ง' : 'ลบแล้ว');

foreach (['storage/logs', 'storage/sessions', 'storage/tmp'] as $dir) {
    $w = is_dir(LNW_ROOT . '/' . $dir) && is_writable(LNW_ROOT . '/' . $dir);
    $add($w ? 'ok' : 'error', "เขียนได้: {$dir}", $w ? 'OK' : 'ไม่สามารถเขียนได้');
}

// ------------------------------------------------------------ Supabase key type (ไม่แสดงค่า key)
$sbKey = (string) config('supabase.service_key', '');
if (!SupabaseStorage::isConfigured()) {
    $add('warn', 'Supabase', 'ยังไม่ได้ตั้งค่า supabase.url / supabase.service_key');
} elseif (str_starts_with($sbKey, 'sb_publishable_')) {
    $add('error', 'Supabase key', 'เป็น publishable key — ต้องใช้ secret key');
} elseif (str_starts_with($sbKey, 'sb_secret_')) {
    $add('ok', 'Supabase key', 'secret key (sb_secret_…)');
} elseif (str_starts_with($sbKey, 'eyJ')) {
    $payload = json_decode((string) base64_decode(strtr(explode('.', $sbKey)[1] ?? '', '-_', '+/')), true);
    $role = is_array($payload) ? (string) ($payload['role'] ?? '') : '';
    $add($role === 'service_role' ? 'ok' : 'error', 'Supabase key',
        $role === 'service_role' ? 'legacy service_role JWT' : "JWT role = “{$role}” — ต้องเป็น service_role");
} else {
    $add('warn', 'Supabase key', 'ไม่รู้จักรูปแบบ key');
}

// ------------------------------------------------------------ Secret leak scan: assets/ ต้องไม่มี secret
$secrets = array_filter(
    [config('db.pass'), $sbKey, config('security.app_key'), config('install.token')],
    static fn($s): bool => is_string($s) && strlen($s) >= 8
);
$leaks = [];
if (is_dir(LNW_ROOT . '/assets')) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(LNW_ROOT . '/assets', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && $f->getSize() < 2_000_000) {
            $content = (string) file_get_contents($f->getPathname());
            foreach ($secrets as $s) {
                if (str_contains($content, $s)) {
                    $leaks[] = substr($f->getPathname(), strlen(LNW_ROOT) + 1);
                    break;
                }
            }
        }
    }
}
$add($leaks === [] ? 'ok' : 'error', 'Secret ใน Frontend (assets/)', $leaks === [] ? 'ไม่พบ' : 'พบใน: ' . implode(', ', $leaks));

// ------------------------------------------------------------ View data
$sb = $_SESSION['_sb_results'] ?? null;
unset($_SESSION['_sb_results']);

$probes = ['config/config.php', 'config/config.sample.php', 'includes/bootstrap.php', 'includes/DB.php',
    'database/schema.sql', 'storage/install.lock', 'storage/logs/', 'admin/includes/guard.php', '.htaccess', 'install/'];
$labels = ['ok' => 'ผ่าน', 'warn' => 'เตือน', 'error' => 'ไม่ผ่าน'];

$summary = ['ok' => 0, 'warn' => 0, 'error' => 0];
foreach ($checks as $c) {
    $summary[$c['status']]++;
}

$pageTitle = 'ตรวจสอบระบบ';
$activeNav = 'system';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <h1>ตรวจสอบระบบ</h1>
  <p class="muted">ผลตรวจจากเซิร์ฟเวอร์จริง ณ <?= e(date('d/m/Y H:i:s')) ?></p>
  <p>
    <span class="badge badge-ok">ผ่าน <?= $summary['ok'] ?></span>
    <span class="badge badge-warn">เตือน <?= $summary['warn'] ?></span>
    <span class="badge badge-error">ไม่ผ่าน <?= $summary['error'] ?></span>
  </p>
</div>

<section class="card">
  <h2 class="h3 card-title"><?= icon('database') ?> เซิร์ฟเวอร์และการตั้งค่า</h2>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>สถานะ</th><th>รายการ</th><th>รายละเอียด</th></tr></thead>
      <tbody>
      <?php foreach ($checks as $c): ?>
        <tr>
          <td><span class="badge badge-<?= e($c['status']) ?>"><?= e($labels[$c['status']]) ?></span></td>
          <td><?= e($c['label']) ?></td>
          <td><?= e($c['detail']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card section-sm">
  <h2 class="h3 card-title"><?= icon('lock') ?> ทดสอบการปิดกั้นไฟล์ภายใน (จาก Browser)</h2>
  <p class="muted small">ผ่าน = HTTP 403/404 หรือ HTTP 200 แบบไม่มีเนื้อหา · ทดสอบจาก Browser เพราะ InfinityFree บล็อกการเรียก URL ตัวเองจากเซิร์ฟเวอร์</p>
  <div class="table-wrap">
    <table class="table" data-probe-table>
      <thead><tr><th>URL</th><th>ผล</th></tr></thead>
      <tbody>
      <?php foreach ($probes as $p): ?>
        <tr data-probe="<?= e(url($p)) ?>">
          <td class="mono">/<?= e($p) ?></td>
          <td data-probe-result><span class="badge badge-neutral">กำลังทดสอบ…</span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card section-sm" id="supabase">
  <h2 class="h3 card-title"><?= icon('cloud') ?> Supabase Storage</h2>
  <p class="muted small">
    Public bucket: <span class="mono"><?= e(config('supabase.public_bucket')) ?></span> ·
    Private bucket: <span class="mono"><?= e(config('supabase.private_bucket')) ?></span> ·
    ช่องทาง: <span class="mono"><?= e($transport ?? 'ไม่มี') ?></span>
  </p>

  <?php if (is_array($sb) && isset($sb['items']) && is_array($sb['items'])): ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>สถานะ</th>
            <th>ผลลัพธ์ (<?= e(($sb['op'] ?? '') === 'buckets' ? 'ตั้งค่า Buckets' : 'Self-test') ?> · <?= e(format_datetime($sb['at'] ?? null)) ?>)</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($sb['items'] as [$st, $msg]): ?>
          <tr>
            <td><span class="badge badge-<?= e($st) ?>"><?= e($labels[$st] ?? $st) ?></span></td>
            <td><?= e($msg) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if (!SupabaseStorage::isConfigured()): ?>
    <p class="muted">กรอก <span class="mono">supabase.url</span> และ <span class="mono">supabase.service_key</span> ใน config/config.php ก่อน</p>
  <?php elseif ($transport === null): ?>
    <p class="text-danger">เซิร์ฟเวอร์ไม่มีทั้ง PHP cURL และ allow_url_fopen จึงเชื่อมต่อ Supabase ไม่ได้</p>
  <?php else: ?>
    <div class="row-actions">
      <form method="post" action="<?= e(url('admin/actions/supabase.php')) ?>" data-confirm="สร้างหรืออัปเดต bucket ตามค่าใน config?">
        <?= Csrf::field() ?><input type="hidden" name="op" value="buckets">
        <button class="btn btn-secondary" type="submit">สร้าง/อัปเดต Buckets</button>
      </form>
      <form method="post" action="<?= e(url('admin/actions/supabase.php')) ?>">
        <?= Csrf::field() ?><input type="hidden" name="op" value="selftest">
        <button class="btn btn-primary" type="submit">ทดสอบ Upload / Signed URL / Delete</button>
      </form>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>