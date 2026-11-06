<?php
/** Path: includes/layout/product-card.php — ต้องมีตัวแปร $p (แถวจาก Catalog) */
defined('LNW_ROOT') || exit;
$__img = Format::imageUrl($p['image_url'] ?? null);
$__ok = Catalog::isPurchasable($p);
$__off = Format::discountPercent($p['price'], $p['compare_at_price'] ?? null);
$__meta = array_filter([$p['platform'] ?? '', $p['server_region'] ?? '', $p['level_rank'] ?? '']);
?>
<article class="product-card<?= $__ok ? '' : ' is-soldout' ?>">
  <a class="product-link" href="<?= e(url('product/' . $p['slug'])) ?>">
    <div class="product-media">
      <?php if ($__img): ?>
        <img src="<?= e($__img) ?>" alt="<?= e($p['title']) ?>" loading="lazy" decoding="async">
      <?php else: ?>
        <div class="media-placeholder" aria-hidden="true"><?= e(Format::initials($p['game_name'])) ?></div>
      <?php endif; ?>
      <div class="product-flags">
        <?php if ($__off): ?><span class="badge badge-error">-<?= (int) $__off ?>%</span><?php endif; ?>
        <?php if (!$__ok): ?><span class="badge badge-neutral">สินค้าหมด</span><?php endif; ?>
      </div>
    </div>
    <div class="product-body">
      <span class="product-game"><?= e($p['game_name']) ?></span>
      <h3 class="product-title"><?= e($p['title']) ?></h3>
      <?php if ($__meta): ?><p class="product-meta"><?= e(implode(' · ', $__meta)) ?></p><?php endif; ?>
      <div class="product-price">
        <span class="price"><?= e(Format::money($p['price'])) ?></span>
        <?php if ($__off): ?><s class="price-old"><?= e(Format::money($p['compare_at_price'])) ?></s><?php endif; ?>
      </div>
    </div>
  </a>
</article>