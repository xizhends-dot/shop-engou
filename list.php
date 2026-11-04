<?php
$config   = require __DIR__ . '/config.php';
$data     = require __DIR__ . '/products.php';
$cats     = $data['categories'];
$products = $data['products'];

// 配列パラメータは受け付けず、カテゴリと検索語を組み合わせて絞り込む。
$cat = isset($_GET['cat']) && is_string($_GET['cat']) ? $_GET['cat'] : 'all';
$query = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
if ($cat !== 'all' && !isset($cats[$cat])) { $cat = 'all'; }
$filtered = array_values(array_filter($products, function ($p) use ($cat, $query) {
    if ($cat !== 'all' && $p['category'] !== $cat) { return false; }
    if ($query === '') { return true; }
    // 日本語・中国語は部分一致、英数字の商品番号は大文字・小文字を区別しない。
    foreach (['id', 'name'] as $field) {
        $value = (string)($p[$field] ?? '');
        $position = function_exists('mb_stripos')
            ? mb_stripos($value, $query, 0, 'UTF-8')
            : stripos($value, $query);
        if ($position !== false) { return true; }
    }
    return false;
}));

// ページング（1ページ最大40件）
$perPage = 40;
$total   = count($filtered);
$pages   = max(1, (int)ceil($total / $perPage));
$requestedPage = isset($_GET['page']) && is_string($_GET['page']) ? (int)$_GET['page'] : 1;
$page    = max(1, min($pages, $requestedPage));
$items   = array_slice($filtered, ($page - 1) * $perPage, $perPage);

/** ページURL生成（カテゴリ・検索語を維持。出力時にHTMLエスケープする） */
function list_url($cat, $page, $query = '') {
    $params = ['cat' => $cat, 'page' => $page];
    if ($cat === 'all') { unset($params['cat']); }
    if ($query !== '') { $params['q'] = $query; }
    return 'list.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
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
      <a href="<?= shop_e(list_url('all', 1, $query)) ?>" class="cat-btn <?= $cat === 'all' ? 'active' : '' ?>"><i class="fa-solid fa-border-all"></i><span>すべて</span></a>
      <?php foreach ($cats as $slug => $c): ?>
      <a href="<?= shop_e(list_url($slug, 1, $query)) ?>" class="cat-btn <?= $cat === $slug ? 'active' : '' ?>"><i class="fa-solid <?= htmlspecialchars($c['icon']) ?>"></i><span><?= htmlspecialchars($c['name']) ?></span></a>
      <?php endforeach; ?>
    </div>

    <div class="catalog-results-summary">
      <p><?php if ($query !== ''): ?>「<?= shop_e($query) ?>」の検索結果：<?php endif; ?><strong><?= (int)$total ?> 点</strong><?php if ($cat !== 'all'): ?> ／ <?= shop_e($cats[$cat]['name']) ?><?php endif; ?></p>
      <?php if ($query !== ''): ?><a class="catalog-search-reset" href="<?= shop_e(list_url($cat, 1)) ?>">検索をクリア</a><?php endif; ?>
    </div>
    <?php if ($total === 0): ?>
      <div class="empty-state">該当する商品がありません。商品名や商品番号の一部で検索するか、カテゴリーを変更してください。</div>
    <?php else: ?>
    <div class="product-grid">
      <?php foreach ($items as $p) { include __DIR__ . '/partials/product_card.php'; } ?>
    </div>

    <?php if ($pages > 1): ?>
    <nav class="pager" aria-label="ページ送り">
      <?php if ($page > 1): ?><a class="pager-btn" href="<?= shop_e(list_url($cat, $page - 1, $query)) ?>" rel="prev"><i class="fa-solid fa-chevron-left"></i></a><?php else: ?><span class="pager-btn disabled"><i class="fa-solid fa-chevron-left"></i></span><?php endif; ?>
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === $page): ?><span class="pager-num active"><?= $i ?></span>
        <?php else: ?><a class="pager-num" href="<?= shop_e(list_url($cat, $i, $query)) ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $pages): ?><a class="pager-btn" href="<?= shop_e(list_url($cat, $page + 1, $query)) ?>" rel="next"><i class="fa-solid fa-chevron-right"></i></a><?php else: ?><span class="pager-btn disabled"><i class="fa-solid fa-chevron-right"></i></span><?php endif; ?>
    </nav>
    <p class="pager-info"><?= $total ?> 件中 <?= ($page - 1) * $perPage + 1 ?>〜<?= min($page * $perPage, $total) ?> 件を表示（<?= $page ?> / <?= $pages ?> ページ）</p>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
