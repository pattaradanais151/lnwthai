<?php
/** Path: admin/index.php — Admin Dashboard (Phase 1: บัญชี เซสชัน สิทธิ์ และกิจกรรม) */
declare(strict_types=1);
require __DIR__ . '/includes/guard.php';

$me = admin_require();
$uid = (int) $me['id'];
$now = now();
$idleSince = date('Y-m-d H:i:s', time() - (int) config('session.admin_idle', 1800));

$stats = null;
if (admin_can('dashboard.view')) {
    $stats = [
        'customers' => (int) DB::value("SELECT COUNT(*) FROM users WHERE account_type = 'customer'"),
        'admins'    => (int) DB::value("SELECT COUNT(*) FROM users WHERE account_type = 'staff' AND status = 'active'"),
        'online'    => (int) DB::value(
            'SELECT COUNT(DISTINCT user_id) FROM user_sessions
             WHERE context = ? AND revoked_at IS NULL AND expires_at > ? AND last_activity_at > ?',
            ['admin', $now, $idleSince]
        ),
        'products'  => (int) DB::value('SELECT COUNT(*) FROM products'),
    ];
}

$online = admin_can('admin.view') ? DB::all(
    'SELECT u.id, u.display_name, u.email, MAX(s.last_activity_at) AS last_activity, COUNT(*) AS session_count
     FROM user_sessions s JOIN users u ON u.id = s.user_id
     WHERE s.context = ? AND s.revoked_at IS NULL AND s.expires_at > ? AND s.last_activity_at > ?
     GROUP BY u.id, u.display_name, u.email
     ORDER BY last_activity DESC LIMIT 20',
    ['admin', $now, $idleSince]
) : [];

$mySessions = DB::all(
    'SELECT id, ip, user_agent, created_at, last_activity_at FROM user_sessions
     WHERE user_id = ? AND context = ? AND revoked_at IS NULL AND expires_at > ? AND last_activity_at > ?
     ORDER BY last_activity_at DESC',
    [$uid, 'admin', $now, $idleSince]
);

$myPerms = Rbac::permissions($uid);
$permGroups = [];
foreach (Rbac::allPermissions() as $p) {
    if (isset($myPerms[$p['slug']])) {
        $permGroups[$p['group_key']][] = $p;
    }
}

$recent = admin_can('audit.view') ? DB::all(
    'SELECT created_at, actor_label, action, target_type, target_id, description, ip
     FROM audit_logs ORDER BY id DESC LIMIT 10'
) : [];

$pageTitle = 'แดชบอร์ด';
$activeNav = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <h1>สวัสดี, <?= e($me['display_name']) ?></h1>
  <p class="muted">
    <?= Rbac::isOwner($uid) ? 'คุณเป็นเจ้าของระบบ (Owner) — มีสิทธิ์ทั้งหมด' : 'คุณเห็นเฉพาะข้อมูลตามสิทธิ์ที่ได้รับ' ?>
  </p>
</div>

<?php if ($stats !== null): ?>
<section class="grid-4" aria-label="ภาพรวม">
  <div class="card stat"><span class="stat-label"><?= icon('users') ?> ลูกค้าทั้งหมด</span><span class="stat-value"><?= number_format($stats['customers']) ?></span></div>
  <div class="card stat"><span class="stat-label"><?= icon('shield-check') ?> ผู้ดูแลที่ใช้งานอยู่</span><span class="stat-value"><?= number_format($stats['admins']) ?></span></div>
  <div class="card stat"><span class="stat-label"><?= icon('activity') ?> ผู้ดูแลออนไลน์ขณะนี้</span><span class="stat-value"><?= number_format($stats['online']) ?></span></div>
  <div class="card stat"><span class="stat-label"><?= icon('gamepad-2') ?> สินค้าทั้งหมด</span><span class="stat-value"><?= number_format($stats['products']) ?></span></div>
</section>
<?php endif; ?>

<div class="grid-2 section-sm">
  <section class="card">
    <h2 class="h3 card-title"><?= icon('monitor') ?> เซสชันของฉัน</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>เริ่มใช้งาน</th><th>ล่าสุด</th><th>IP</th><th>อุปกรณ์</th></tr></thead>
        <tbody>
        <?php foreach ($mySessions as $s): ?>
          <tr>
            <td><?= e(format_datetime($s['created_at'])) ?>
              <?php if ((int) $s['id'] === (int) $me['session_id']): ?><span class="badge badge-ok">ปัจจุบัน</span><?php endif; ?></td>
            <td><?= e(format_datetime($s['last_activity_at'])) ?></td>
            <td class="mono"><?= e($s['ip'] ?? '—') ?></td>
            <td class="truncate" title="<?= e($s['user_agent'] ?? '') ?>"><?= e(mb_strimwidth((string) ($s['user_agent'] ?? '—'), 0, 40, '…')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="card">
    <h2 class="h3 card-title"><?= icon('lock') ?> สิทธิ์ของฉัน (<?= count($myPerms) ?>)</h2>
    <?php if (!$permGroups): ?>
      <p class="muted">ยังไม่ได้รับสิทธิ์ใด ๆ กรุณาติดต่อ Owner</p>
    <?php else: ?>
      <dl class="dl perm-list">
        <?php foreach ($permGroups as $group => $perms): ?>
          <dt><?= e(ucfirst((string) $group)) ?></dt>
          <dd><?php foreach ($perms as $p): ?><span class="chip<?= (int) $p['is_sensitive'] === 1 ? ' chip-warn' : '' ?>" title="<?= e($p['slug']) ?>"><?= e($p['name']) ?></span><?php endforeach; ?></dd>
        <?php endforeach; ?>
      </dl>
    <?php endif; ?>
  </section>
</div>

<?php if ($online): ?>
<section class="card section-sm">
  <h2 class="h3 card-title"><?= icon('users') ?> ผู้ดูแลที่ออนไลน์ (ภายใน <?= (int) ceil((int) config('session.admin_idle', 1800) / 60) ?> นาที)</h2>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>ชื่อ</th><th>อีเมล</th><th>กิจกรรมล่าสุด</th><th>จำนวนเซสชัน</th></tr></thead>
      <tbody>
      <?php foreach ($online as $o): ?>
        <tr>
          <td><?= e($o['display_name']) ?><?php if ((int) $o['id'] === $uid): ?> <span class="badge badge-neutral">คุณ</span><?php endif; ?></td>
          <td><?= e($o['email']) ?></td>
          <td><?= e(format_datetime($o['last_activity'])) ?></td>
          <td><?= (int) $o['session_count'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<?php if (admin_can('audit.view')): ?>
<section class="card section-sm">
  <h2 class="h3 card-title"><?= icon('activity') ?> กิจกรรมล่าสุด</h2>
  <?php if (!$recent): ?>
    <p class="muted">ยังไม่มีบันทึกกิจกรรม</p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>เวลา</th><th>ผู้ดำเนินการ</th><th>การกระทำ</th><th>เป้าหมาย</th><th>รายละเอียด</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td class="nowrap"><?= e(format_datetime($r['created_at'])) ?></td>
          <td><?= e($r['actor_label'] ?? 'ระบบ') ?></td>
          <td class="mono"><?= e($r['action']) ?></td>
          <td class="mono"><?= e(trim(($r['target_type'] ?? '') . ' #' . ($r['target_id'] ?? ''), ' #')) ?: '—' ?></td>
          <td><?= e($r['description'] ?? '') ?></td>
          <td class="mono"><?= e($r['ip'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>