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
    import_send_xlsx('products_sample.xlsx', import_sample_xlsx_product_rows($cats), import_build_sku_sheet_rows([import_sample_grouped_product($cats)]));
}

if ($action === 'export') {
    $rows = import_build_export_rows($data['products']);
    if ($format === 'csv') {
        import_send_csv('products_export_' . date('Ymd_His') . '.csv', $rows);
    }
    import_send_xlsx('products_export_' . date('Ymd_His') . '.xlsx', import_xlsx_product_rows(import_build_export_rows($data['products'], false)), import_build_sku_sheet_rows($data['products']));
}

/* ---------------- アップロード取込 ---------------- */
$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $errors[] = __('login.error_session');
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
        } elseif (!$errors) {
            $result = import_apply_workbook($parsed, $data, $cats);
            if (!empty($result['error'])) {
                $errors[] = $result['error'];
            } else {
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
admin_head(__('page.import'));
?>
<div class="adm-head">
  <h2><?= htmlspecialchars(__('page.import')) ?></h2>
  <a href="products.php" class="adm-btn"><i class="fa-solid fa-arrow-left"></i> <?= htmlspecialchars(__('btn.back_products')) ?></a>
</div>

<?php if (!$zipOk): ?>
<div class="adm-flash adm-flash-err"><?= __('import.zip_warn') ?></div>
<?php endif; ?>
<?php if (!function_exists('curl_init')): ?>
<div class="adm-flash adm-flash-err">外部画像URLを取り込むには PHP の cURL 拡張を有効にしてください。本サイトの画像パスを使用する取り込みはそのまま利用できます。</div>
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
    <a href="import.php?action=sample&amp;format=csv" class="adm-btn" title="UTF-8 CSV"><i class="fa-solid fa-file-csv"></i> サンプル CSV</a>
    <a href="import.php?action=export&amp;format=csv" class="adm-btn" title="UTF-8 CSV"><i class="fa-solid fa-file-csv"></i> CSV 出力</a>
  </div>
  <p class="adm-note">Excel は「商品」と「SKU」の2シート。SKUは1組み合わせ1行、CODEで商品に紐づけます。「SAMPLE-001・002」の画像パスはダミーです。CSVは従来のJSON形式です。</p>

  <hr style="border:none;border-top:1px solid var(--border);margin:24px 0;">

  <h3 style="font-size:15px;margin-bottom:14px;color:var(--heading);">2. ファイルをアップロード</h3>
  <div class="adm-note">
    商品番号（id）が既存と一致する行は<strong>更新</strong>、一致しない行は<strong>新規追加</strong>されます。<br>
    Excel の「商品」シートは <code>id, category, name, tag, price, price_max, badge, icon, accent, desc, rating, reviews, image1 … image20</code>。SKUのある商品は価格を空欄にできます。既存商品のSKUだけ変える場合は「SKU」シートのみ行を追加し、商品シートはヘッダーだけ残してください。同じ CODE の SKU 行は一括で置き換えます。該当 CODE にSKU行がなければ既存SKUを保持します。<br>
    「SKU」シートの列は <code>CODE, タイトル1, 内容1, 価格, タイトル2, 内容2, タイトル3, 内容3, SKU番号, 表示名</code>。単一仕様なら先頭4列だけ入力します。SKU番号・表示名は任意で、空欄なら自動生成します。タイトルと内容は左から順に入力し、同じCODEではタイトルと順序をそろえてください。最大3グループ、各30選択肢、1商品100SKUです。未知のCODEや重複した組み合わせは取り込まずエラーを表示します。<br>
    <details><summary>旧CSV・旧ExcelのJSON列について</summary>
    旧形式の表頭は <code>id, category, name, tag, price, price_max, skus, sku_groups, badge, icon, accent, desc, rating, reviews, image1 … image20</code>。2シートある場合は同じCODEの「SKU」シートを優先します。<br>
    <code>skus</code> は任意列です。JSON 配列（例：<code>[{"id":"A","name":"白 / S","price":1000}]</code>）で指定し、各 SKU の価格から商品価格を自動計算します。最大100件、番号は同一商品内で大文字・小文字を区別せず一意にしてください。<br>
    <code>sku_groups</code> も任意列です。例：<code>[{"name":"カラー","options":["RED","BLUE"]},{"name":"サイズ","options":["S","M"]}]</code>。最大3グループ・各30選択肢、グループ名は60文字、選択肢は100文字までで、グループ名および同一グループ内の選択肢は重複できません。<br>
    グループがある SKU は <code>options</code> をグループ順に指定します（例：<code>[{"id":"RED-M","name":"","price":1200,"options":["RED","M"]}]</code>）。SKU 名が空なら選択肢を「 / 」でつなぎます。グループありは400文字、従来 SKU は200文字まで。組み合わせは一意で、一部の組み合わせだけ登録できます。<br>
    各列自体がない場合は既存のグループ・SKU を維持し、列の空欄または <code>[]</code> はその項目を解除します。グループだけ残して SKU を空にすることや、グループを解除して <code>options</code> を残すことはできません。組み合わせが一致しない行は、画像のダウンロード前に行全体をスキップします。両方解除する場合は両列を空欄または <code>[]</code> にしてください。</details>
    <code>price</code> は必須列ですが、SKU がある行では値を空欄にできます。SKU がない場合は最低価格（必須）、<code>price_max</code> は最高価格（任意）です。価格は0〜2147483647の半角整数で、最高価格は最低価格以上にしてください。<br>
    SKU がない行の <code>price_max</code> 列の空欄は最高価格を解除し、単一価格になります。旧ファイルなどで列自体がない場合、既存商品の最高価格は維持され、新規商品は単一価格になります。維持する最高価格を最低価格が超える行は取り込めません。<br>
    <code>category</code> はキー（<?= htmlspecialchars(implode(' / ', array_keys($cats))) ?>）または名称。<br>
    画像は <code>image1</code>（メイン）〜 <code>image20</code> に、本站の画像パスまたは公開 <strong>HTTP(S) 画像URL</strong>を指定します。URLの画像は取り込み時にサーバーへダウンロードされ、以後は本站の画像を表示します。<br>
    JPG / PNG / WebP / GIF、1枚8MBまで。URLの拡張子ではなく画像の実体を検証します。利用権限のある画像のみ指定してください。<br>
    全画像欄が空なら既存画像を維持し、1枚でも指定すると指定順の画像一覧に置き換えます。ダウンロード等に失敗した行は商品を変更せずスキップします。<br>
    外部画像は1回につき最大50URL・ダウンロード時間予算20秒で処理します（DNS問い合わせやサーバー保存に別途時間がかかる場合があります）。上限に達した場合は成功済みの行を除き、小分けに再取り込みしてください。途中までダウンロードされた画像は画像管理に残る場合があります。<br>
    PHP cURL 拡張が必要です。ログイン必須・アクセス制限付き・転送URLは利用できません。転送の場合は最終的な画像URLを指定してください。
  </div>
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
    document.getElementById('btnImportSubmit').disabled = true;
    setStatus('取り込み中… 外部画像のダウンロードが完了するまで、このページを閉じないでください。');
  });
})();
</script>
<?php admin_foot(); ?>
