<?php
require_once __DIR__ . '/auth.php';
admin_require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if (!csrf_check($_POST['csrf'] ?? '')) {
        set_flash(__('flash.session_expired'), 'err');
        header('Location: banners.php'); exit;
    }

    if ($act === 'settings') {
        $keys = ['eyebrow', 'title', 'subtitle', 'btn1_text', 'btn1_link', 'btn2_text', 'btn2_link'];
        $s = [];
        foreach ($keys as $kk) { $s[$kk] = trim((string)($_POST[$kk] ?? '')); }
        if (banner_settings_save($s)) {
            set_flash(__('flash.banner_settings_saved'));
        } else {
            set_flash(__('flash.banner_save_fail'), 'err');
        }
        header('Location: banners.php'); exit;
    }

    if ($act === 'upload') {
        $banners = banners_load();
        $max     = defined('BANNER_MAX') ? BANNER_MAX : 5;
        $ok = 0; $ng = 0; $skipMax = 0;
        if (!empty($_FILES['files']['name'][0])) {
            $n = count($_FILES['files']['name']);
            for ($i = 0; $i < $n; $i++) {
                if (count($banners) >= $max) {
                    $skipMax++;
                    continue;
                }
                if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                    $one = [
                        'name'     => $_FILES['files']['name'][$i],
                        'tmp_name' => $_FILES['files']['tmp_name'][$i],
                        'error'    => $_FILES['files']['error'][$i],
                        'size'     => $_FILES['files']['size'][$i],
                    ];
                    $rel = store_handle_upload($one, BANNER_DIR, BANNER_REL);
                    if ($rel) {
                        $banners[] = ['image' => $rel, 'link' => '', 'title' => '', 'subtitle' => ''];
                        $ok++;
                    } else {
                        $ng++;
                    }
                }
            }
        }
        banners_save($banners);
        $msg = __('flash.banner_added', ['ok' => $ok, 'fail' => $ng ? __('flash.banner_added_fail', ['ng' => $ng]) : '']);
        if ($skipMax > 0) {
            $msg .= ' ' . __('flash.banner_max_skip', ['n' => $skipMax, 'max' => $max]);
        }
        set_flash($msg, ($ng || $skipMax) ? 'err' : 'ok');
        header('Location: banners.php'); exit;
    }

    if ($act === 'save') {
        // タイトル・サブ・リンク編集 + 並び順（order[] の値で並べ替え）
        $banners = banners_load();
        $byImg = [];
        foreach ($banners as $b) { $byImg[$b['image']] = $b; }
        $links  = $_POST['link'] ?? [];
        $titles = $_POST['title'] ?? [];
        $subs   = $_POST['subtitle'] ?? [];
        $order  = $_POST['order'] ?? array_keys($byImg);
        $new = [];
        foreach ($order as $img) {
            if (!store_validate_banner_image_path($img)) {
                continue;
            }
            if (isset($byImg[$img])) {
                $new[] = [
                    'image'    => $img,
                    'link'     => trim((string)($links[$img] ?? '')),
                    'title'    => trim((string)($titles[$img] ?? '')),
                    'subtitle' => trim((string)($subs[$img] ?? '')),
                ];
            }
        }
        if (banners_save($new)) {
            set_flash(__('flash.saved'));
        } else {
            set_flash(__('flash.banner_save_fail'), 'err');
        }
        header('Location: banners.php'); exit;
    }

    if ($act === 'replace') {
        $path = (string)($_POST['path'] ?? '');
        if (!store_validate_banner_image_path($path)) {
            set_flash(__('flash.banner_bad_path'), 'err');
            header('Location: banners.php'); exit;
        }
        if (empty($_FILES['file']['tmp_name']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            set_flash(__('flash.banner_pick_file'), 'err');
            header('Location: banners.php'); exit;
        }
        $banners = banners_load();
        $idx = -1;
        foreach ($banners as $i => $b) {
            if ($b['image'] === $path) {
                $idx = $i;
                break;
            }
        }
        if ($idx < 0) {
            set_flash(__('flash.banner_not_found'), 'err');
            header('Location: banners.php'); exit;
        }
        $uploadErr = '';
        $rel = store_handle_upload($_FILES['file'], BANNER_DIR, BANNER_REL, $uploadErr);
        if ($rel === null || $rel === '') {
            set_flash($uploadErr !== '' ? $uploadErr : __('flash.banner_upload_fail'), 'err');
            header('Location: banners.php'); exit;
        }
        $old = $banners[$idx];
        $banners[$idx] = [
            'image'    => $rel,
            'link'     => $old['link'] ?? '',
            'title'    => $old['title'] ?? '',
            'subtitle' => $old['subtitle'] ?? '',
        ];
        banners_save($banners);
        if ($rel !== $path) {
            store_delete_banner_image($path);
        }
        set_flash(__('flash.banner_replaced'));
        header('Location: banners.php'); exit;
    }

    if ($act === 'delete') {
        $path = (string)($_POST['path'] ?? '');
        if (!store_validate_banner_image_path($path)) {
            set_flash(__('flash.banner_bad_path'), 'err');
            header('Location: banners.php'); exit;
        }
        $banners = array_values(array_filter(banners_load(), function ($b) use ($path) { return $b['image'] !== $path; }));
        banners_save($banners);
        store_delete_banner_image($path);
        set_flash(__('flash.banner_deleted'));
        header('Location: banners.php'); exit;
    }
}

$banners  = banners_load();
$settings = banner_settings_load();
$bannerMax = defined('BANNER_MAX') ? BANNER_MAX : 5;
$bannerVisible = count(array_filter($banners, 'banner_has_content'));
$bannerSlotsLeft = max(0, $bannerMax - count($banners));
require_once __DIR__ . '/_layout.php';
$token = csrf_token();
admin_head(__('page.banners'));
?>
<div class="adm-head">
  <h2><?= htmlspecialchars(__('page.banners')) ?> <span class="adm-count"><?= count($banners) ?> / <?= $bannerMax ?> <?= htmlspecialchars(__('banner.count')) ?> · <?= htmlspecialchars(__('banner.visible_count', ['n' => $bannerVisible])) ?></span></h2>
  <a href="index.php" class="adm-btn"><i class="fa-solid fa-arrow-left"></i> <?= htmlspecialchars(__('btn.back_console')) ?></a>
</div>

<div class="adm-form" style="max-width:760px;margin-bottom:22px;">
  <h3 style="font-size:15px;margin-bottom:6px;color:var(--heading);"><?= htmlspecialchars(__('banner.settings_title')) ?></h3>
  <p class="adm-note"><?= __('banner.settings_note') ?></p>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
    <input type="hidden" name="action" value="settings">
    <div class="adm-field">
      <label><?= htmlspecialchars(__('banner.eyebrow')) ?></label>
      <input type="text" name="eyebrow" value="<?= htmlspecialchars($settings['eyebrow']) ?>">
    </div>
    <div class="adm-field">
      <label><?= htmlspecialchars(__('banner.default_title')) ?></label>
      <input type="text" name="title" value="<?= htmlspecialchars($settings['title']) ?>">
    </div>
    <div class="adm-field">
      <label><?= htmlspecialchars(__('banner.default_subtitle')) ?></label>
      <input type="text" name="subtitle" value="<?= htmlspecialchars($settings['subtitle']) ?>">
    </div>
    <div class="adm-grid">
      <div class="adm-field">
        <label><?= htmlspecialchars(__('banner.btn1_text')) ?></label>
        <input type="text" name="btn1_text" value="<?= htmlspecialchars($settings['btn1_text']) ?>">
      </div>
      <div class="adm-field">
        <label><?= htmlspecialchars(__('banner.btn1_link')) ?></label>
        <input type="text" name="btn1_link" value="<?= htmlspecialchars($settings['btn1_link']) ?>">
      </div>
    </div>
    <div class="adm-grid">
      <div class="adm-field">
        <label><?= htmlspecialchars(__('banner.btn2_text')) ?></label>
        <input type="text" name="btn2_text" value="<?= htmlspecialchars($settings['btn2_text']) ?>">
      </div>
      <div class="adm-field">
        <label><?= htmlspecialchars(__('banner.btn2_link')) ?></label>
        <input type="text" name="btn2_link" value="<?= htmlspecialchars($settings['btn2_link']) ?>">
      </div>
    </div>
    <div class="adm-formfoot">
      <button type="submit" class="adm-btn adm-btn-primary"><i class="fa-solid fa-floppy-disk"></i> <?= htmlspecialchars(__('btn.save_common')) ?></button>
    </div>
  </form>
</div>

<div class="adm-form" style="max-width:760px;">
  <h3 style="font-size:15px;margin-bottom:12px;color:var(--heading);"><?= htmlspecialchars(__('banner.upload_title')) ?></h3>
  <p class="adm-note"><?= htmlspecialchars(__('banner.upload_note', ['max' => $bannerMax])) ?></p>
  <?php if ($bannerSlotsLeft <= 0): ?>
  <p class="adm-note" style="color:var(--danger);font-weight:600;"><?= htmlspecialchars(__('banner.max_full', ['max' => $bannerMax])) ?></p>
  <?php else: ?>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
    <input type="hidden" name="action" value="upload">
    <div class="adm-field" style="margin-bottom:12px;">
      <input type="file" name="files[]" accept="image/*" multiple required>
      <small><?= htmlspecialchars(__('banner.upload_hint', ['left' => $bannerSlotsLeft, 'max' => $bannerMax])) ?></small>
    </div>
    <button type="submit" class="adm-btn adm-btn-primary"><i class="fa-solid fa-upload"></i> <?= htmlspecialchars(__('btn.upload')) ?></button>
  </form>
  <?php endif; ?>
</div>

<?php if (empty($banners)): ?>
  <div class="adm-empty" style="margin-top:20px;"><?= htmlspecialchars(__('banner.empty')) ?></div>
<?php else: ?>
<p class="adm-note" style="margin-top:20px;"><?= htmlspecialchars(__('banner.list_note')) ?></p>
<form method="post" style="margin-top:12px;">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
  <input type="hidden" name="action" value="save">
  <div class="banner-admin-list" id="bannerList">
    <?php foreach ($banners as $b):
      $isDraft = !banner_has_content($b);
    ?>
    <div class="banner-admin-item<?= $isDraft ? ' banner-admin-item--draft' : '' ?>" data-img="<?= htmlspecialchars($b['image']) ?>">
      <input type="hidden" name="order[]" value="<?= htmlspecialchars($b['image']) ?>">
      <img src="../<?= htmlspecialchars($b['image']) ?>" alt="">
      <?php if ($isDraft): ?>
      <span class="banner-draft-badge"><?= htmlspecialchars(__('banner.draft_badge')) ?></span>
      <?php endif; ?>
      <div class="banner-admin-meta">
        <label><?= htmlspecialchars(__('banner.per_title')) ?></label>
        <input type="text" name="title[<?= htmlspecialchars($b['image']) ?>]" value="<?= htmlspecialchars($b['title']) ?>" placeholder="例: 新生活応援フェア開催中！">
        <label style="margin-top:8px;"><?= htmlspecialchars(__('banner.per_subtitle')) ?></label>
        <input type="text" name="subtitle[<?= htmlspecialchars($b['image']) ?>]" value="<?= htmlspecialchars($b['subtitle']) ?>" placeholder="例: 人気の家電をお得な価格で">
        <label style="margin-top:8px;"><?= htmlspecialchars(__('banner.per_link')) ?></label>
        <input type="text" name="link[<?= htmlspecialchars($b['image']) ?>]" value="<?= htmlspecialchars($b['link']) ?>" placeholder="例: list.php / product.php?id=nano-shower">
      </div>
      <div class="banner-admin-actions">
        <button type="button" class="adm-btn adm-btn-sm js-replace" data-path="<?= htmlspecialchars($b['image'], ENT_QUOTES) ?>" title="<?= htmlspecialchars(__('btn.replace')) ?>">
          <i class="fa-solid fa-image"></i> <?= htmlspecialchars(__('btn.replace')) ?>
        </button>
        <button type="button" class="adm-btn adm-btn-sm js-up" title="<?= htmlspecialchars(__('btn.up')) ?>"><i class="fa-solid fa-arrow-up"></i></button>
        <button type="button" class="adm-btn adm-btn-sm js-down" title="<?= htmlspecialchars(__('btn.down')) ?>"><i class="fa-solid fa-arrow-down"></i></button>
        <button type="button" class="adm-btn adm-btn-sm adm-btn-danger js-del" title="<?= htmlspecialchars(__('btn.delete')) ?>"><i class="fa-solid fa-trash"></i></button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="adm-formfoot">
    <button type="submit" class="adm-btn adm-btn-primary"><i class="fa-solid fa-floppy-disk"></i> <?= htmlspecialchars(__('banner.save_order')) ?></button>
  </div>
</form>

<!-- 削除・差し替え用（メインフォームの外に置く — ネスト form 禁止） -->
<form method="post" id="delForm" style="display:none;">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="path" id="delPath">
</form>
<form method="post" id="replaceForm" enctype="multipart/form-data" style="display:none;">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
  <input type="hidden" name="action" value="replace">
  <input type="hidden" name="path" id="replacePath">
  <input type="file" name="file" id="replaceFile" accept="image/jpeg,image/png,image/webp,image/gif">
</form>

<script>
(function () {
  var list = document.getElementById('bannerList');
  var replaceForm = document.getElementById('replaceForm');
  var replacePath = document.getElementById('replacePath');
  var replaceFile = document.getElementById('replaceFile');

  list.querySelectorAll('.js-replace').forEach(function (btn) {
    btn.addEventListener('click', function () {
      replacePath.value = btn.dataset.path || '';
      replaceFile.value = '';
      replaceFile.click();
    });
  });
  replaceFile.addEventListener('change', function () {
    if (!replaceFile.files || !replaceFile.files.length) return;
    if (!confirm(<?= json_encode(__('banner.replace_confirm'), JSON_UNESCAPED_UNICODE) ?>)) {
      replaceFile.value = '';
      return;
    }
    replaceForm.submit();
  });

  // 上下移動
  list.querySelectorAll('.js-up').forEach(function (b) {
    b.addEventListener('click', function () {
      var item = b.closest('.banner-admin-item');
      if (item.previousElementSibling) list.insertBefore(item, item.previousElementSibling);
    });
  });
  list.querySelectorAll('.js-down').forEach(function (b) {
    b.addEventListener('click', function () {
      var item = b.closest('.banner-admin-item');
      if (item.nextElementSibling) list.insertBefore(item.nextElementSibling, item);
    });
  });
  // 削除
  list.querySelectorAll('.js-del').forEach(function (b) {
    b.addEventListener('click', function () {
      if (!confirm(<?= json_encode(__('banner.delete_confirm'), JSON_UNESCAPED_UNICODE) ?>)) return;
      var img = b.closest('.banner-admin-item').dataset.img;
      document.getElementById('delPath').value = img;
      document.getElementById('delForm').submit();
    });
  });
})();
</script>
<?php endif; ?>
<?php admin_foot(); ?>
