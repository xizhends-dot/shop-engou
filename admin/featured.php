<?php
require_once __DIR__ . '/auth.php';
admin_require_login();

$data     = store_load();
$products = $data['products'];
$cats     = $data['categories'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        set_flash('セッションの有効期限が切れました。', 'err');
        header('Location: featured.php'); exit;
    }
    $ids = $_POST['featured'] ?? [];
    // 存在する商品IDのみ・最大件数は featured_save 側で制限
    $valid = [];
    foreach ((array)$ids as $id) {
        if (store_find_index($products, $id) >= 0) { $valid[] = $id; }
    }
    featured_save($valid);
    set_flash('おすすめ商品を保存しました。');
    header('Location: featured.php'); exit;
}

$featured = featured_load();
$max = defined('FEATURED_MAX') ? FEATURED_MAX : 12;
require_once __DIR__ . '/_layout.php';
$token = csrf_token();
admin_head('おすすめ商品');
?>
<div class="adm-head">
  <h2>おすすめ商品 <span class="adm-count">トップに表示（最大<?= $max ?>件）</span></h2>
  <a href="products.php" class="adm-btn"><i class="fa-solid fa-arrow-left"></i> 商品一覧へ</a>
</div>

<p class="adm-note" style="margin-bottom:16px;">
  チェックした商品がトップページの「おすすめ商品」に表示されます（最大 <?= $max ?> 件）。未選択の場合は先頭の商品が自動表示されます。<span id="featCount" style="font-weight:700;color:var(--accent-strong);"></span>
</p>

<form method="post" class="adm-form" style="max-width:none;">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
  <div class="feat-grid">
    <?php foreach ($products as $p):
      $thumb = !empty($p['images']) ? $p['images'][0] : '';
      $checked = in_array($p['id'], $featured, true);
    ?>
    <label class="feat-item <?= $checked ? 'checked' : '' ?>">
      <input type="checkbox" name="featured[]" value="<?= htmlspecialchars($p['id']) ?>" <?= $checked ? 'checked' : '' ?>>
      <span class="feat-thumb <?= $thumb === '' ? 'ph' : '' ?>" <?= $thumb === '' ? 'style="background:linear-gradient(140deg,' . htmlspecialchars($p['accent']) . ',' . htmlspecialchars($p['accent']) . '99)"' : '' ?>>
        <?php if ($thumb !== ''): ?><img src="../<?= htmlspecialchars($thumb) ?>" alt=""><?php else: ?><i class="fa-solid <?= htmlspecialchars($p['icon']) ?>"></i><?php endif; ?>
        <i class="fa-solid fa-circle-check feat-tick"></i>
      </span>
      <span class="feat-name"><?= htmlspecialchars($p['name']) ?></span>
      <span class="feat-cat"><?= htmlspecialchars($cats[$p['category']]['name'] ?? '') ?></span>
    </label>
    <?php endforeach; ?>
  </div>
  <div class="adm-formfoot">
    <button type="submit" class="adm-btn adm-btn-primary"><i class="fa-solid fa-floppy-disk"></i> 保存する</button>
  </div>
</form>

<script>
(function () {
  var MAX = <?= (int)$max ?>;
  var boxes = Array.prototype.slice.call(document.querySelectorAll('.feat-item input[type=checkbox]'));
  var counter = document.getElementById('featCount');
  function update() {
    var n = boxes.filter(function (b) { return b.checked; }).length;
    counter.textContent = '（選択中 ' + n + ' / ' + MAX + '）';
    boxes.forEach(function (b) {
      b.closest('.feat-item').classList.toggle('checked', b.checked);
      if (!b.checked) b.disabled = (n >= MAX);
    });
  }
  boxes.forEach(function (b) { b.addEventListener('change', update); });
  update();
})();
</script>
<?php admin_foot(); ?>
