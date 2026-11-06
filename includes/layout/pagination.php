<?php
/**
 * Path: includes/layout/pagination.php
 * ต้องมี: $pager (page, pages), $pagerPath (เช่น 'shop'), $pagerParams (ไม่รวม page)
 */
defined('LNW_ROOT') || exit;
$__page = (int) ($pager['page'] ?? 1);
$__pages = (int) ($pager['pages'] ?? 1);
if ($__pages > 1):
    $__link = static fn(int $n): string => Format::query($pagerPath, array_merge($pagerParams, ['page' => $n > 1 ? $n : null]));
    $__nums = array_unique(array_merge([1, $__pages], range(max(1, $__page - 2), min($__pages, $__page + 2))));
    sort($__nums);
?>
<nav class="pagination" aria-label="เลขหน้า">
  <?php if ($__page > 1): ?>
    <a class="page-link" href="<?= e($__link($__page - 1)) ?>" rel="prev" aria-label="หน้าก่อนหน้า"><?= icon('chevron-left') ?></a>
  <?php else: ?>
    <span class="page-link is-disabled" aria-hidden="true"><?= icon('chevron-left') ?></span>
  <?php endif; ?>
  <?php $__prev = 0; foreach ($__nums as $__n): ?>
    <?php if ($__n - $__prev > 1): ?><span class="page-gap">…</span><?php endif; ?>
    <?php if ($__n === $__page): ?>
      <span class="page-link is-active" aria-current="page"><?= $__n ?></span>
    <?php else: ?>
      <a class="page-link" href="<?= e($__link($__n)) ?>"><?= $__n ?></a>
    <?php endif; ?>
  <?php $__prev = $__n; endforeach; ?>
  <?php if ($__page < $__pages): ?>
    <a class="page-link" href="<?= e($__link($__page + 1)) ?>" rel="next" aria-label="หน้าถัดไป"><?= icon('chevron-right') ?></a>
  <?php else: ?>
    <span class="page-link is-disabled" aria-hidden="true"><?= icon('chevron-right') ?></span>
  <?php endif; ?>
</nav>
<?php endif; ?>