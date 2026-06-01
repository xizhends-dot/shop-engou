<?php
require_once __DIR__ . '/auth.php';
admin_require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        set_flash(__('flash.session_expired'), 'err');
        header('Location: categories.php'); exit;
    }
    $act  = $_POST['action'] ?? '';
    $data = store_load();

    if ($act === 'save') {
        $order = $_POST['order'] ?? [];
        $names = $_POST['name'] ?? [];
        $icons = $_POST['icon'] ?? [];
        $cats  = $data['categories'];
        $newCats = categories_reorder($cats, is_array($order) ? $order : []);
        foreach ($newCats as $slug => $c) {
            if (isset($names[$slug])) {
                $newCats[$slug]['name'] = trim((string)$names[$slug]) ?: $slug;
                $newCats[$slug]['icon'] = trim((string)($icons[$slug] ?? '')) ?: 'fa-tag';
            }
        }
        $data['categories'] = $newCats;
        if (store_save($data)) {
            set_flash(__('flash.category_saved'));
        } else {
            set_flash(store_save_error_message(), 'err');
        }
        header('Location: categories.php'); exit;
    }

    if ($act === 'add') {
        $slug = trim((string)($_POST['slug'] ?? ''));
        $name = trim((string)($_POST['new_name'] ?? ''));
        $icon = trim((string)($_POST['new_icon'] ?? '')) ?: 'fa-tag';
        if (!preg_match('/^[a-z0-9_\-]{1,32}$/i', $slug)) {
            set_flash(__('flash.category_key_invalid'), 'err');
        } elseif (isset($data['categories'][$slug])) {
            set_flash(__('flash.category_key_exists'), 'err');
        } elseif ($name === '') {
            set_flash(__('flash.category_name_required'), 'err');
        } else {
            $data['categories'][$slug] = ['name' => $name, 'icon' => $icon];
            categories_apply_sort_indices($data['categories']);
            if (store_save($data)) {
                set_flash(__('flash.category_added', ['name' => $name]));
            } else {
                set_flash(store_save_error_message(), 'err');
            }
        }
        header('Location: categories.php'); exit;
    }

    if ($act === 'delete') {
        $slug = (string)($_POST['slug'] ?? '');
        $usage = category_usage($data);
        if (!isset($data['categories'][$slug])) {
            set_flash(__('flash.category_not_found'), 'err');
        } elseif (($usage[$slug] ?? 0) > 0) {
            set_flash(__('flash.category_has_products'), 'err');
        } else {
            unset($data['categories'][$slug]);
            categories_apply_sort_indices($data['categories']);
            if (store_save($data)) {
                set_flash(__('flash.category_deleted'));
            } else {
                set_flash(store_save_error_message(), 'err');
            }
        }
        header('Location: categories.php'); exit;
    }
}

$data  = store_load();
$cats  = $data['categories'];
$usage = category_usage($data);
require_once __DIR__ . '/_layout.php';
$token = csrf_token();
admin_head(__('page.categories'));
$delTitle = htmlspecialchars(__('cat.cannot_delete'), ENT_QUOTES);
?>
<div class="adm-head">
  <h2><?= htmlspecialchars(__('page.categories')) ?> <span class="adm-count"><?= count($cats) ?> <?= htmlspecialchars(__('unit.count')) ?></span></h2>
  <a href="products.php" class="adm-btn"><i class="fa-solid fa-arrow-left"></i> <?= htmlspecialchars(__('btn.back_products')) ?></a>
</div>

<form method="post" class="adm-form" style="max-width:860px;">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
  <input type="hidden" name="action" value="save">
  <h3 style="font-size:15px;margin-bottom:6px;color:var(--heading);"><?= htmlspecialchars(__('cat.list')) ?></h3>
  <p class="adm-note" style="margin-bottom:14px;"><?= htmlspecialchars(__('cat.order_note')) ?></p>
  <?php if (empty($cats)): ?>
    <p class="adm-note"><?= htmlspecialchars(__('cat.empty')) ?></p>
  <?php else: ?>
  <table class="adm-table" style="box-shadow:none;">
    <thead>
      <tr>
        <th class="cat-col-order"><?= htmlspecialchars(__('cat.col_order')) ?></th>
        <th><?= htmlspecialchars(__('col.key')) ?></th>
        <th><?= htmlspecialchars(__('cat.name')) ?></th>
        <th><?= htmlspecialchars(__('col.icon')) ?></th>
        <th><?= htmlspecialchars(__('col.product_count')) ?></th>
        <th><?= htmlspecialchars(__('col.actions')) ?></th>
      </tr>
    </thead>
    <tbody id="catList">
      <?php foreach ($cats as $slug => $c): $cnt = $usage[$slug] ?? 0; ?>
      <tr class="cat-row" data-slug="<?= htmlspecialchars($slug, ENT_QUOTES) ?>">
        <td class="cat-col-order">
          <input type="hidden" name="order[]" value="<?= htmlspecialchars($slug) ?>">
          <span class="cat-drag-handle" draggable="true" title="<?= htmlspecialchars(__('cat.drag_hint')) ?>" aria-label="<?= htmlspecialchars(__('cat.drag_hint')) ?>">
            <i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>
          </span>
        </td>
        <td><code><?= htmlspecialchars($slug) ?></code></td>
        <td><input type="text" name="name[<?= htmlspecialchars($slug) ?>]" value="<?= htmlspecialchars($c['name']) ?>" style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:7px;font-family:inherit;"></td>
        <td><input type="text" name="icon[<?= htmlspecialchars($slug) ?>]" value="<?= htmlspecialchars($c['icon']) ?>" placeholder="fa-tag" style="width:130px;padding:8px 10px;border:1px solid var(--border);border-radius:7px;font-family:inherit;"></td>
        <td><?= $cnt ?> <?= htmlspecialchars(__('unit.count')) ?></td>
        <td>
          <button type="button" class="adm-btn adm-btn-sm adm-btn-danger js-cat-del" data-slug="<?= htmlspecialchars($slug, ENT_QUOTES) ?>" data-name="<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>" <?= $cnt > 0 ? 'disabled title="' . $delTitle . '"' : '' ?>>
            <i class="fa-solid fa-trash"></i>
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="adm-formfoot">
    <button type="submit" class="adm-btn adm-btn-primary"><i class="fa-solid fa-floppy-disk"></i> <?= htmlspecialchars(__('btn.save_changes')) ?></button>
  </div>
  <?php endif; ?>
</form>

<form method="post" class="adm-form" style="max-width:760px;margin-top:22px;">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
  <input type="hidden" name="action" value="add">
  <h3 style="font-size:15px;margin-bottom:6px;color:var(--heading);"><?= htmlspecialchars(__('cat.add_title')) ?></h3>
  <p class="adm-note"><?= htmlspecialchars(__('cat.add_note')) ?></p>
  <div class="adm-grid">
    <div class="adm-field">
      <label><?= htmlspecialchars(__('cat.key')) ?></label>
      <input type="text" name="slug" placeholder="outdoor" pattern="[A-Za-z0-9_\-]+">
    </div>
    <div class="adm-field">
      <label><?= htmlspecialchars(__('cat.name')) ?></label>
      <input type="text" name="new_name" placeholder="">
    </div>
  </div>
  <div class="adm-field">
    <label><?= htmlspecialchars(__('cat.icon_fa')) ?></label>
    <input type="text" name="new_icon" placeholder="fa-tag">
    <small><a href="https://fontawesome.com/search?o=r&m=free" target="_blank"><?= htmlspecialchars(__('cat.icon_list')) ?></a></small>
  </div>
  <div class="adm-formfoot">
    <button type="submit" class="adm-btn adm-btn-primary"><i class="fa-solid fa-plus"></i> <?= htmlspecialchars(__('btn.add')) ?></button>
  </div>
</form>

<form method="post" id="catDelForm" style="display:none;">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="slug" id="catDelSlug">
</form>
<script>
(function () {
  var list = document.getElementById('catList');
  var dragRow = null;

  if (list) {
    list.querySelectorAll('.cat-drag-handle').forEach(function (handle) {
      handle.addEventListener('dragstart', function (e) {
        dragRow = handle.closest('tr');
        if (!dragRow) return;
        dragRow.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', dragRow.dataset.slug || '');
        if (e.dataTransfer.setDragImage) {
          var ghost = dragRow.cloneNode(true);
          ghost.style.opacity = '0.85';
          ghost.style.background = '#f4f6f9';
          document.body.appendChild(ghost);
          e.dataTransfer.setDragImage(ghost, 20, 20);
          setTimeout(function () { ghost.remove(); }, 0);
        }
      });
      handle.addEventListener('dragend', function () {
        if (dragRow) dragRow.classList.remove('is-dragging');
        dragRow = null;
        list.querySelectorAll('.cat-row').forEach(function (r) { r.classList.remove('cat-drag-over'); });
      });
    });

    list.querySelectorAll('.cat-row').forEach(function (row) {
      row.addEventListener('dragover', function (e) {
        if (!dragRow || dragRow === row) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        list.querySelectorAll('.cat-row').forEach(function (r) { r.classList.remove('cat-drag-over'); });
        row.classList.add('cat-drag-over');
      });
      row.addEventListener('dragleave', function (e) {
        if (!row.contains(e.relatedTarget)) row.classList.remove('cat-drag-over');
      });
      row.addEventListener('drop', function (e) {
        e.preventDefault();
        row.classList.remove('cat-drag-over');
        if (!dragRow || dragRow === row) return;
        var rect = row.getBoundingClientRect();
        var before = e.clientY < rect.top + rect.height / 2;
        list.insertBefore(dragRow, before ? row : row.nextElementSibling);
      });
    });

    list.addEventListener('dragover', function (e) {
      if (dragRow) e.preventDefault();
    });
  }

  document.querySelectorAll('.js-cat-del').forEach(function (b) {
    b.addEventListener('click', function () {
      var msg = <?= json_encode(__('cat.delete_confirm', ['name' => '']), JSON_UNESCAPED_UNICODE) ?>.replace('{name}', b.dataset.name);
      if (!confirm(msg)) return;
      document.getElementById('catDelSlug').value = b.dataset.slug;
      document.getElementById('catDelForm').submit();
    });
  });
})();
</script>
<?php admin_foot(); ?>
