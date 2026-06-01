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

$token = csrf_token();
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

<div class="products-batch-bar">
  <div class="products-batch-inner">
    <span class="products-batch-hint"><?= htmlspecialchars(__('products.batch_hint')) ?></span>
    <div class="products-batch-actions">
      <button type="button" class="adm-btn adm-btn-sm" id="btnProdSelectPage"><i class="fa-solid fa-check-double" aria-hidden="true"></i><span><?= htmlspecialchars(__('products.select_page')) ?></span></button>
      <button type="button" class="adm-btn adm-btn-sm" id="btnProdSelectNone"><i class="fa-solid fa-xmark" aria-hidden="true"></i><span><?= htmlspecialchars(__('products.select_none')) ?></span></button>
      <button type="button" class="adm-btn adm-btn-sm adm-btn-danger" id="btnProdBatchDelete" disabled><i class="fa-solid fa-trash" aria-hidden="true"></i><span><?= htmlspecialchars(__('products.batch_delete')) ?> (<span id="prodSelCount">0</span>)</span></button>
    </div>
  </div>
</div>

<form method="post" id="prodBatchForm" action="delete.php" style="display:none;">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
  <input type="hidden" name="page" value="<?= (int)$page ?>">
  <div id="prodBatchIds"></div>
</form>

<table class="adm-table" id="productsTable">
  <thead>
    <tr>
      <th class="adm-col-check" scope="col">
        <label class="adm-check-label" title="<?= htmlspecialchars(__('products.select_page')) ?>">
          <input type="checkbox" id="prodCheckAll" class="adm-check" aria-label="<?= htmlspecialchars(__('products.select_page')) ?>">
        </label>
      </th>
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
    <tr data-product-id="<?= htmlspecialchars($p['id'], ENT_QUOTES) ?>">
      <td class="adm-col-check">
        <label class="adm-check-label">
          <input type="checkbox" class="adm-check prod-cb" value="<?= htmlspecialchars($p['id'], ENT_QUOTES) ?>" data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>" aria-label="<?= htmlspecialchars($p['name']) ?>">
        </label>
      </td>
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
          <a class="adm-btn adm-btn-sm" href="../product.php?id=<?= urlencode($p['id']) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-eye"></i></a>
          <form method="post" action="delete.php" class="adm-inline" onsubmit="return confirm(<?= json_encode($delMsg, JSON_UNESCAPED_UNICODE) ?>);">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
            <input type="hidden" name="id" value="<?= htmlspecialchars($p['id']) ?>">
            <input type="hidden" name="page" value="<?= (int)$page ?>">
            <button type="submit" class="adm-btn adm-btn-sm adm-btn-danger" title="<?= htmlspecialchars(__('btn.delete')) ?>"><i class="fa-solid fa-trash"></i></button>
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

<script>
(function () {
  var table = document.getElementById('productsTable');
  if (!table) return;

  var checkAll = document.getElementById('prodCheckAll');
  var btnPage = document.getElementById('btnProdSelectPage');
  var btnNone = document.getElementById('btnProdSelectNone');
  var btnDel = document.getElementById('btnProdBatchDelete');
  var selCount = document.getElementById('prodSelCount');
  var batchForm = document.getElementById('prodBatchForm');
  var batchIds = document.getElementById('prodBatchIds');
  var confirmTpl = <?= json_encode(__('products.batch_delete_confirm'), JSON_UNESCAPED_UNICODE) ?>;

  function boxes() {
    return Array.prototype.slice.call(table.querySelectorAll('.prod-cb'));
  }

  function selected() {
    return boxes().filter(function (cb) { return cb.checked; });
  }

  function refresh() {
    var n = selected().length;
    var all = boxes();
    selCount.textContent = String(n);
    btnDel.disabled = n === 0;
    if (checkAll) {
      checkAll.checked = all.length > 0 && n === all.length;
      checkAll.indeterminate = n > 0 && n < all.length;
    }
  }

  boxes().forEach(function (cb) {
    cb.addEventListener('change', refresh);
  });

  if (checkAll) {
    checkAll.addEventListener('change', function () {
      var on = checkAll.checked;
      boxes().forEach(function (cb) { cb.checked = on; });
      refresh();
    });
  }

  if (btnPage) {
    btnPage.addEventListener('click', function () {
      boxes().forEach(function (cb) { cb.checked = true; });
      refresh();
    });
  }

  if (btnNone) {
    btnNone.addEventListener('click', function () {
      boxes().forEach(function (cb) { cb.checked = false; });
      if (checkAll) { checkAll.checked = false; checkAll.indeterminate = false; }
      refresh();
    });
  }

  if (btnDel) {
    btnDel.addEventListener('click', function () {
      var picked = selected();
      if (!picked.length) return;
      var msg = confirmTpl.replace('{n}', String(picked.length));
      if (!confirm(msg)) return;
      batchIds.innerHTML = '';
      picked.forEach(function (cb) {
        var inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'ids[]';
        inp.value = cb.value;
        batchIds.appendChild(inp);
      });
      batchForm.submit();
    });
  }

  refresh();
})();
</script>
<?php endif; ?>
<?php admin_foot(); ?>
