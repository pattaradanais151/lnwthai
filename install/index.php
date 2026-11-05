<?php
/**
 * Lnwthai — One-time Owner installer
 * Path: install/index.php
 * ลบโฟลเดอร์ install/ ทันทีหลังติดตั้งสำเร็จ
 * ป้องกันการสร้าง Owner ซ้ำ 4 ชั้น: install.enabled, install.token, storage/install.lock, UNIQUE users.owner_lock
 */
declare(strict_types=1);
define('LNW_CONTEXT', 'install');
require dirname(__DIR__) . '/includes/bootstrap.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$lockFile = LNW_ROOT . '/storage/install.lock';
if (config('install.enabled', false) !== true) {
    render_error(404);
}
if (is_file($lockFile)) {
    render_error(403, 'ติดตั้งเสร็จแล้ว กรุณาตั้ง install.enabled = false และลบโฟลเดอร์ install/');
}
if (strlen((string) config('install.token', '')) < 32) {
    render_error(503, 'กรุณากำหนด install.token อย่างน้อย 32 ตัวอักษรใน config/config.php');
}

$requiredTables = ['users', 'roles', 'permissions', 'user_roles', 'role_permissions', 'user_permissions', 'user_sessions',
    'login_attempts', 'games', 'categories', 'products', 'game_accounts', 'product_images', 'orders', 'order_items',
    'payment_submissions', 'refunds', 'order_status_history', 'settings', 'banners', 'audit_logs'];

$checks = [];
$blocking = false;
$check = static function (bool $ok, string $label, string $detail, bool $required = true) use (&$checks, &$blocking): void {
    $checks[] = ['status' => $ok ? 'ok' : ($required ? 'error' : 'warn'), 'label' => $label, 'detail' => $detail];
    if (!$ok && $required) {
        $blocking = true;
    }
};

$check(PHP_VERSION_ID >= 80100, 'PHP 8.1+', PHP_VERSION);
foreach (['pdo_mysql', 'openssl', 'mbstring', 'curl', 'fileinfo', 'json'] as $ext) {
    $check(extension_loaded($ext), "Extension {$ext}", extension_loaded($ext) ? 'พร้อม' : 'ไม่พบ');
}
$check(extension_loaded('gd'), 'Extension gd', extension_loaded('gd') ? 'พร้อม' : 'แนะนำ', false);
$appKeyOk = strlen((string) config('security.app_key', '')) >= 64;
$check($appKeyOk, 'security.app_key', $appKeyOk ? 'ตั้งค่าแล้ว' : 'ยังไม่ได้ตั้ง (ต้อง ≥ 64 ตัว)');
$check(is_writable(LNW_ROOT . '/storage'), 'storage/ เขียนได้', is_writable(LNW_ROOT . '/storage') ? 'OK' : 'ไม่สามารถเขียนได้');
$check(is_https(), 'HTTPS', is_https() ? 'OK' : 'ยังไม่ใช่ HTTPS — รหัสผ่านจะถูกส่งแบบไม่เข้ารหัส', false);
$check(!config('app.debug'), 'Debug ปิด', config('app.debug') ? 'เปิดอยู่' : 'OK', false);

try {
    $check(true, 'เชื่อมต่อฐานข้อมูล', (string) DB::value('SELECT VERSION()'));
    $missing = array_diff($requiredTables, DB::run('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
    $check($missing === [], 'โครงสร้างตาราง', $missing === [] ? 'ครบ ' . count($requiredTables) . ' ตาราง' : 'ขาด: ' . implode(', ', $missing) . ' — Import database/schema.sql');
    if ($missing === []) {
        $roles = (int) DB::value("SELECT COUNT(*) FROM roles WHERE slug IN ('owner','super_admin','admin','staff','customer')");
        $check($roles === 5, 'ข้อมูลบทบาทเริ่มต้น', "{$roles}/5");
        if ((int) DB::value('SELECT COUNT(*) FROM users WHERE owner_lock = 1') > 0) {
            @file_put_contents($lockFile, 'owner-exists ' . now() . PHP_EOL);
            render_error(403, 'มีบัญชี Owner อยู่แล้ว ไม่สามารถสร้างซ้ำได้ กรุณาลบโฟลเดอร์ install/');
        }
    }
} catch (RuntimeException | PDOException $e) {
    error_log('Installer DB: ' . $e->getMessage());
    $check(false, 'เชื่อมต่อฐานข้อมูล', 'เชื่อมต่อไม่ได้ — ตรวจสอบค่า db ใน config/config.php');
}

$errors = [];
$done = null;

if (is_post()) {
    Csrf::verify();
    $ip = client_ip();
    if ($blocking) {
        $errors[] = 'กรุณาแก้ไขรายการที่ไม่ผ่านก่อน';
    } elseif (RateLimiter::tooManyActions('install', $ip, 10, 3600)) {
        $errors[] = 'พยายามบ่อยเกินไป กรุณารอ 1 ชั่วโมง';
    } elseif (!hash_equals((string) config('install.token'), post_raw('install_token'))) {
        RateLimiter::record('install', 'installer', $ip, false, null, 'bad_token');
        $errors[] = 'Installation Token ไม่ถูกต้อง';
    } else {
        $v = new Validator($_POST);
        $v->required('display_name', 'ชื่อที่แสดง')->length('display_name', 2, 100, 'ชื่อที่แสดง')
          ->required('email', 'อีเมล')->email('email')
          ->required('username', 'ชื่อผู้ใช้')->username('username')
          ->password('password', 12)
          ->same('password', 'password_confirmation', 'รหัสผ่านยืนยันไม่ตรงกัน');
        if ($v->fails()) {
            $errors = array_values($v->errors());
        } else {
            $email = mb_strtolower($v->value('email'));
            try {
                $ownerId = (int) DB::transaction(static function () use ($v, $email): int {
                    if (DB::value('SELECT id FROM users WHERE owner_lock = 1 FOR UPDATE') !== null) {
                        throw new DomainException('owner_exists');
                    }
                    $now = now();
                    $id = DB::insert(
                        "INSERT INTO users (account_type, email, username, display_name, password_hash, status, owner_lock,
                                            password_changed_at, created_at, updated_at)
                         VALUES ('staff', ?, ?, ?, ?, 'active', 1, ?, ?, ?)",
                        [$email, $v->value('username'), $v->value('display_name'), Auth::hashPassword($v->raw('password')), $now, $now, $now]
                    );
                    $roleId = (int) DB::value("SELECT id FROM roles WHERE slug = 'owner'");
                    DB::run('INSERT INTO user_roles (user_id, role_id, assigned_at) VALUES (?, ?, ?)', [$id, $roleId, $now]);
                    return $id;
                });
                RateLimiter::record('install', 'installer', $ip, true, $ownerId, 'owner_created');
                Audit::log('install.owner_created', 'user', $ownerId, 'สร้างบัญชี Owner ผ่านตัวติดตั้ง', ['email' => $email], $ownerId, 'install');
                $locked = @file_put_contents($lockFile, 'installed ' . now() . PHP_EOL) !== false;
                $done = ['email' => $email, 'locked' => $locked];
            } catch (DomainException) {
                $errors[] = 'มีบัญชี Owner อยู่แล้ว';
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                $errors[] = 'อีเมลหรือชื่อผู้ใช้นี้ถูกใช้งานแล้ว';
            }
        }
    }
}

$labels = ['ok' => 'ผ่าน', 'warn' => 'เตือน', 'error' => 'ไม่ผ่าน'];
?><!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>ติดตั้ง Lnwthai</title>
<link rel="icon" type="image/svg+xml" href="<?= e(asset('images/logo.svg')) ?>">
<script src="<?= e(asset('js/theme-init.js')) ?>"></script>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body>
<main id="main" class="section">
  <div class="container narrow">
    <div class="page-head">
      <h1>ติดตั้ง Lnwthai</h1>
      <p class="muted">สร้างบัญชี Owner (ทำได้ครั้งเดียว)</p>
    </div>

    <?php if ($done): ?>
      <section class="card">
        <div class="alert alert-success"><?= icon('circle-check') ?><span>สร้างบัญชี Owner (<?= e($done['email']) ?>) สำเร็จ</span></div>
        <h2 class="h3">ขั้นตอนที่ต้องทำทันที</h2>
        <ol class="steps">
          <li>แก้ <span class="mono">config/config.php</span>: ตั้ง <span class="mono">'install' =&gt; ['enabled' =&gt; false, 'token' =&gt; '']</span></li>
          <li>ลบโฟลเดอร์ <span class="mono">install/</span> ออกจาก Hosting</li>
          <?php if (!$done['locked']): ?><li class="text-danger">สร้างไฟล์ storage/install.lock ไม่สำเร็จ — ตรวจสิทธิ์โฟลเดอร์ storage/ (UNIQUE owner_lock ยังกันการสร้างซ้ำอยู่)</li><?php endif; ?>
          <li>เข้าสู่ระบบที่ <a href="<?= e(url('admin/login.php')) ?>">/admin/login.php</a> แล้วเปิดหน้า “ตรวจสอบระบบ”</li>
        </ol>
      </section>
    <?php else: ?>
      <section class="card">
        <h2 class="h3 card-title"><?= icon('activity') ?> ตรวจสอบความพร้อม</h2>
        <div class="table-wrap">
          <table class="table">
            <tbody>
            <?php foreach ($checks as $c): ?>
              <tr><td><span class="badge badge-<?= e($c['status']) ?>"><?= e($labels[$c['status']]) ?></span></td><td><?= e($c['label']) ?></td><td><?= e($c['detail']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if (!$appKeyOk): ?>
          <p class="small">ค่าที่สุ่มให้สำหรับ <span class="mono">security.app_key</span> (คัดลอกไปใส่ config แล้วรีเฟรช):</p>
          <p class="code-box mono"><?= e(bin2hex(random_bytes(32))) ?></p>
        <?php endif; ?>
      </section>

      <section class="card section-sm">
        <h2 class="h3 card-title"><?= icon('user-plus') ?> สร้างบัญชี Owner</h2>
        <?php foreach ($errors as $err): ?>
          <div class="alert alert-error"><?= icon('alert-triangle') ?><span><?= e($err) ?></span></div>
        <?php endforeach; ?>
        <form method="post" class="form" autocomplete="off">
          <?= Csrf::field() ?>
          <div class="field">
            <label for="install_token">Installation Token (จาก config)</label>
            <input id="install_token" name="install_token" type="password" required maxlength="200">
          </div>
          <div class="field">
            <label for="display_name">ชื่อที่แสดง</label>
            <input id="display_name" name="display_name" type="text" required maxlength="100" value="<?= e(post_str('display_name')) ?>">
          </div>
          <div class="field">
            <label for="email">อีเมล</label>
            <input id="email" name="email" type="email" required maxlength="190" value="<?= e(post_str('email')) ?>">
          </div>
          <div class="field">
            <label for="username">ชื่อผู้ใช้ (a-z, 0-9, _ .)</label>
            <input id="username" name="username" type="text" required maxlength="30" value="<?= e(post_str('username')) ?>">
          </div>
          <div class="field">
            <label for="password">รหัสผ่าน (อย่างน้อย 12 ตัว มีตัวอักษรและตัวเลข)</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required minlength="12" maxlength="72">
          </div>
          <div class="field">
            <label for="password_confirmation">ยืนยันรหัสผ่าน</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required maxlength="72">
          </div>
          <button class="btn btn-primary btn-block" type="submit" <?= $blocking ? 'disabled' : '' ?>>สร้างบัญชี Owner</button>
        </form>
      </section>
    <?php endif; ?>
  </div>
</main>
</body>
</html>