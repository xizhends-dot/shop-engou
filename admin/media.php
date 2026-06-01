<?php
require_once __DIR__ . '/auth.php';
admin_require_login();

$cur = media_safe_dir($_GET['dir'] ?? '');   // 現在のフォルダ（images/products 起点）

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act    = $_POST['action'] ?? '';
    $postDir = media_safe_dir($_POST['dir'] ?? '');
    if (!csrf_check($_POST['csrf'] ?? '')) {
        set_flash('セッションの有効期限が切れました。', 'err');
        header('Location: media.php' . ($postDir !== '' ? '?dir=' . urlencode($postDir) : '')); exit;
    }
    $redir = 'media.php' . ($postDir !== '' ? '?dir=' . urlencode($postDir) : '');

    if ($act === 'mkdir') {
        $name = media_safe_name($_POST['folder'] ?? '');
        if ($name === '') {
            set_flash('フォルダ名は半角英数・ハイフン・アンダースコア（1〜64文字）で入力してください。', 'err');
        } else {
            media_create_folder($postDir, $name);
            set_flash('フォルダ「' . $name . '」を作成しました。');
        }
        header('Location: ' . $redir); exit;
    }

    if ($act === 'upload') {
        $absDir = SHOP_BASE . '/' . ($postDir === '' ? UPLOAD_REL : UPLOAD_REL . '/' . $postDir);
        $relDir = $postDir === '' ? UPLOAD_REL : UPLOAD_REL . '/' . $postDir;
        $ok = 0; $ng = 0; $paths = []; $errors = [];
        if (empty($_FILES['files']['name'][0])) {
            $errors[] = 'アップロードされたファイルがありません（POST サイズ上限 post_max_size を確認）';
        } else {
            $n = count($_FILES['files']['name']);
            for ($i = 0; $i < $n; $i++) {
                $one = [
                    'name'     => $_FILES['files']['name'][$i],
                    'tmp_name' => $_FILES['files']['tmp_name'][$i],
                    'error'    => $_FILES['files']['error'][$i],
                    'size'     => $_FILES['files']['size'][$i],
                ];
                $fileErr = null;
                $rel = store_handle_upload($one, $absDir, $relDir, $fileErr);
                if ($rel) { $ok++; $paths[] = $rel; }
                else { $ng++; $errors[] = ($one['name'] ?? 'file') . ': ' . ($fileErr ?: '保存失敗'); }
            }
        }
        // AJAX（ドラッグ＆ドロップ等）の場合は JSON を返す（アップロード先パスも返す）
        if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => $ok, 'ng' => $ng, 'paths' => $paths, 'errors' => $errors], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $flash = "画像をアップロードしました：成功 {$ok} 件" . ($ng ? " / 失敗 {$ng} 件" : '');
        if (!empty($errors)) { $flash .= ' — ' . implode(' / ', array_slice($errors, 0, 3)); }
        set_flash($flash, $ng ? 'err' : 'ok');
        header('Location: ' . $redir); exit;
    }

    if ($act === 'delete') {
        $data = store_load();
        $path = (string)($_POST['path'] ?? '');
        if (media_validate_product_image_path($path, $postDir)) {
            $removed = store_unlink_image_refs($data, $path);
            store_delete_image($path);
            if ($removed > 0) { store_save($data); }
            set_flash('画像を削除しました' . ($removed ? "（{$removed} 件の商品から参照を解除）" : '') . '。');
        } else {
            set_flash('削除対象の画像パスが不正です。', 'err');
        }
        header('Location: ' . $redir); exit;
    }

    if ($act === 'delete_batch') {
        $data   = store_load();
        $paths  = isset($_POST['paths']) && is_array($_POST['paths']) ? $_POST['paths'] : [];
        $del    = 0;
        $refs   = 0;
        $skip   = 0;
        foreach ($paths as $path) {
            $path = (string)$path;
            if (!media_validate_product_image_path($path, $postDir)) {
                $skip++;
                continue;
            }
            $refs += store_unlink_image_refs($data, $path);
            store_delete_image($path);
            $del++;
        }
        if ($refs > 0) { store_save($data); }
        if ($del > 0) {
            $msg = "画像を {$del} 枚削除しました";
            if ($refs > 0) { $msg .= "（{$refs} 件の商品参照を解除）"; }
            if ($skip > 0) { $msg .= "。スキップ {$skip} 件"; }
            set_flash($msg . '。');
        } else {
            set_flash('削除できる画像が選択されていません。', 'err');
        }
        header('Location: ' . $redir); exit;
    }

    if ($act === 'rmdir') {
        $target = media_safe_dir($_POST['target'] ?? '');
        if ($target !== '' && media_delete_folder($target)) {
            set_flash('フォルダを削除しました。');
        } else {
            set_flash('フォルダを削除できませんでした（空でない、または存在しません）。', 'err');
        }
        header('Location: ' . $redir); exit;
    }
}

try {
    $data = store_load();
} catch (Throwable $e) {
    $data = ['categories' => [], 'products' => []];
    set_flash('データの読み込みに失敗しました: ' . $e->getMessage(), 'err');
}

$list  = media_list($cur);
$usage = store_image_usage($data);
$tree  = media_tree('');

// 画像のページング（1ページ最大40枚）
$perPage    = 40;
$total      = count($list['images']);
$pages      = max(1, (int)ceil($total / $perPage));
$page       = max(1, min($pages, (int)($_GET['page'] ?? 1)));
$pageImages = array_slice($list['images'], ($page - 1) * $perPage, $perPage);

function media_url($dir, $page = 1) {
    $q = [];
    if ($dir !== '') { $q['dir'] = $dir; }
    if ($page > 1)   { $q['page'] = $page; }
    return 'media.php' . ($q ? '?' . http_build_query($q) : '');
}
function render_media_tree($nodes, $cur) {
    if (empty($nodes)) { return; }
    echo '<ul class="mtree">';
    foreach ($nodes as $n) {
        $active = ($cur === $n['path']) ? ' active' : '';
        echo '<li><span class="mtree-row' . $active . '">';
        echo '<a class="mtree-link" href="' . htmlspecialchars(media_url($n['path'])) . '"><i class="fa-solid fa-folder"></i> ' . htmlspecialchars($n['name']) . '</a>';
        echo '<button type="button" class="mtree-del" data-path="' . htmlspecialchars($n['path'], ENT_QUOTES) . '" data-name="' . htmlspecialchars($n['name'], ENT_QUOTES) . '" title="空フォルダを削除"><i class="fa-solid fa-xmark"></i></button>';
        echo '</span>';
        render_media_tree($n['children'], $cur);
        echo '</li>';
    }
    echo '</ul>';
}

require_once __DIR__ . '/_layout.php';
$token = csrf_token();
admin_head('画像管理');

// パンくず用
$crumbs = $cur === '' ? [] : explode('/', $cur);
?>
<div class="adm-head">
  <h2>画像管理</h2>
  <a href="index.php" class="adm-btn"><i class="fa-solid fa-arrow-left"></i> 一覧へ</a>
</div>

<!-- 操作（アップロード + 新規フォルダ）：上部・全幅 -->
<div class="adm-form" style="max-width:none;">
  <div class="media-crumb" style="margin-bottom:14px;">
    <a href="media.php">products</a>
    <?php $acc = ''; foreach ($crumbs as $c): $acc = $acc === '' ? $c : $acc . '/' . $c; ?>
      <span class="sep">/</span><a href="media.php?dir=<?= urlencode($acc) ?>"><?= htmlspecialchars($c) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="media-ops">
    <div class="media-op">
      <label>このフォルダに画像をアップロード（ドラッグ＆ドロップ / フォルダごと可）</label>
      <div class="dropzone" id="dropzone">
        <i class="fa-solid fa-cloud-arrow-up"></i>
        <p>ここに画像（または画像フォルダ）をドラッグ＆ドロップ</p>
        <div class="dz-btns">
          <button type="button" class="adm-btn" id="btnPickFiles"><i class="fa-solid fa-images"></i> ファイルを選択</button>
          <button type="button" class="adm-btn" id="btnPickFolder"><i class="fa-solid fa-folder-open"></i> フォルダを選択</button>
        </div>
        <input type="file" id="fileInput" accept="image/*" multiple hidden>
        <input type="file" id="folderInput" accept="image/*" webkitdirectory multiple hidden>
        <div class="dz-status" id="dzStatus"></div>
      </div>
    </div>
    <form method="post" class="media-op">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
      <input type="hidden" name="action" value="mkdir">
      <input type="hidden" name="dir" value="<?= htmlspecialchars($cur) ?>">
      <label>このフォルダ内に新規フォルダ（例: 商品番号 aaa）</label>
      <div class="media-op-row">
        <input type="text" name="folder" placeholder="半角英数・ハイフン" pattern="[A-Za-z0-9_\-]+">
        <button type="submit" class="adm-btn"><i class="fa-solid fa-folder-plus"></i> 作成</button>
      </div>
    </form>
  </div>
</div>

<div class="media-layout">
  <!-- 左：フォルダツリー -->
  <aside class="media-tree-pane">
    <div class="media-tree-head">フォルダ</div>
    <a class="mtree-root<?= $cur === '' ? ' active' : '' ?>" href="media.php"><i class="fa-solid fa-folder-tree"></i> products</a>
    <?php render_media_tree($tree, $cur); ?>
  </aside>

  <!-- 右：画像 -->
  <div class="media-main">
    <!-- 画像一覧（ページング） -->
    <?php if ($total === 0): ?>
      <div class="adm-empty" style="margin-top:20px;">このフォルダに画像はありません。アップロードするか、左のツリーから別のフォルダを選んでください。</div>
    <?php else: ?>
    <div class="media-batch-bar">
      <div class="media-batch-inner">
        <span class="media-count"><?= $total ?> 枚<?= $pages > 1 ? '（' . $page . ' / ' . $pages . ' ページ）' : '' ?></span>
        <div class="media-batch-actions">
          <button type="button" class="adm-btn adm-btn-sm" id="btnSelectPage"><i class="fa-solid fa-check-double" aria-hidden="true"></i><span>このページを全選択</span></button>
          <button type="button" class="adm-btn adm-btn-sm" id="btnSelectFolder"><i class="fa-solid fa-folder-open" aria-hidden="true"></i><span>フォルダ内を全選択</span></button>
          <button type="button" class="adm-btn adm-btn-sm" id="btnSelectNone"><i class="fa-solid fa-xmark" aria-hidden="true"></i><span>選択解除</span></button>
          <button type="button" class="adm-btn adm-btn-sm adm-btn-danger" id="btnBatchDelete" disabled><i class="fa-solid fa-trash" aria-hidden="true"></i><span>選択を削除（<span id="selCount">0</span>）</span></button>
        </div>
      </div>
    </div>
    <form method="post" id="mediaBatchForm" style="display:none;">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
      <input type="hidden" name="action" value="delete_batch">
      <input type="hidden" name="dir" value="<?= htmlspecialchars($cur) ?>">
      <div id="batchPathsContainer"></div>
    </form>
    <div class="media-grid" id="mediaGrid">
      <?php foreach ($pageImages as $img): $used = $usage[$img] ?? []; ?>
      <div class="media-item" data-path="<?= htmlspecialchars($img, ENT_QUOTES) ?>">
        <label class="media-pick">
          <input type="checkbox" class="media-cb" value="<?= htmlspecialchars($img, ENT_QUOTES) ?>">
          <span class="media-pick-ui" aria-hidden="true"></span>
        </label>
        <div class="media-thumb"><img src="../<?= htmlspecialchars($img) ?>" alt="" loading="lazy"></div>
        <div class="media-meta">
          <input type="text" class="media-path" value="<?= htmlspecialchars($img) ?>" readonly onclick="this.select()">
          <button type="button" class="adm-btn adm-btn-sm media-copy" data-path="<?= htmlspecialchars($img) ?>"><i class="fa-solid fa-copy"></i> パスをコピー</button>
          <?php if ($used): ?><span class="media-used"><i class="fa-solid fa-link"></i> 使用中：<?= count($used) ?>件</span><?php else: ?><span class="media-unused">未使用</span><?php endif; ?>
          <form method="post" class="media-del" onsubmit="return confirm('この画像を削除しますか？<?= $used ? '\n※' . count($used) . '件の商品で使用中です。' : '' ?>');">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="dir" value="<?= htmlspecialchars($cur) ?>">
            <input type="hidden" name="path" value="<?= htmlspecialchars($img) ?>">
            <button type="submit" class="adm-btn adm-btn-sm adm-btn-danger"><i class="fa-solid fa-trash"></i></button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if ($pages > 1): ?>
    <nav class="adm-pager">
      <?php if ($page > 1): ?><a class="adm-pager-btn" href="<?= htmlspecialchars(media_url($cur, $page - 1)) ?>"><i class="fa-solid fa-chevron-left"></i></a><?php endif; ?>
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === $page): ?><span class="adm-pager-num active"><?= $i ?></span>
        <?php else: ?><a class="adm-pager-num" href="<?= htmlspecialchars(media_url($cur, $i)) ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $pages): ?><a class="adm-pager-btn" href="<?= htmlspecialchars(media_url($cur, $page + 1)) ?>"><i class="fa-solid fa-chevron-right"></i></a><?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- フォルダ削除用フォーム（ツリーのJSから送信） -->
<form method="post" id="mtreeDelForm" style="display:none;">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
  <input type="hidden" name="action" value="rmdir">
  <input type="hidden" name="dir" value="<?= htmlspecialchars($cur) ?>">
  <input type="hidden" name="target" id="mtreeDelTarget">
</form>

<script>
(function () {
  var dz = document.getElementById('dropzone');
  if (!dz) return;
  var CUR = <?= json_encode($cur) ?>;
  var CSRF = <?= json_encode($token) ?>;
  var statusEl = document.getElementById('dzStatus');
  var fileInput = document.getElementById('fileInput');
  var folderInput = document.getElementById('folderInput');

  function isImage(f) { return /^image\//.test(f.type) || /\.(jpe?g|png|webp|gif)$/i.test(f.name); }
  function setStatus(msg) { statusEl.textContent = msg; }

  document.getElementById('btnPickFiles').addEventListener('click', function () { fileInput.click(); });
  document.getElementById('btnPickFolder').addEventListener('click', function () { folderInput.click(); });
  fileInput.addEventListener('change', function () { upload(Array.prototype.slice.call(fileInput.files)); });
  folderInput.addEventListener('change', function () { upload(Array.prototype.slice.call(folderInput.files)); });

  // ドラッグ＆ドロップ
  ['dragenter', 'dragover'].forEach(function (ev) {
    dz.addEventListener(ev, function (e) { e.preventDefault(); e.stopPropagation(); dz.classList.add('over'); });
  });
  ['dragleave', 'drop'].forEach(function (ev) {
    dz.addEventListener(ev, function (e) { e.preventDefault(); e.stopPropagation(); if (ev === 'dragleave' && dz.contains(e.relatedTarget)) return; dz.classList.remove('over'); });
  });
  dz.addEventListener('drop', function (e) {
    var dt = e.dataTransfer;
    var items = dt.items;
    if (items && items.length && items[0].webkitGetAsEntry) {
      var entries = [];
      for (var i = 0; i < items.length; i++) { var en = items[i].webkitGetAsEntry(); if (en) entries.push(en); }
      collectEntries(entries).then(upload);
    } else {
      upload(Array.prototype.slice.call(dt.files));
    }
  });

  // フォルダを含むドロップを再帰的に展開
  function collectEntries(entries) {
    var files = [];
    function readEntry(entry) {
      return new Promise(function (res) {
        if (entry.isFile) { entry.file(function (f) { files.push(f); res(); }, res); }
        else if (entry.isDirectory) {
          var reader = entry.createReader(); var all = [];
          (function readBatch() {
            reader.readEntries(function (batch) {
              if (!batch.length) { Promise.all(all.map(readEntry)).then(res); }
              else { all = all.concat(Array.prototype.slice.call(batch)); readBatch(); }
            }, res);
          })();
        } else res();
      });
    }
    return Promise.all(entries.map(readEntry)).then(function () { return files; });
  }

  function upload(fileList) {
    var imgs = fileList.filter(isImage);
    if (!imgs.length) { setStatus('画像ファイル（JPG/PNG/WebP/GIF）が見つかりませんでした。'); return; }
    var fd = new FormData();
    fd.append('csrf', CSRF);
    fd.append('action', 'upload');
    fd.append('dir', CUR);
    imgs.forEach(function (f) { fd.append('files[]', f, f.name); });
    setStatus('アップロード中… ' + imgs.length + ' 枚');
    dz.classList.add('uploading');
    fetch('media.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
      .then(function (r) {
        return r.text().then(function (t) {
          try { return JSON.parse(t); }
          catch (e) { throw new Error(t.slice(0, 200) || ('HTTP ' + r.status)); }
        });
      })
      .then(function (j) {
        dz.classList.remove('uploading');
        if (j.ok > 0) {
          (j.paths || []).forEach(function () {});
          setStatus('完了：成功 ' + j.ok + ' 枚' + (j.ng ? ' / 失敗 ' + j.ng + ' 枚' : '') + '。更新します…');
          setTimeout(function () { location.reload(); }, 800);
        } else {
          var msg = (j.errors && j.errors.length) ? j.errors.join(' / ') : 'アップロードに失敗しました';
          setStatus(msg);
        }
      })
      .catch(function (e) { dz.classList.remove('uploading'); setStatus(e.message || 'アップロードに失敗しました。'); });
  }
})();

// 一括選択・削除
(function () {
  var grid = document.getElementById('mediaGrid');
  var form = document.getElementById('mediaBatchForm');
  var container = document.getElementById('batchPathsContainer');
  var btnDel = document.getElementById('btnBatchDelete');
  var selCount = document.getElementById('selCount');
  var ALL_IN_FOLDER = <?= json_encode($list['images'], JSON_UNESCAPED_UNICODE) ?>;
  var USAGE = <?= json_encode($usage, JSON_UNESCAPED_UNICODE) ?>;
  if (!grid || !form) return;

  var folderSelectAll = false;

  function cbs() { return Array.prototype.slice.call(grid.querySelectorAll('.media-cb')); }
  function selected() { return cbs().filter(function (c) { return c.checked; }); }
  function pathsToDelete() {
    if (folderSelectAll) return ALL_IN_FOLDER.slice();
    return selected().map(function (c) { return c.value; });
  }
  function updateUi() {
    var n = folderSelectAll ? ALL_IN_FOLDER.length : selected().length;
    selCount.textContent = n;
    btnDel.disabled = n === 0;
    grid.querySelectorAll('.media-item').forEach(function (el) {
      var cb = el.querySelector('.media-cb');
      el.classList.toggle('is-selected', cb && (cb.checked || folderSelectAll));
    });
  }
  function setAll(on) {
    folderSelectAll = false;
    cbs().forEach(function (c) { c.checked = on; });
    updateUi();
  }

  grid.addEventListener('change', function (e) {
    if (e.target && e.target.classList.contains('media-cb')) {
      folderSelectAll = false;
      updateUi();
    }
  });
  document.getElementById('btnSelectPage').addEventListener('click', function () { setAll(true); });
  document.getElementById('btnSelectNone').addEventListener('click', function () { setAll(false); });
  document.getElementById('btnSelectFolder').addEventListener('click', function () {
    folderSelectAll = true;
    cbs().forEach(function (c) { c.checked = true; });
    updateUi();
  });

  btnDel.addEventListener('click', function () {
    var paths = pathsToDelete();
    if (!paths.length) return;
    var used = 0;
    paths.forEach(function (p) {
      if (USAGE[p] && USAGE[p].length) used++;
    });
    var msg = paths.length + ' 枚の画像を削除しますか？';
    if (folderSelectAll && ALL_IN_FOLDER.length > cbs().length) {
      msg += '\n（このフォルダ内の全画像・全ページ対象）';
    }
    if (used) msg += '\n※商品で使用中の画像がある場合は参照を解除します。';
    if (!confirm(msg)) return;
    container.innerHTML = '';
    paths.forEach(function (p) {
      var inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = 'paths[]';
      inp.value = p;
      container.appendChild(inp);
    });
    form.submit();
  });
})();

// フォルダツリー：空フォルダ削除
document.querySelectorAll('.mtree-del').forEach(function (b) {
  b.addEventListener('click', function () {
    if (!confirm('フォルダ「' + b.dataset.name + '」を削除しますか？（空の場合のみ削除できます）')) return;
    document.getElementById('mtreeDelTarget').value = b.dataset.path;
    document.getElementById('mtreeDelForm').submit();
  });
});

document.querySelectorAll('.media-copy').forEach(function (b) {
  b.addEventListener('click', function () {
    var path = b.dataset.path;
    var done = function () { var o = b.innerHTML; b.innerHTML = '<i class="fa-solid fa-check"></i> コピーしました'; setTimeout(function(){ b.innerHTML = o; }, 1500); };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(path).then(done, fallback);
    } else { fallback(); }
    function fallback() {
      var t = document.createElement('textarea'); t.value = path; document.body.appendChild(t); t.select();
      try { document.execCommand('copy'); done(); } catch (e) {}
      document.body.removeChild(t);
    }
  });
});

// フォルダツリーの高さを「画像カード1枚」と揃える
(function () {
  var pane = document.querySelector('.media-tree-pane');
  var card = document.querySelector('.media-item');
  if (!pane || !card) return;
  function sync() { pane.style.height = card.getBoundingClientRect().height + 'px'; }
  sync();
  window.addEventListener('resize', sync);
  card.querySelectorAll('img').forEach(function (im) {
    if (!im.complete) im.addEventListener('load', sync);
  });
})();
</script>
<?php admin_foot(); ?>
