<?php
require_once __DIR__ . '/auth.php';
admin_require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if (!csrf_check($_POST['csrf'] ?? '')) {
        set_flash('セッションの有効期限が切れました。', 'err');
        header('Location: banners.php'); exit;
    }

    if ($act === 'settings') {
        $keys = ['eyebrow', 'title', 'subtitle', 'btn1_text', 'btn1_link', 'btn2_text', 'btn2_link'];
        $s = [];
        foreach ($keys as $kk) { $s[$kk] = trim((string)($_POST[$kk] ?? '')); }
        banner_settings_save($s);
        set_flash('バナー共通設定を保存しました。');
        header('Location: banners.php'); exit;
    }

    if ($act === 'upload') {
        $banners = banners_load();
        $ok = 0; $ng = 0;
        if (!empty($_FILES['files']['name'][0])) {
            $n = count($_FILES['files']['name']);
            for ($i = 0; $i < $n; $i++) {
                if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                    $one = [
                        'name'     => $_FILES['files']['name'][$i],
                        'tmp_name' => $_FILES['files']['tmp_name'][$i],
                        'error'    => $_FILES['files']['error'][$i],
                        'size'     => $_FILES['files']['size'][$i],
                    ];
                    $rel = store_handle_upload($one, BANNER_DIR, BANNER_REL);
                    if ($rel) { $banners[] = ['image' => $rel, 'link' => '']; $ok++; } else { $ng++; }
                }
            }
        }
        banners_save($banners);
        set_flash("バナーを追加しました：成功 {$ok} 件" . ($ng ? " / 失敗 {$ng} 件" : ''), $ng ? 'err' : 'ok');
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
            if (isset($byImg[$img])) {
                $new[] = [
                    'image'    => $img,
                    'link'     => trim((string)($links[$img] ?? '')),
                    'title'    => trim((string)($titles[$img] ?? '')),
                    'subtitle' => trim((string)($subs[$img] ?? '')),
                ];
            }
        }
        banners_save($new);
        set_flash('保存しました。');
        header('Location: banners.php'); exit;
    }

    if ($act === 'delete') {
        $path = (string)($_POST['path'] ?? '');
        $banners = array_values(array_filter(banners_load(), function ($b) use ($path) { return $b['image'] !== $path; }));
        banners_save($banners);
        store_delete_image($path);
        set_flash('バナーを削除しました。');
        header('Location: banners.php'); exit;
    }
}

$banners  = banners_load();
$settings = banner_settings_load();
require_once __DIR__ . '/_layout.php';
$token = csrf_token();
admin_head('バナー管理');
?>
<div class="adm-head">
  <h2>バナー管理 <span class="adm-count"><?= count($banners) ?> 枚</span></h2>
  <a href="index.php" class="adm-btn"><i class="fa-solid fa-arrow-left"></i> 控制台へ</a>
</div>

<!-- バナー共通設定 -->
<div class="adm-form" style="max-width:760px;margin-bottom:22px;">
  <h3 style="font-size:15px;margin-bottom:6px;color:var(--heading);">バナー共通設定</h3>
  <p class="adm-note">小見出し・ボタン・既定文案の設定です。各バナーで個別のタイトルを設定していない場合は、ここの「既定タイトル／サブテキスト」が表示されます。HTMLタグ（&lt;br&gt; / &lt;span class='accent'&gt;～&lt;/span&gt;）も使えます。</p>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
    <input type="hidden" name="action" value="settings">
    <div class="adm-field">
      <label>小見出し（eyebrow）</label>
      <input type="text" name="eyebrow" value="<?= htmlspecialchars($settings['eyebrow']) ?>">
    </div>
    <div class="adm-field">
      <label>既定タイトル</label>
      <input type="text" name="title" value="<?= htmlspecialchars($settings['title']) ?>">
    </div>
    <div class="adm-field">
      <label>既定サブテキスト</label>
      <input type="text" name="subtitle" value="<?= htmlspecialchars($settings['subtitle']) ?>">
    </div>
    <div class="adm-grid">
      <div class="adm-field">
        <label>ボタン1 テキスト</label>
        <input type="text" name="btn1_text" value="<?= htmlspecialchars($settings['btn1_text']) ?>">
      </div>
      <div class="adm-field">
        <label>ボタン1 リンク（既定）</label>
        <input type="text" name="btn1_link" value="<?= htmlspecialchars($settings['btn1_link']) ?>">
      </div>
    </div>
    <div class="adm-grid">
      <div class="adm-field">
        <label>ボタン2 テキスト</label>
        <input type="text" name="btn2_text" value="<?= htmlspecialchars($settings['btn2_text']) ?>">
      </div>
      <div class="adm-field">
        <label>ボタン2 リンク</label>
        <input type="text" name="btn2_link" value="<?= htmlspecialchars($settings['btn2_link']) ?>">
      </div>
    </div>
    <div class="adm-formfoot">
      <button type="submit" class="adm-btn adm-btn-primary"><i class="fa-solid fa-floppy-disk"></i> 共通設定を保存</button>
    </div>
  </form>
</div>

<div class="adm-form" style="max-width:760px;">
  <h3 style="font-size:15px;margin-bottom:12px;color:var(--heading);">バナー画像をアップロード</h3>
  <p class="adm-note">トップページの大きなバナーに表示され、2枚以上で自動的にスライド（横長の画像、目安 1600×700px 程度を推奨）。</p>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
    <input type="hidden" name="action" value="upload">
    <div class="adm-field" style="margin-bottom:12px;">
      <input type="file" name="files[]" accept="image/*" multiple required>
      <small>JPG / PNG / WebP / GIF、1枚あたり8MBまで。複数選択できます。</small>
    </div>
    <button type="submit" class="adm-btn adm-btn-primary"><i class="fa-solid fa-upload"></i> アップロード</button>
  </form>
</div>

<?php if (empty($banners)): ?>
  <div class="adm-empty" style="margin-top:20px;">まだバナーがありません。アップロードするとトップに表示されます（未登録の間は従来のグラデーション背景）。</div>
<?php else: ?>
<form method="post" style="margin-top:20px;">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
  <input type="hidden" name="action" value="save">
  <div class="banner-admin-list" id="bannerList">
    <?php foreach ($banners as $b): ?>
    <div class="banner-admin-item" data-img="<?= htmlspecialchars($b['image']) ?>">
      <input type="hidden" name="order[]" value="<?= htmlspecialchars($b['image']) ?>">
      <img src="../<?= htmlspecialchars($b['image']) ?>" alt="">
      <div class="banner-admin-meta">
        <label>タイトル（このバナーの見出し・HTML可）</label>
        <input type="text" name="title[<?= htmlspecialchars($b['image']) ?>]" value="<?= htmlspecialchars($b['title']) ?>" placeholder="例: 新生活応援フェア開催中！">
        <label style="margin-top:8px;">サブテキスト</label>
        <input type="text" name="subtitle[<?= htmlspecialchars($b['image']) ?>]" value="<?= htmlspecialchars($b['subtitle']) ?>" placeholder="例: 人気の家電をお得な価格で">
        <label style="margin-top:8px;">「商品を見る」ボタンのリンク先（任意）</label>
        <input type="text" name="link[<?= htmlspecialchars($b['image']) ?>]" value="<?= htmlspecialchars($b['link']) ?>" placeholder="例: list.php / product.php?id=nano-shower">
      </div>
      <div class="banner-admin-actions">
        <button type="button" class="adm-btn adm-btn-sm js-up" title="上へ"><i class="fa-solid fa-arrow-up"></i></button>
        <button type="button" class="adm-btn adm-btn-sm js-down" title="下へ"><i class="fa-solid fa-arrow-down"></i></button>
        <button type="button" class="adm-btn adm-btn-sm adm-btn-danger js-del" title="削除"><i class="fa-solid fa-trash"></i></button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="adm-formfoot">
    <button type="submit" class="adm-btn adm-btn-primary"><i class="fa-solid fa-floppy-disk"></i> 並び順・リンクを保存</button>
  </div>
</form>

<!-- 削除用の単独フォーム（JSで送信） -->
<form method="post" id="delForm" style="display:none;">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="path" id="delPath">
</form>

<script>
(function () {
  var list = document.getElementById('bannerList');
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
      if (!confirm('このバナーを削除しますか？')) return;
      var img = b.closest('.banner-admin-item').dataset.img;
      document.getElementById('delPath').value = img;
      document.getElementById('delForm').submit();
    });
  });
})();
</script>
<?php endif; ?>
<?php admin_foot(); ?>
