<?php
require_once __DIR__ . '/auth.php';
admin_require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        set_flash('セッションの有効期限が切れました。', 'err');
        header('Location: categories.php'); exit;
    }
    $act  = $_POST['action'] ?? '';
    $data = store_load();

    if ($act === 'save') {
        // 既存カテゴリの名称・アイコンを更新
        $names = $_POST['name'] ?? [];
        $icons = $_POST['icon'] ?? [];
        foreach ($data['categories'] as $slug => $c) {
            if (isset($names[$slug])) {
                $data['categories'][$slug]['name'] = trim((string)$names[$slug]) ?: $slug;
                $data['categories'][$slug]['icon'] = trim((string)($icons[$slug] ?? '')) ?: 'fa-tag';
            }
        }
        if (store_save($data)) {
            set_flash('カテゴリを保存しました。');
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
            set_flash('カテゴリキーは半角英数・ハイフン（1〜32文字）で入力してください。', 'err');
        } elseif (isset($data['categories'][$slug])) {
            set_flash('そのカテゴリキーは既に存在します。', 'err');
        } elseif ($name === '') {
            set_flash('カテゴリ名を入力してください。', 'err');
        } else {
            $data['categories'][$slug] = ['name' => $name, 'icon' => $icon];
            if (store_save($data)) {
                set_flash('カテゴリ「' . $name . '」を追加しました。');
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
            set_flash('対象のカテゴリが見つかりません。', 'err');
        } elseif (($usage[$slug] ?? 0) > 0) {
            set_flash('このカテゴリには商品があるため削除できません（先に商品のカテゴリを変更してください）。', 'err');
        } else {
            unset($data['categories'][$slug]);
            if (store_save($data)) {
                set_flash('カテゴリを削除しました。');
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
admin_head('カテゴリ管理');
?>
<div class="adm-head">
  <h2>カテゴリ管理 <span class="adm-count"><?= count($cats) ?> 件</span></h2>
  <a href="index.php" class="adm-btn"><i class="fa-solid fa-arrow-left"></i> 一覧へ</a>
</div>

<!-- 既存カテゴリ -->
<form method="post" class="adm-form" style="max-width:760px;">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
  <input type="hidden" name="action" value="save">
  <h3 style="font-size:15px;margin-bottom:14px;color:var(--heading);">カテゴリ一覧</h3>
  <?php if (empty($cats)): ?>
    <p class="adm-note">カテゴリがありません。下のフォームから追加してください。</p>
  <?php else: ?>
  <table class="adm-table" style="box-shadow:none;">
    <thead><tr><th>キー</th><th>カテゴリ名</th><th>アイコン</th><th>商品数</th><th>操作</th></tr></thead>
    <tbody>
      <?php foreach ($cats as $slug => $c): $cnt = $usage[$slug] ?? 0; ?>
      <tr>
        <td><code><?= htmlspecialchars($slug) ?></code></td>
        <td><input type="text" name="name[<?= htmlspecialchars($slug) ?>]" value="<?= htmlspecialchars($c['name']) ?>" style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:7px;font-family:inherit;"></td>
        <td><input type="text" name="icon[<?= htmlspecialchars($slug) ?>]" value="<?= htmlspecialchars($c['icon']) ?>" placeholder="fa-tag" style="width:130px;padding:8px 10px;border:1px solid var(--border);border-radius:7px;font-family:inherit;"></td>
        <td><?= $cnt ?> 件</td>
        <td>
          <button type="button" class="adm-btn adm-btn-sm adm-btn-danger js-cat-del" data-slug="<?= htmlspecialchars($slug, ENT_QUOTES) ?>" data-name="<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>" <?= $cnt > 0 ? 'disabled title="商品があるため削除できません"' : '' ?>>
            <i class="fa-solid fa-trash"></i>
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="adm-formfoot">
    <button type="submit" class="adm-btn adm-btn-primary"><i class="fa-solid fa-floppy-disk"></i> 変更を保存</button>
  </div>
  <?php endif; ?>
</form>

<!-- 新規カテゴリ追加 -->
<form method="post" class="adm-form" style="max-width:760px;margin-top:22px;">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
  <input type="hidden" name="action" value="add">
  <h3 style="font-size:15px;margin-bottom:6px;color:var(--heading);">カテゴリを追加</h3>
  <p class="adm-note">「キー」は商品データで使う識別子（半角英数・ハイフン、例: <code>beauty</code>）。後から変更できません。</p>
  <div class="adm-grid">
    <div class="adm-field">
      <label>キー（半角英数）</label>
      <input type="text" name="slug" placeholder="例: outdoor" pattern="[A-Za-z0-9_\-]+">
    </div>
    <div class="adm-field">
      <label>カテゴリ名</label>
      <input type="text" name="new_name" placeholder="例: アウトドア">
    </div>
  </div>
  <div class="adm-field">
    <label>アイコン（Font Awesome）</label>
    <input type="text" name="new_icon" placeholder="fa-tag">
    <small><a href="https://fontawesome.com/search?o=r&m=free" target="_blank">アイコン一覧</a>（例: fa-utensils / fa-house-chimney / fa-wand-magic-sparkles）</small>
  </div>
  <div class="adm-formfoot">
    <button type="submit" class="adm-btn adm-btn-primary"><i class="fa-solid fa-plus"></i> 追加する</button>
  </div>
</form>

<!-- 削除用フォーム（JSで送信） -->
<form method="post" id="catDelForm" style="display:none;">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="slug" id="catDelSlug">
</form>
<script>
document.querySelectorAll('.js-cat-del').forEach(function (b) {
  b.addEventListener('click', function () {
    if (!confirm('カテゴリ「' + b.dataset.name + '」を削除しますか？')) return;
    document.getElementById('catDelSlug').value = b.dataset.slug;
    document.getElementById('catDelForm').submit();
  });
});
</script>
<?php admin_foot(); ?>
