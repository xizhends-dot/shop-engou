<?php
/**
 * 商品カード（共通パーツ）
 * 期待する変数: $p（商品データ）, $cats（カテゴリ配列）
 */
$thumb = !empty($p['images']) ? $p['images'][0] : '';
$cat   = $cats[$p['category']] ?? null;
?>
<article class="product-card" data-category="<?= htmlspecialchars($p['category']) ?>">
  <a href="product.php?id=<?= urlencode($p['id']) ?>" class="product-thumb <?= $thumb === '' ? 'placeholder' : '' ?>" <?= $thumb === '' ? 'style="' . shop_gradient_style($p['accent'] ?? '') . '"' : '' ?>>
  <?php if ($thumb !== ''): ?>
    <img src="<?= shop_e($thumb) ?>" alt="<?= shop_e($p['name']) ?>">
  <?php else: ?>
    <i class="fa-solid <?= shop_e(shop_sanitize_icon($p['icon'] ?? 'fa-box')) ?> ph-icon"></i>
  <?php endif; ?>
    <?php if (!empty($p['badge'])): ?>
    <span class="badge"><?= htmlspecialchars($p['badge']) ?></span>
    <?php endif; ?>
    <?php if ($cat): ?>
    <span class="product-cat-tag"><?= htmlspecialchars($cat['name']) ?></span>
    <?php endif; ?>
  </a>
  <div class="product-body">
    <h3><?= htmlspecialchars($p['name']) ?></h3>
    <p class="p-tag"><?= htmlspecialchars($p['tag']) ?></p>
    <?php if (!empty($p['rating']) || !empty($p['reviews'])): ?>
    <div class="p-rating">
      <span class="stars"><?= render_stars($p['rating'] ?? 0) ?></span>
      <?php if (!empty($p['reviews'])): ?><span class="p-rating-cnt">(<?= number_format((int)$p['reviews']) ?>)</span><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="product-foot">
      <span class="price"><?= yen($p['price']) ?></span>
      <a href="product.php?id=<?= urlencode($p['id']) ?>" class="btn-detail">詳細を見る</a>
    </div>
  </div>
</article>
