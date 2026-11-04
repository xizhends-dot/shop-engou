<?php
// カテゴリ別の商品情報を一度集計する。空カテゴリは表示しない。
$homeGroups = [];
foreach ($cats as $slug => $category) {
    $categoryProducts = array_values(array_filter($products, function ($item) use ($slug) {
        return ($item['category'] ?? '') === $slug;
    }));
    if (!$categoryProducts) { continue; }
    $cover = '';
    foreach ($categoryProducts as $item) {
        if (!empty($item['images'][0])) { $cover = $item['images'][0]; break; }
    }
    $homeGroups[$slug] = ['category' => $category, 'products' => $categoryProducts, 'cover' => $cover];
}
?>
<div class="catalog-service-strip">
  <div class="container catalog-service-grid">
    <a href="list.php"><i class="fa-solid fa-layer-group" aria-hidden="true"></i><span>商品を比較する<small>仕様・画像・税込価格をチェック</small></span></a>
    <a href="#shopping-guide"><i class="fa-regular fa-compass" aria-hidden="true"></i><span>はじめての方へ<small>商品選びからお問い合わせまで</small></span></a>
    <a href="contact.php"><i class="fa-regular fa-comments" aria-hidden="true"></i><span>商品について相談する<small>気になることはお気軽に</small></span></a>
  </div>
</div>
<section class="section catalog-categories" id="categories">
  <div class="container">
    <div class="catalog-section-heading"><div><span class="catalog-kicker">カテゴリー</span><h2>気になるカテゴリーから。</h2></div><a class="catalog-text-link" href="list.php">すべての商品 <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></div>
    <p class="catalog-section-intro">お探しのアイテムを、カテゴリーごとにご覧いただけます。</p>
    <?php if (!$homeGroups): ?><p class="empty-state">商品情報を準備しています。商品に関するご相談は<a class="catalog-text-link" href="contact.php">お問い合わせ窓口</a>へお寄せください。</p><?php endif; ?>
    <div class="catalog-category-grid">
      <?php foreach ($homeGroups as $slug => $group): $category = $group['category']; ?>
      <a class="catalog-category-card" href="list.php?cat=<?= rawurlencode($slug) ?>">
        <div class="catalog-category-art">
          <?php if ($group['cover'] !== ''): ?><img src="<?= shop_e($group['cover']) ?>" alt="<?= shop_e($category['name']) ?>" loading="lazy"><?php else: ?><i class="fa-solid <?= shop_e(shop_sanitize_icon($category['icon'] ?? 'fa-box')) ?>" aria-hidden="true"></i><?php endif; ?>
        </div>
        <div class="catalog-category-copy"><div><h3><?= shop_e($category['name']) ?></h3><span><?= count($group['products']) ?> 点の商品</span></div><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
