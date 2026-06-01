<?php
$config   = require __DIR__ . '/config.php';
$data     = require __DIR__ . '/products.php';
$cats     = $data['categories'];
$products = $data['products'];

// カテゴリ絞り込み
$cat = isset($_GET['cat']) ? (string)$_GET['cat'] : 'all';
if ($cat !== 'all' && !isset($cats[$cat])) { $cat = 'all'; }
$filtered = $cat === 'all'
    ? $products
    : array_values(array_filter($products, function ($p) use ($cat) { return $p['category'] === $cat; }));

// ページング（1ページ最大40件）
$perPage = 40;
$total   = count($filtered);
$pages   = max(1, (int)ceil($total / $perPage));
$page    = max(1, min($pages, (int)($_GET['page'] ?? 1)));
$items   = array_slice($filtered, ($page - 1) * $perPage, $perPage);

/** ページURL生成（cat を維持） */
function list_url($cat, $page) {
    $q = ['cat' => $cat, 'page' => $page];
    if ($cat === 'all') { unset($q['cat']); }
    return 'list.php' . ($q ? '?' . http_build_query($q) : '');
}

$page_title = '商品一覧 | ' . $config['company_name_ja'] . ' SHOP';
$active     = 'shop';
require __DIR__ . '/partials/header.php';
?>

<section class="list-head">
  <div class="container">
    <div class="eyebrow">ALL PRODUCTS</div>
    <h1>商品一覧</h1>
    <p>美容家電・生活家電・キッチン家電のラインナップ</p>
  </div>
</section>

<section class="section" id="products" style="padding-top:40px;">
  <div class="container">
    <div class="cat-filter">
      <a href="<?= list_url('all', 1) ?>" class="cat-btn <?= $cat === 'all' ? 'active' : '' ?>"><i class="fa-solid fa-border-all"></i><span>すべて</span></a>
      <?php foreach ($cats as $slug => $c): ?>
      <a href="<?= list_url($slug, 1) ?>" class="cat-btn <?= $cat === $slug ? 'active' : '' ?>"><i class="fa-solid <?= htmlspecialchars($c['icon']) ?>"></i><span><?= htmlspecialchars($c['name']) ?></span></a>
      <?php endforeach; ?>
    </div>

    <?php if ($total === 0): ?>
      <div class="empty-state">該当する商品がありません。</div>
    <?php else: ?>
    <div class="product-grid">
      <?php foreach ($items as $p) { include __DIR__ . '/partials/product_card.php'; } ?>
    </div>

    <?php if ($pages > 1): ?>
    <nav class="pager" aria-label="ページ送り">
      <?php if ($page > 1): ?><a class="pager-btn" href="<?= list_url($cat, $page - 1) ?>" rel="prev"><i class="fa-solid fa-chevron-left"></i></a><?php else: ?><span class="pager-btn disabled"><i class="fa-solid fa-chevron-left"></i></span><?php endif; ?>
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === $page): ?><span class="pager-num active"><?= $i ?></span>
        <?php else: ?><a class="pager-num" href="<?= list_url($cat, $i) ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $pages): ?><a class="pager-btn" href="<?= list_url($cat, $page + 1) ?>" rel="next"><i class="fa-solid fa-chevron-right"></i></a><?php else: ?><span class="pager-btn disabled"><i class="fa-solid fa-chevron-right"></i></span><?php endif; ?>
    </nav>
    <p class="pager-info"><?= $total ?> 件中 <?= ($page - 1) * $perPage + 1 ?>〜<?= min($page * $perPage, $total) ?> 件を表示（<?= $page ?> / <?= $pages ?> ページ）</p>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
