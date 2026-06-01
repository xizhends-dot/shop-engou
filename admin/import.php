<?php
require_once __DIR__ . '/auth.php';
admin_require_login();
require_once __DIR__ . '/../lib/product_import.php';

$data = store_load();
$cats = $data['categories'];

$action = $_GET['action'] ?? '';
$format = ($_GET['format'] ?? 'xlsx') === 'csv' ? 'csv' : 'xlsx';

if ($action === 'template' || $action === 'sample') {
    $rows = import_sample_rows($cats);
    if ($format === 'csv') {
        import_send_csv('products_sample.csv', $rows);
    }
    import_send_xlsx('products_sample.xlsx', $rows);
}

if ($action === 'export') {
    $rows = import_build_export_rows($data['products']);
    if ($format === 'csv') {
        import_send_csv('products_export_' . date('Ymd_His') . '.csv', $rows);
    }
    import_send_xlsx('products_export_' . date('Ymd_His') . '.xlsx', $rows);
}

/* ---------------- アップロード取込 ---------------- */
$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $errors[] = 'セッションの有効期限が切れました。もう一度お試しください。';
    } elseif (!isset($_FILES['datafile']) || $_FILES['datafile']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Excel（.xlsx）または CSV ファイルを選択してください。';
    } else {
        $fname  = $_FILES['datafile']['name'] ?? '';
        $tmp    = $_FILES['datafile']['tmp_name'];
        $fmt    = import_detect_format($fname);

        if ($fmt === '') {
            $errors[] = '対応形式は .xlsx（推奨）または .csv です。';
        } elseif ($fmt === 'xlsx') {
            $parsed = import_read_xlsx($tmp);
        } else {
            $parsed = import_read_csv($tmp);
        }

        if (!empty($parsed['error'])) {
            $errors[] = $parsed['error'];
        } else {
            $sheet = import_parse_sheet_rows($parsed['rows']);
            if (!empty($sheet['error'])) {
                $errors[] = $sheet['error'];
            } else {
                $result = import_apply_rows(
                    $sheet['rows'],
                    $sheet['map'],
                    $data,
                    $cats,
                    !empty($parsed['from_csv'])
                );
                $data = $result['data'];
                if ($result['added'] + $result['updated'] > 0) {
                    if (store_save($data)) {
                        $report = [
                            'ok'   => "取り込み完了：新規 {$result['added']} 件 / 更新 {$result['updated']} 件。",
                            'rows' => $result['rowErrors'],
                        ];
                    } else {
                        $errors[] = store_save_error_message();
                    }
                } else {
                    $report = [
                        'ok'   => '取り込める有効な行がありませんでした。',
                        'rows' => $result['rowErrors'],
                    ];
                }
            }
        }
    }

    if (!empty($errors)) {
        $report = ['err' => $errors];
    }
}

$zipOk = class_exists('ZipArchive');
require_once __DIR__ . '/_layout.php';
$token = csrf_token();
admin_head('Excel取込');
?>
<div class="adm-head">
  <h2>Excel 取込・書き出し</h2>
  <a href="index.php" class="adm-btn"><i class="fa-solid fa-arrow-left"></i> 一覧へ</a>
</div>

<?php if (!$zipOk): ?>
<div class="adm-flash adm-flash-err">PHP の <strong>zip</strong> 拡張が無効です。Excel（.xlsx）を使うには宝塔 → PHP → 安装扩展 → zip を有効にしてください。</div>
<?php endif; ?>

<?php if ($report && !empty($report['err'])): ?>
<div class="adm-flash adm-flash-err"><ul><?php foreach ($report['err'] as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
<?php elseif ($report): ?>
<div class="adm-flash adm-flash-ok"><?= htmlspecialchars($report['ok']) ?></div>
<?php if (!empty($report['rows'])): ?>
<div class="adm-flash adm-flash-err">
  <strong>スキップした行：</strong>
  <ul><?php foreach ($report['rows'] as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>
<?php endif; ?>

<div class="adm-form" style="max-width:760px;">
  <h3 style="font-size:15px;margin-bottom:14px;color:var(--heading);">1. テンプレート / 現在のデータ</h3>
  <p class="adm-note" style="margin-bottom:12px;">
    <strong>推奨：Excel（.xlsx）</strong> — 日本語の文字化けが起きにくく、Excel でそのまま編集できます。
  </p>
  <div class="adm-formfoot" style="border:none;padding-top:0;margin-top:0;flex-wrap:wrap;gap:10px;">
    <a href="import.php?action=sample" class="adm-btn adm-btn-primary"><i class="fa-solid fa-file-excel"></i> サンプル Excel</a>
    <a href="import.php?action=export" class="adm-btn adm-btn-primary"><i class="fa-solid fa-file-export"></i> 商品を Excel 出力</a>
    <a href="import.php?action=sample&amp;format=csv" class="adm-btn" title="従来形式"><i class="fa-solid fa-file-csv"></i> サンプル CSV</a>
    <a href="import.php?action=export&amp;format=csv" class="adm-btn" title="従来形式"><i class="fa-solid fa-file-csv"></i> CSV 出力</a>
  </div>

  <hr style="border:none;border-top:1px solid var(--border);margin:24px 0;">

  <h3 style="font-size:15px;margin-bottom:14px;color:var(--heading);">2. ファイルをアップロード</h3>
  <p class="adm-note">
    商品番号（id）が既存と一致する行は<strong>更新</strong>、一致しない行は<strong>新規追加</strong>されます。<br>
    1行目はヘッダー：<code>id, category, name, tag, price, badge, icon, accent, desc, rating, reviews, image1 … image20</code>。<br>
    <code>category</code> はキー（<?= htmlspecialchars(implode(' / ', array_keys($cats))) ?>）または名称。<br>
    画像は <code>image1</code>（メイン）〜 <code>image20</code>。空欄の列は既存画像を維持します。
  </p>
  <form method="post" enctype="multipart/form-data" id="importForm">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
    <div class="adm-field">
      <label>Excel / CSV ファイル（ドラッグ＆ドロップ）</label>
      <div class="dropzone" id="importDrop">
        <i class="fa-solid fa-file-excel"></i>
        <p>ここに <strong>.xlsx</strong>（推奨）または .csv をドラッグ＆ドロップ</p>
        <div class="dz-btns">
          <button type="button" class="adm-btn" id="btnPickData"><i class="fa-solid fa-folder-open"></i> ファイルを選択</button>
        </div>
        <input type="file" name="datafile" id="datafileInput" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,.csv,text/csv" hidden>
        <div class="dz-status" id="importDropStatus"></div>
      </div>
      <small>推奨：<strong>.xlsx</strong>（UTF-8・文字化けしにくい）。従来の .csv も利用可能です。</small>
    </div>
    <div class="adm-formfoot">
      <button type="submit" class="adm-btn adm-btn-primary" id="btnImportSubmit"<?= $zipOk ? '' : ' disabled' ?>><i class="fa-solid fa-upload"></i> 取り込む</button>
    </div>
  </form>
</div>
<script>
(function () {
  var dz = document.getElementById('importDrop');
  var input = document.getElementById('datafileInput');
  var status = document.getElementById('importDropStatus');
  var form = document.getElementById('importForm');
  var btnPick = document.getElementById('btnPickData');
  if (!dz || !input) return;

  function extOk(name) {
    var n = (name || '').toLowerCase();
    return n.slice(-5) === '.xlsx' || n.slice(-4) === '.csv';
  }

  function setStatus(msg, isErr) {
    status.textContent = msg || '';
    status.className = 'dz-status' + (isErr ? ' dz-status-err' : msg ? ' dz-status-ok' : '');
  }

  function applyFile(file) {
    if (!file) return;
    if (!extOk(file.name)) {
      setStatus('.xlsx または .csv のみアップロードできます。', true);
      return;
    }
    try {
      var dt = new DataTransfer();
      dt.items.add(file);
      input.files = dt.files;
    } catch (e) {
      setStatus('お使いのブラウザではドロップに未対応です。「ファイルを選択」をご利用ください。', true);
      return;
    }
    dz.classList.add('has-file');
    var icon = dz.querySelector('i');
    if (icon) {
      icon.className = file.name.toLowerCase().slice(-4) === '.csv'
        ? 'fa-solid fa-file-csv'
        : 'fa-solid fa-file-excel';
    }
    setStatus('選択中: ' + file.name + '（' + Math.max(1, Math.round(file.size / 1024)) + ' KB）');
  }

  btnPick.addEventListener('click', function () { input.click(); });
  input.addEventListener('change', function () {
    if (input.files && input.files[0]) applyFile(input.files[0]);
  });

  ['dragenter', 'dragover'].forEach(function (ev) {
    dz.addEventListener(ev, function (e) { e.preventDefault(); e.stopPropagation(); dz.classList.add('over'); });
  });
  ['dragleave', 'drop'].forEach(function (ev) {
    dz.addEventListener(ev, function (e) {
      e.preventDefault(); e.stopPropagation();
      if (ev === 'dragleave' && dz.contains(e.relatedTarget)) return;
      dz.classList.remove('over');
    });
  });
  dz.addEventListener('drop', function (e) {
    var files = e.dataTransfer && e.dataTransfer.files;
    if (!files || !files.length) return;
    if (files.length > 1) {
      setStatus('1回に1ファイルだけ取り込めます。', true);
      return;
    }
    applyFile(files[0]);
  });

  form.addEventListener('submit', function (e) {
    if (!input.files || !input.files.length) {
      e.preventDefault();
      setStatus('ファイルをドロップするか、選択してください。', true);
      return;
    }
    dz.classList.add('uploading');
    setStatus('取り込み中…');
  });
})();
</script>
<?php admin_foot(); ?>
