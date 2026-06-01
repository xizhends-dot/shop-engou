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
$from    = $total ? ($page - 1) * $perPage + 1 : 0;
$to      = min($page * $perPage, $total);

admin_head(__('page.products'));
?>
<div class="adm-head">
  <h2><?= htmlspecialchars(__('page.products')) ?> <span class="adm-count"><?= $total ?> <?= htmlspecialchars(__('unit.count')) ?></span></h2>
  <div class="adm-head-actions">
    <a href="index.php" class="adm-btn"><i class="fa-solid fa-gauge-high"></i> <?= htmlspecialchars(__('nav.console')) ?></a>
    <a href="edit.php" class="adm-btn adm-btn-primary"><i class="fa-solid fa-plus"></i> <?= htmlspecialchars(__('btn.new_product')) ?></a>
  </div>
</div>

<?php if (empty($products)): ?>
  <div class="adm-empty"><?= htmlspecialchars(__('products.empty')) ?></div>
<?php else: ?>
<table class="adm-table">
  <thead>
    <tr>
      <th class="adm-col-thumb"><?= htmlspecialchars(__('col.image')) ?></th>
      <th class="adm-col-id"><?= htmlspecialchars(__('col.product_id')) ?></th>
      <th class="adm-col-name"><?= htmlspecialchars(__('col.name')) ?></th>
      <th class="adm-col-cat"><?= htmlspecialchars(__('col.category')) ?></th>
      <th class="adm-col-price"><?= htmlspecialchars(__('col.price')) ?></th>
      <th class="adm-col-actions"><?= htmlspecialchars(__('col.actions')) ?></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($items as $p):
      $thumb = !empty($p['images']) ? $p['images'][0] : '';
      $cat = $cats[$p['category']]['name'] ?? $p['category'];
      $delMsg = __('products.delete_confirm', ['name' => $p['name']]);
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
          <a class="adm-btn adm-btn-sm" href="edit.php?id=<?= urlencode($p['id']) ?>"><i class="fa-solid fa-pen"></i> <?= htmlspecialchars(__('btn.edit')) ?></a>
          <a class="adm-btn adm-btn-sm" href="../product.php?id=<?= urlencode($p['id']) ?>" target="_blank"><i class="fa-solid fa-eye"></i></a>
          <form method="post" action="delete.php" class="adm-inline" onsubmit="return confirm(<?= json_encode($delMsg, JSON_UNESCAPED_UNICODE) ?>);">
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
<nav class="adm-pager" aria-label="pager">
  <?php if ($page > 1): ?><a class="adm-pager-btn" href="products.php?page=<?= $page - 1 ?>"><i class="fa-solid fa-chevron-left"></i></a><?php endif; ?>
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <?php if ($i === $page): ?><span class="adm-pager-num active"><?= $i ?></span>
    <?php else: ?><a class="adm-pager-num" href="products.php?page=<?= $i ?>"><?= $i ?></a><?php endif; ?>
  <?php endfor; ?>
  <?php if ($page < $pages): ?><a class="adm-pager-btn" href="products.php?page=<?= $page + 1 ?>"><i class="fa-solid fa-chevron-right"></i></a><?php endif; ?>
</nav>
<p class="adm-pager-info"><?= htmlspecialchars(__('products.pager_range', [
    'total' => $total, 'from' => $from, 'to' => $to, 'page' => $page, 'pages' => $pages,
])) ?></p>
<?php endif; ?>
<?php endif; ?>
<?php admin_foot(); ?>
