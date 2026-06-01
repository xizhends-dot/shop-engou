<?php
require_once __DIR__ . '/auth.php';
admin_require_login();

$data     = store_load();
$products = $data['products'];
$cats     = $data['categories'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        set_flash(__('flash.session_expired'), 'err');
        header('Location: featured.php'); exit;
    }
    $ids = $_POST['featured'] ?? [];
    $valid = [];
    foreach ((array)$ids as $id) {
        if (store_find_index($products, $id) >= 0) { $valid[] = $id; }
    }
    featured_save($valid);
    set_flash(__('flash.featured_saved'));
    header('Location: featured.php'); exit;
}

$featured = featured_load();
$max = defined('FEATURED_MAX') ? FEATURED_MAX : 12;
require_once __DIR__ . '/_layout.php';
$token = csrf_token();
admin_head(__('page.featured'));
?>
<div class="adm-head">
  <h2><?= htmlspecialchars(__('page.featured')) ?> <span class="adm-count"><?= htmlspecialchars(__('feat.title_hint', ['max' => $max])) ?></span></h2>
  <a href="products.php" class="adm-btn"><i class="fa-solid fa-arrow-left"></i> <?= htmlspecialchars(__('btn.back_products')) ?></a>
</div>

<p class="adm-note" style="margin-bottom:16px;">
  <?= htmlspecialchars(__('feat.note', ['max' => $max])) ?><span id="featCount" style="font-weight:700;color:var(--accent-strong);"></span>
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
    <button type="submit" class="adm-btn adm-btn-primary"><i class="fa-solid fa-floppy-disk"></i> <?= htmlspecialchars(__('btn.save')) ?></button>
  </div>
</form>

<script>
(function () {
  var MAX = <?= (int)$max ?>;
  var tpl = <?= json_encode(__('feat.selected', ['n' => 0, 'max' => $max]), JSON_UNESCAPED_UNICODE) ?>;
  var boxes = Array.prototype.slice.call(document.querySelectorAll('.feat-item input[type=checkbox]'));
  var counter = document.getElementById('featCount');
  function update() {
    var n = boxes.filter(function (b) { return b.checked; }).length;
    counter.textContent = tpl.replace('{n}', n).replace('{max}', MAX);
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
