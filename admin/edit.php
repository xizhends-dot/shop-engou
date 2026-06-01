<?php
require_once __DIR__ . '/auth.php';
admin_require_login();
require_once __DIR__ . '/_layout.php';

$data    = store_load();
$cats    = $data['categories'];
$library = store_list_images();

// 編集対象の特定（id があれば編集、なければ新規）
$editId  = isset($_GET['id']) ? (string)$_GET['id'] : '';
$isEdit  = false;
$current = [
    'id' => '', 'category' => array_key_first($cats), 'name' => '', 'tag' => '',
    'price' => '', 'badge' => '', 'icon' => 'fa-box', 'accent' => '#DEF13F',
    'desc' => '', 'images' => [], 'attributes' => [], 'rating' => '', 'reviews' => '',
];
if ($editId !== '') {
    $idx = store_find_index($data['products'], $editId);
    if ($idx >= 0) { $current = array_merge($current, $data['products'][$idx]); $isEdit = true; }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $errors[] = 'セッションの有効期限が切れました。もう一度お試しください。';
    }
    // 入力取得
    $in = [
        'id'       => trim($_POST['id'] ?? ''),
        'category' => trim($_POST['category'] ?? ''),
        'name'     => trim($_POST['name'] ?? ''),
        'tag'      => trim($_POST['tag'] ?? ''),
        'price'    => trim($_POST['price'] ?? ''),
        'badge'    => trim($_POST['badge'] ?? ''),
        'icon'     => trim($_POST['icon'] ?? '') ?: 'fa-box',
        'accent'   => trim($_POST['accent'] ?? '') ?: '#DEF13F',
        'desc'     => trim($_POST['desc'] ?? ''),
        'rating'   => trim($_POST['rating'] ?? ''),
        'reviews'  => trim($_POST['reviews'] ?? ''),
    ];
    $postedEditId = (string)($_POST['edit_id'] ?? '');
    $isEdit = ($postedEditId !== '' && store_find_index($data['products'], $postedEditId) >= 0);

    // 編集時は id を変更不可（元のまま）
    if ($isEdit) { $in['id'] = $postedEditId; }

    // バリデーション
    if (!store_valid_id($in['id'])) {
        $errors[] = '商品番号は半角英数・ハイフン・アンダースコア（1〜64文字）で入力してください。';
    } elseif (!$isEdit && store_find_index($data['products'], $in['id']) >= 0) {
        $errors[] = 'その商品番号は既に使われています。別の番号を指定してください。';
    }
    if (!isset($cats[$in['category']])) { $errors[] = 'カテゴリを選択してください。'; }
    if ($in['name'] === '') { $errors[] = '商品名を入力してください。'; }
    if ($in['price'] === '' || !is_numeric($in['price']) || (int)$in['price'] < 0) { $errors[] = '価格は0以上の数値で入力してください。'; }

    if (empty($errors)) {
        // 画像: フロントで並べ替え・削除済みの順序つきリスト（images[]）
        $kept = [];
        foreach (($_POST['images'] ?? []) as $im) {
            $im = (string)$im;
            if (in_array($im, $kept, true)) { continue; }
            if (strpos($im, UPLOAD_REL . '/') === 0 && strpos($im, '..') === false && is_file(SHOP_BASE . '/' . $im)) {
                $kept[] = $im;
            }
        }
        // 画像ライブラリから選択された画像を末尾に追加
        foreach (($_POST['lib_images'] ?? []) as $li) {
            $li = (string)$li;
            if (in_array($li, $kept, true)) { continue; }
            if (strpos($li, UPLOAD_REL . '/') === 0 && strpos($li, '..') === false && is_file(SHOP_BASE . '/' . $li)) {
                $kept[] = $li;
            }
        }
        // 新規アップロード（複数・末尾に追加）
        if (!empty($_FILES['new_images']['name'][0])) {
            $n = count($_FILES['new_images']['name']);
            for ($i = 0; $i < $n; $i++) {
                if ($_FILES['new_images']['error'][$i] === UPLOAD_ERR_OK) {
                    $one = [
                        'name'     => $_FILES['new_images']['name'][$i],
                        'tmp_name' => $_FILES['new_images']['tmp_name'][$i],
                        'error'    => $_FILES['new_images']['error'][$i],
                        'size'     => $_FILES['new_images']['size'][$i],
                    ];
                    $rel = store_handle_upload($one);
                    if ($rel) { $kept[] = $rel; }
                    else { $errors[] = '画像のアップロードに失敗しました（対応形式: JPG/PNG/WebP/GIF、8MBまで）。'; }
                }
            }
        }

        // 商品属性（属性名 + 選択肢）
        $attributes = [];
        $attrNames = $_POST['attr_name'] ?? [];
        $attrOpts  = $_POST['attr_options'] ?? [];
        foreach ($attrNames as $ai => $anm) {
            $anm = trim((string)$anm);
            if ($anm === '') { continue; }
            $optsRaw = trim((string)($attrOpts[$ai] ?? ''));
            $opts = $optsRaw === '' ? [] : array_values(array_filter(array_map('trim', preg_split('#[,、，\\|/／・]#u', $optsRaw)), function ($v) { return $v !== ''; }));
            $attributes[] = ['name' => $anm, 'options' => $opts];
        }

        $record = [
            'id'         => $in['id'],
            'category'   => $in['category'],
            'icon'       => $in['icon'],
            'accent'     => $in['accent'],
            'images'     => array_values($kept),
            'name'       => $in['name'],
            'tag'        => $in['tag'],
            'price'      => (int)$in['price'],
            'badge'      => $in['badge'],
            'desc'       => $in['desc'],
            'attributes' => $attributes,
            'rating'     => max(0, min(5, (float)$in['rating'])),
            'reviews'    => max(0, (int)$in['reviews']),
        ];

        if (empty($errors)) {
            if ($isEdit) {
                $idx = store_find_index($data['products'], $in['id']);
                $data['products'][$idx] = $record;
            } else {
                $data['products'][] = $record;
            }
            if (store_save($data)) {
                set_flash($isEdit ? '商品を更新しました。' : '商品を追加しました。');
                header('Location: index.php');
                exit;
            } else {
                $errors[] = 'データの保存に失敗しました。data/ フォルダの書き込み権限をご確認ください。';
            }
        }
    }
    // エラー時はフォームに入力値を復元
    $current = array_merge($current, $in);
    if (isset($kept)) { $current['images'] = $kept; }
    if (isset($attributes)) { $current['attributes'] = $attributes; }
}

$token = csrf_token();
admin_head($isEdit ? '商品を編集' : '商品を追加');
?>
<div class="adm-head">
  <h2><?= $isEdit ? '商品を編集' : '商品を追加' ?></h2>
  <a href="index.php" class="adm-btn"><i class="fa-solid fa-arrow-left"></i> 一覧へ</a>
</div>

<?php if (!empty($errors)): ?>
<div class="adm-flash adm-flash-err">
  <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="adm-form">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
  <?php if ($isEdit): ?><input type="hidden" name="edit_id" value="<?= htmlspecialchars($current['id']) ?>"><?php endif; ?>

  <div class="adm-grid">
    <div class="adm-field">
      <label>商品番号（ID）<span class="req">*</span></label>
      <input type="text" name="id" value="<?= htmlspecialchars($current['id']) ?>" <?= $isEdit ? 'readonly' : 'placeholder="例: ENG-001"' ?> required>
      <small><?= $isEdit ? '商品番号は変更できません。' : '半角英数・ハイフン可。URLと画面に表示されます。' ?></small>
    </div>
    <div class="adm-field">
      <label>カテゴリ <span class="req">*</span></label>
      <select name="category">
        <?php foreach ($cats as $slug => $c): ?>
        <option value="<?= htmlspecialchars($slug) ?>" <?= $current['category'] === $slug ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="adm-field">
    <label>商品名 <span class="req">*</span></label>
    <input type="text" name="name" value="<?= htmlspecialchars($current['name']) ?>" required>
  </div>

  <div class="adm-field">
    <label>キャッチコピー（一覧カードの短い説明）</label>
    <input type="text" name="tag" value="<?= htmlspecialchars($current['tag']) ?>" placeholder="例: 節水・高洗浄力・美肌美髪">
  </div>

  <div class="adm-grid">
    <div class="adm-field">
      <label>価格（税込・数値）<span class="req">*</span></label>
      <input type="number" name="price" value="<?= htmlspecialchars((string)$current['price']) ?>" min="0" step="1" required>
    </div>
    <div class="adm-field">
      <label>バッジ（任意）</label>
      <input type="text" name="badge" value="<?= htmlspecialchars($current['badge']) ?>" placeholder="例: 人気No.1 / NEW">
    </div>
  </div>

  <div class="adm-grid">
    <div class="adm-field">
      <label>プレースホルダー用アイコン（Font Awesome）</label>
      <input type="text" name="icon" value="<?= htmlspecialchars($current['icon']) ?>" placeholder="例: fa-shower">
      <small>画像が無い場合に表示。<a href="https://fontawesome.com/search?o=r&m=free" target="_blank">アイコン一覧</a></small>
    </div>
    <div class="adm-field">
      <label>プレースホルダー色</label>
      <input type="color" name="accent" value="<?= htmlspecialchars($current['accent']) ?>" class="adm-color">
    </div>
  </div>

  <div class="adm-grid">
    <div class="adm-field">
      <label>評価（0〜5・0.5刻み）</label>
      <input type="number" name="rating" value="<?= htmlspecialchars((string)$current['rating']) ?>" min="0" max="5" step="0.1" placeholder="例: 4.5">
      <small>星で表示されます（前面の商品ページ）。0 のときは非表示。</small>
    </div>
    <div class="adm-field">
      <label>評価数（レビュー件数）</label>
      <input type="number" name="reviews" value="<?= htmlspecialchars((string)$current['reviews']) ?>" min="0" step="1" placeholder="例: 128">
    </div>
  </div>

  <div class="adm-field">
    <label>商品詳細</label>
    <textarea name="desc" rows="6"><?= htmlspecialchars($current['desc']) ?></textarea>
  </div>

  <div class="adm-field">
    <label>商品画像（先頭が<strong>メイン画像</strong>、2枚目以降が<strong>詳細画像</strong>・ボタンで並べ替え／削除）</label>
    <div class="imgm" id="imgManager">
      <?php foreach ($current['images'] as $img): ?>
      <div class="imgm-item" data-path="<?= htmlspecialchars($img) ?>">
        <input type="hidden" name="images[]" value="<?= htmlspecialchars($img) ?>">
        <span class="imgm-main">メイン</span>
        <img src="../<?= htmlspecialchars($img) ?>" alt="">
        <div class="imgm-tools">
          <button type="button" class="imgm-btn js-img-up" title="前へ"><i class="fa-solid fa-arrow-left"></i></button>
          <button type="button" class="imgm-btn js-img-down" title="後へ"><i class="fa-solid fa-arrow-right"></i></button>
          <button type="button" class="imgm-btn imgm-rm js-img-rm" title="この商品から外す"><i class="fa-solid fa-xmark"></i></button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <p class="adm-note" id="imgEmpty" style="<?= empty($current['images']) ? '' : 'display:none;' ?>">画像が未登録です（色＋アイコンのプレースホルダーが表示されます）。</p>

    <div style="margin-top:12px;">
      <label style="font-weight:700;font-size:13px;">画像を追加（複数可・ドラッグ＆ドロップ / フォルダごと可）</label>
      <div class="dropzone" id="imgDrop">
        <i class="fa-solid fa-cloud-arrow-up"></i>
        <p>ここに画像（複数）をドラッグ＆ドロップ、または</p>
        <div class="dz-btns">
          <button type="button" class="adm-btn" id="imgPickFiles"><i class="fa-solid fa-images"></i> ファイルを選択</button>
          <button type="button" class="adm-btn" id="imgPickFolder"><i class="fa-solid fa-folder-open"></i> フォルダを選択</button>
        </div>
        <input type="file" id="imgFileInput" accept="image/*" multiple hidden>
        <input type="file" id="imgFolderInput" accept="image/*" webkitdirectory multiple hidden>
        <div class="dz-status" id="imgDropStatus"></div>
      </div>
      <small>アップロードした画像はこの商品に追加され、<a href="media.php" target="_blank">画像管理</a>にも保存されます。JPG / PNG / WebP / GIF、1枚8MBまで。</small>
    </div>

    <?php
      $attached = $current['images'] ?? [];
      $pickable = array_values(array_filter($library, function ($im) use ($attached) { return !in_array($im, $attached, true); }));
    ?>
    <?php if (!empty($pickable)): ?>
    <details class="adm-libpick">
      <summary><i class="fa-solid fa-images"></i> 画像ライブラリから選択（<?= count($pickable) ?> 枚）</summary>
      <div class="lib-grid">
        <?php foreach ($pickable as $im): ?>
        <label class="lib-item">
          <input type="checkbox" name="lib_images[]" value="<?= htmlspecialchars($im) ?>">
          <img src="../<?= htmlspecialchars($im) ?>" alt="" loading="lazy">
        </label>
        <?php endforeach; ?>
      </div>
    </details>
    <?php endif; ?>
  </div>

  <!-- 商品属性 -->
  <div class="adm-field">
    <label>商品属性（カラー・サイズ など）</label>
    <p class="adm-note">「属性名」と「選択肢」（カンマ区切り）を入力。例: <code>カラー</code> ／ <code>レッド, ブラック, ホワイト</code>。前面の商品ページに表示されます。</p>
    <div id="attrList">
      <?php foreach ($current['attributes'] as $a): ?>
      <div class="attr-row">
        <input type="text" name="attr_name[]" value="<?= htmlspecialchars($a['name'] ?? '') ?>" placeholder="属性名（例: カラー）" class="attr-name">
        <input type="text" name="attr_options[]" value="<?= htmlspecialchars(implode(', ', $a['options'] ?? [])) ?>" placeholder="選択肢（カンマ区切り）" class="attr-opts">
        <button type="button" class="adm-btn adm-btn-sm adm-btn-danger js-attr-del" title="削除"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="adm-btn adm-btn-sm" id="attrAdd"><i class="fa-solid fa-plus"></i> 属性を追加</button>
  </div>

  <div class="adm-formfoot">
    <button type="submit" class="adm-btn adm-btn-primary"><i class="fa-solid fa-floppy-disk"></i> 保存する</button>
    <a href="index.php" class="adm-btn">キャンセル</a>
  </div>
</form>

<script>
(function () {
  // 画像マネージャ：並べ替え・削除・メイン表示
  var mgr = document.getElementById('imgManager');
  var empty = document.getElementById('imgEmpty');
  function refresh() {
    var items = mgr.querySelectorAll('.imgm-item');
    items.forEach(function (it, i) {
      var badge = it.querySelector('.imgm-main');
      if (badge) {
        if (i === 0) { badge.textContent = 'メイン'; badge.classList.remove('imgm-sub'); }
        else { badge.textContent = '詳細' + i; badge.classList.add('imgm-sub'); }
      }
    });
    if (empty) empty.style.display = items.length ? 'none' : '';
  }
  mgr.addEventListener('click', function (e) {
    var btn = e.target.closest('button'); if (!btn) return;
    var item = btn.closest('.imgm-item'); if (!item) return;
    if (btn.classList.contains('js-img-up') && item.previousElementSibling) {
      mgr.insertBefore(item, item.previousElementSibling);
    } else if (btn.classList.contains('js-img-down') && item.nextElementSibling) {
      mgr.insertBefore(item.nextElementSibling, item);
    } else if (btn.classList.contains('js-img-rm')) {
      item.remove();
    }
    refresh();
  });
  refresh();

  // 画像管理に1枚追加（重複は無視）
  function addImage(path) {
    if (mgr.querySelector('.imgm-item[data-path="' + (window.CSS && CSS.escape ? CSS.escape(path) : path) + '"]')) return;
    var div = document.createElement('div');
    div.className = 'imgm-item';
    div.setAttribute('data-path', path);
    var hid = document.createElement('input'); hid.type = 'hidden'; hid.name = 'images[]'; hid.value = path;
    var badge = document.createElement('span'); badge.className = 'imgm-main'; badge.textContent = 'メイン';
    var img = document.createElement('img'); img.src = '../' + path; img.alt = '';
    var tools = document.createElement('div'); tools.className = 'imgm-tools';
    tools.innerHTML = '<button type="button" class="imgm-btn js-img-up" title="前へ"><i class="fa-solid fa-arrow-left"></i></button>' +
      '<button type="button" class="imgm-btn js-img-down" title="後へ"><i class="fa-solid fa-arrow-right"></i></button>' +
      '<button type="button" class="imgm-btn imgm-rm js-img-rm" title="この商品から外す"><i class="fa-solid fa-xmark"></i></button>';
    div.appendChild(hid); div.appendChild(badge); div.appendChild(img); div.appendChild(tools);
    mgr.appendChild(div);
    refresh();
  }

  // 画像追加ドロップゾーン（AJAXで画像管理にアップロード → この商品に追加）
  var dz = document.getElementById('imgDrop');
  var dzStatus = document.getElementById('imgDropStatus');
  var fileInput = document.getElementById('imgFileInput');
  var folderInput = document.getElementById('imgFolderInput');
  function isImage(f) { return /^image\//.test(f.type) || /\.(jpe?g|png|webp|gif)$/i.test(f.name); }
  function dzSet(m) { dzStatus.textContent = m; }

  document.getElementById('imgPickFiles').addEventListener('click', function () { fileInput.click(); });
  document.getElementById('imgPickFolder').addEventListener('click', function () { folderInput.click(); });
  fileInput.addEventListener('change', function () { uploadImgs(Array.prototype.slice.call(fileInput.files)); });
  folderInput.addEventListener('change', function () { uploadImgs(Array.prototype.slice.call(folderInput.files)); });

  ['dragenter', 'dragover'].forEach(function (ev) {
    dz.addEventListener(ev, function (e) { e.preventDefault(); e.stopPropagation(); dz.classList.add('over'); });
  });
  ['dragleave', 'drop'].forEach(function (ev) {
    dz.addEventListener(ev, function (e) { e.preventDefault(); e.stopPropagation(); if (ev === 'dragleave' && dz.contains(e.relatedTarget)) return; dz.classList.remove('over'); });
  });
  dz.addEventListener('drop', function (e) {
    var items = e.dataTransfer.items;
    if (items && items.length && items[0].webkitGetAsEntry) {
      var entries = [];
      for (var i = 0; i < items.length; i++) { var en = items[i].webkitGetAsEntry(); if (en) entries.push(en); }
      collectEntries(entries).then(uploadImgs);
    } else {
      uploadImgs(Array.prototype.slice.call(e.dataTransfer.files));
    }
  });
  function collectEntries(entries) {
    var files = [];
    function readEntry(entry) {
      return new Promise(function (res) {
        if (entry.isFile) { entry.file(function (f) { files.push(f); res(); }, res); }
        else if (entry.isDirectory) {
          var reader = entry.createReader(), all = [];
          (function rb() { reader.readEntries(function (b) { if (!b.length) { Promise.all(all.map(readEntry)).then(res); } else { all = all.concat(Array.prototype.slice.call(b)); rb(); } }, res); })();
        } else res();
      });
    }
    return Promise.all(entries.map(readEntry)).then(function () { return files; });
  }
  function uploadImgs(fileList) {
    var imgs = fileList.filter(isImage);
    if (!imgs.length) { dzSet('画像ファイルが見つかりませんでした。'); return; }
    // ファイル名で自然順ソート（1, 2, …, 10 の順。詳細画像の並びを崩さない）
    imgs.sort(function (a, b) { return a.name.localeCompare(b.name, undefined, { numeric: true, sensitivity: 'base' }); });
    var fd = new FormData();
    fd.append('csrf', <?= json_encode($token) ?>);
    fd.append('action', 'upload');
    fd.append('dir', '');
    imgs.forEach(function (f) { fd.append('files[]', f, f.name); });
    dzSet('アップロード中… ' + imgs.length + ' 枚');
    dz.classList.add('uploading');
    fetch('media.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        (j.paths || []).forEach(addImage);
        dzSet('追加しました：' + (j.ok || 0) + ' 枚' + (j.ng ? ' / 失敗 ' + j.ng : ''));
        dz.classList.remove('uploading');
      })
      .catch(function () { dz.classList.remove('uploading'); dzSet('アップロードに失敗しました。'); });
  }

  // 属性エディタ：行の追加・削除
  var attrList = document.getElementById('attrList');
  document.getElementById('attrAdd').addEventListener('click', function () {
    var row = document.createElement('div');
    row.className = 'attr-row';
    row.innerHTML = '<input type="text" name="attr_name[]" placeholder="属性名（例: カラー）" class="attr-name">' +
      '<input type="text" name="attr_options[]" placeholder="選択肢（カンマ区切り）" class="attr-opts">' +
      '<button type="button" class="adm-btn adm-btn-sm adm-btn-danger js-attr-del" title="削除"><i class="fa-solid fa-xmark"></i></button>';
    attrList.appendChild(row);
  });
  attrList.addEventListener('click', function (e) {
    var btn = e.target.closest('.js-attr-del'); if (!btn) return;
    btn.closest('.attr-row').remove();
  });
})();
</script>
<?php admin_foot(); ?>
