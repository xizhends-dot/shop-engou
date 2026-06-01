<?php
require_once __DIR__ . '/auth.php';
admin_require_login();
require_once __DIR__ . '/_layout.php';

$data     = store_load();
$cats     = $data['categories'];
$products = $data['products'];

$perPage = 20;
$total   = count($products);
$pages   = max(1, (int)ceil($total / $perPage));
$page    = max(1, min($pages, (int)($_GET['page'] ?? 1)));
$items   = array_slice($products, ($page - 1) * $perPage, $perPage);

admin_head('商品一覧');
?>
<div class="adm-head">
  <h2>商品一覧 <span class="adm-count"><?= $total ?> 件</span></h2>
  <div class="adm-head-actions">
    <a href="index.php" class="adm-btn"><i class="fa-solid fa-gauge-high"></i> 控制台</a>
    <a href="edit.php" class="adm-btn adm-btn-primary"><i class="fa-solid fa-plus"></i> 新規追加</a>
  </div>
</div>

<?php if (empty($products)): ?>
  <div class="adm-empty">まだ商品がありません。「新規追加」から登録してください。</div>
<?php else: ?>
<table class="adm-table">
  <thead>
    <tr>
      <th class="adm-col-thumb">画像</th>
      <th class="adm-col-id">商品番号</th>
      <th class="adm-col-name">商品名</th>
      <th class="adm-col-cat">カテゴリ</th>
      <th class="adm-col-price">価格</th>
      <th class="adm-col-actions">操作</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($items as $p):
      $thumb = !empty($p['images']) ? $p['images'][0] : '';
      $cat = $cats[$p['category']]['name'] ?? $p['category'];
    ?>
    <tr>
      <td class="adm-col-thumb">
        <?php if ($thumb !== ''): ?>
          <img class="adm-thumb" src="../<?= htmlspecialchars($thumb) ?>" alt="">
        <?php else: ?>
          <span class="adm-thumb adm-thumb-ph" style="background: linear-gradient(140deg, <?= htmlspecialchars($p['accent']) ?>, <?= htmlspecialchars($p['accent']) ?>99);"><i class="fa-solid <?= htmlspecialchars($p['icon']) ?>"></i></span>
        <?php endif; ?>
      </td>
      <td class="adm-col-id"><code><?= htmlspecialchars($p['id']) ?></code></td>
      <td class="adm-col-name"><span class="adm-name-text" title="<?= htmlspecialchars($p['name']) ?>"><?= htmlspecialchars($p['name']) ?></span></td>
      <td class="adm-col-cat"><span class="adm-cell-text"><?= htmlspecialchars($cat) ?></span></td>
      <td class="adm-col-price">¥<?= number_format($p['price']) ?></td>
      <td class="adm-col-actions adm-actions">
        <div class="adm-actions-inner">
          <a class="adm-btn adm-btn-sm" href="edit.php?id=<?= urlencode($p['id']) ?>"><i class="fa-solid fa-pen"></i> 編集</a>
          <a class="adm-btn adm-btn-sm" href="../product.php?id=<?= urlencode($p['id']) ?>" target="_blank"><i class="fa-solid fa-eye"></i></a>
          <form method="post" action="delete.php" class="adm-inline" onsubmit="return confirm('「<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>」を削除しますか？');">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= htmlspecialchars($p['id']) ?>">
            <button type="submit" class="adm-btn adm-btn-sm adm-btn-danger"><i class="fa-solid fa-trash"></i></button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php if ($pages > 1): ?>
<nav class="adm-pager" aria-label="ページ送り">
  <?php if ($page > 1): ?><a class="adm-pager-btn" href="products.php?page=<?= $page - 1 ?>"><i class="fa-solid fa-chevron-left"></i></a><?php endif; ?>
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <?php if ($i === $page): ?><span class="adm-pager-num active"><?= $i ?></span>
    <?php else: ?><a class="adm-pager-num" href="products.php?page=<?= $i ?>"><?= $i ?></a><?php endif; ?>
  <?php endfor; ?>
  <?php if ($page < $pages): ?><a class="adm-pager-btn" href="products.php?page=<?= $page + 1 ?>"><i class="fa-solid fa-chevron-right"></i></a><?php endif; ?>
</nav>
<p class="adm-pager-info"><?= $total ?> 件中 <?= ($page - 1) * $perPage + 1 ?>〜<?= min($page * $perPage, $total) ?> 件（<?= $page ?> / <?= $pages ?> ページ）</p>
<?php endif; ?>
<?php endif; ?>
<?php admin_foot(); ?>
