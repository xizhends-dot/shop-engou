<?php
require_once __DIR__ . '/auth.php';
admin_require_login();

$data     = store_load();
$cats     = $data['categories'];
$products = $data['products'];

$productTotal   = count($products);
$categoryTotal  = count($cats);
$featuredIds    = featured_load();
$featuredMax    = defined('FEATURED_MAX') ? FEATURED_MAX : 12;
$featuredCount  = 0;
foreach ($featuredIds as $fid) {
    if (store_find_index($products, $fid) >= 0) {
        $featuredCount++;
    }
}
$bannerTotal    = count(banners_load());
$noImageCount   = 0;
foreach ($products as $p) {
    if (empty($p['images'])) {
        $noImageCount++;
    }
}

$imageFileCount = 0;
if (is_dir(UPLOAD_DIR)) {
    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(UPLOAD_DIR, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($rii as $file) {
        if ($file->isFile()) {
            $imageFileCount++;
        }
    }
}

$health       = store_health_report();
$healthOk     = !in_array(false, array_column($health, 'ok'), true);
$healthIssues = array_filter($health, function ($r) { return !$r['ok']; });

global $config;
$storage = store_driver();

require_once __DIR__ . '/_layout.php';
admin_head('控制台');
?>
<div class="adm-head">
  <h2>控制台</h2>
  <a href="../index.php" target="_blank" rel="noopener" class="adm-btn"><i class="fa-solid fa-up-right-from-square"></i> ショップを表示</a>
</div>

<section class="adm-dash-stats" aria-label="概要">
  <a href="products.php" class="adm-stat-card">
    <span class="adm-stat-icon"><i class="fa-solid fa-box"></i></span>
    <span class="adm-stat-num"><?= $productTotal ?></span>
    <span class="adm-stat-label">登録商品</span>
  </a>
  <a href="categories.php" class="adm-stat-card">
    <span class="adm-stat-icon"><i class="fa-solid fa-tags"></i></span>
    <span class="adm-stat-num"><?= $categoryTotal ?></span>
    <span class="adm-stat-label">カテゴリ</span>
  </a>
  <a href="featured.php" class="adm-stat-card">
    <span class="adm-stat-icon"><i class="fa-solid fa-star"></i></span>
    <span class="adm-stat-num"><?= $featuredCount ?><small>/<?= $featuredMax ?></small></span>
    <span class="adm-stat-label">おすすめ</span>
  </a>
  <a href="banners.php" class="adm-stat-card">
    <span class="adm-stat-icon"><i class="fa-solid fa-panorama"></i></span>
    <span class="adm-stat-num"><?= $bannerTotal ?></span>
    <span class="adm-stat-label">バナー</span>
  </a>
  <a href="media.php" class="adm-stat-card">
    <span class="adm-stat-icon"><i class="fa-solid fa-images"></i></span>
    <span class="adm-stat-num"><?= $imageFileCount ?></span>
    <span class="adm-stat-label">商品画像ファイル</span>
  </a>
  <?php if ($noImageCount > 0): ?>
  <a href="products.php" class="adm-stat-card adm-stat-card--warn">
    <span class="adm-stat-icon"><i class="fa-solid fa-image"></i></span>
    <span class="adm-stat-num"><?= $noImageCount ?></span>
    <span class="adm-stat-label">画像未設定の商品</span>
  </a>
  <?php endif; ?>
</section>

<div class="adm-dash-grid">
  <section class="adm-dash-panel">
    <h3 class="adm-dash-panel-title"><i class="fa-solid fa-bolt"></i> クイック操作</h3>
    <div class="adm-dash-actions">
      <a href="edit.php" class="adm-dash-action adm-dash-action--primary"><i class="fa-solid fa-plus"></i> 商品を追加</a>
      <a href="products.php" class="adm-dash-action"><i class="fa-solid fa-list"></i> 商品一覧</a>
      <a href="import.php" class="adm-dash-action"><i class="fa-solid fa-file-excel"></i> Excel取込</a>
      <a href="media.php" class="adm-dash-action"><i class="fa-solid fa-images"></i> 画像管理</a>
      <a href="categories.php" class="adm-dash-action"><i class="fa-solid fa-tags"></i> カテゴリ</a>
      <a href="featured.php" class="adm-dash-action"><i class="fa-solid fa-star"></i> おすすめ</a>
      <a href="banners.php" class="adm-dash-action"><i class="fa-solid fa-panorama"></i> バナー</a>
    </div>
  </section>

  <section class="adm-dash-panel">
    <h3 class="adm-dash-panel-title"><i class="fa-solid fa-gear"></i> サイト設定</h3>
    <dl class="adm-dash-dl">
      <dt>会社名</dt>
      <dd><?= htmlspecialchars($config['company_name_ja'] ?? '') ?></dd>
      <dt>保存方式</dt>
      <dd><code><?= htmlspecialchars($storage) ?></code></dd>
      <dt>ショップ URL</dt>
      <dd><a href="<?= htmlspecialchars($config['shop_site_url'] ?? '') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($config['shop_site_url'] ?? '') ?></a></dd>
      <dt>本社サイト</dt>
      <dd><a href="<?= htmlspecialchars($config['main_site_url'] ?? '') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($config['main_site_url'] ?? '') ?></a></dd>
    </dl>
  </section>

  <section class="adm-dash-panel adm-dash-panel--wide">
    <h3 class="adm-dash-panel-title">
      <i class="fa-solid fa-heart-pulse"></i> 保存環境
      <?php if ($healthOk): ?>
        <span class="adm-dash-badge adm-dash-badge--ok">正常</span>
      <?php else: ?>
        <span class="adm-dash-badge adm-dash-badge--err">要確認 <?= count($healthIssues) ?> 件</span>
      <?php endif; ?>
    </h3>
    <ul class="adm-dash-health">
      <?php foreach ($health as $row): ?>
      <li class="<?= $row['ok'] ? 'ok' : 'ng' ?>">
        <span class="adm-dash-health-label"><?= htmlspecialchars($row['label']) ?></span>
        <span class="adm-dash-health-detail"><?= htmlspecialchars($row['detail']) ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
    <p class="adm-dash-foot">
      <a href="check.php" class="adm-btn adm-btn-sm"><i class="fa-solid fa-stethoscope"></i> 詳細診断</a>
      <?php if ($storage === 'mysql'): ?>
      <a href="migrate.php" class="adm-btn adm-btn-sm"><i class="fa-solid fa-database"></i> DB移行</a>
      <?php endif; ?>
    </p>
  </section>
</div>

<?php if (!empty($products)): ?>
<section class="adm-dash-panel" style="margin-top:20px;">
  <h3 class="adm-dash-panel-title"><i class="fa-solid fa-clock"></i> 直近の商品（最大5件）</h3>
  <table class="adm-table">
    <thead>
      <tr>
        <th class="adm-col-id">商品番号</th>
        <th class="adm-col-name">商品名</th>
        <th class="adm-col-cat">カテゴリ</th>
        <th class="adm-col-price">価格</th>
        <th class="adm-col-actions">操作</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach (array_slice($products, 0, 5) as $p):
        $cat = $cats[$p['category']]['name'] ?? $p['category'];
      ?>
      <tr>
        <td class="adm-col-id"><code><?= htmlspecialchars($p['id']) ?></code></td>
        <td class="adm-col-name"><?= htmlspecialchars($p['name']) ?></td>
        <td class="adm-col-cat"><?= htmlspecialchars($cat) ?></td>
        <td class="adm-col-price">¥<?= number_format($p['price']) ?></td>
        <td class="adm-col-actions">
          <a class="adm-btn adm-btn-sm" href="edit.php?id=<?= urlencode($p['id']) ?>"><i class="fa-solid fa-pen"></i> 編集</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($productTotal > 5): ?>
  <p class="adm-dash-foot"><a href="products.php">すべての商品を見る（<?= $productTotal ?> 件）→</a></p>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php admin_foot(); ?>
