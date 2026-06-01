<?php
require_once __DIR__ . '/auth.php';
admin_require_login();

$data = store_load();
$cats = $data['categories'];

// CSV の列順（テンプレート/エクスポート/インポート共通）
// 画像は image1〜image20 の最大20列（image1 がメイン画像）
define('CSV_IMG_MAX', 20);
$CSV_COLS = ['id', 'category', 'name', 'tag', 'price', 'badge', 'icon', 'accent', 'desc', 'rating', 'reviews'];
for ($i = 1; $i <= CSV_IMG_MAX; $i++) { $CSV_COLS[] = 'image' . $i; }

/* ---------------- CSV 出力（テンプレート / エクスポート） ---------------- */
function csv_output($filename, $rows) {
    global $CSV_COLS;
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM（Excelで文字化けしないように）
    $out = fopen('php://output', 'w');
    fputcsv($out, $CSV_COLS, ',', '"', '');
    foreach ($rows as $r) { fputcsv($out, $r, ',', '"', ''); }
    fclose($out);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'template' || $action === 'sample') {
    // 記入例つきサンプル（実際のカテゴリキーを使用）
    $catKeys = array_keys($cats);
    $c1 = $catKeys[0] ?? 'beauty';
    $c2 = $catKeys[1] ?? $c1;
    csv_output('products_sample.csv', [
        // image1 にメイン画像1枚の例
        array_merge(
            ['SAMPLE-001', $c1, 'サンプル商品（画像1枚）', 'キャッチコピー例', '4980', '人気No.1', 'fa-wand-magic-sparkles', '#DEF13F', '商品の詳細説明をここに記入します。', '4.5', '128'],
            array_pad(['images/products/sample/main.jpg'], CSV_IMG_MAX, '')
        ),
        // image1〜image3 に複数画像を指定した例（image1 が必須・メイン）
        array_merge(
            ['SAMPLE-002', $c2, 'サンプル商品（複数画像）', '静音・大容量', '12800', 'NEW', 'fa-house-chimney', '#93c5fd', 'image1 がメイン画像、image2 以降は任意です。', '0', '0'],
            array_pad(['images/products/sample/1.jpg', 'images/products/sample/2.jpg', 'images/products/sample/3.jpg'], CSV_IMG_MAX, '')
        ),
    ]);
}

if ($action === 'export') {
    $rows = [];
    foreach ($data['products'] as $p) {
        $imgs = array_slice($p['images'] ?? [], 0, CSV_IMG_MAX);
        $rows[] = array_merge([
            $p['id'],
            $p['category'],
            $p['name'],
            $p['tag'] ?? '',
            $p['price'],
            $p['badge'] ?? '',
            $p['icon'] ?? 'fa-box',
            $p['accent'] ?? '#DEF13F',
            $p['desc'] ?? '',
            $p['rating'] ?? 0,
            $p['reviews'] ?? 0,
        ], array_pad($imgs, CSV_IMG_MAX, ''));
    }
    csv_output('products_export_' . date('Ymd_His') . '.csv', $rows);
}

/* ---------------- CSV 取り込み（アップロード） ---------------- */
$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $errors[] = 'セッションの有効期限が切れました。もう一度お試しください。';
    } elseif (!isset($_FILES['csvfile']) || $_FILES['csvfile']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'CSVファイルを選択してください。';
    }

    if (empty($errors)) {
        $raw = file_get_contents($_FILES['csvfile']['tmp_name']);

        // BOM 除去 → UTF-8 へ（Excel の Shift_JIS / CP932 対応）
        if (substr($raw, 0, 3) === "\xEF\xBB\xBF") { $raw = substr($raw, 3); }
        if (function_exists('mb_detect_encoding')) {
            $enc = mb_detect_encoding($raw, ['UTF-8', 'SJIS-win', 'CP932', 'SJIS', 'EUC-JP'], true);
            if ($enc && $enc !== 'UTF-8' && function_exists('mb_convert_encoding')) {
                $conv = @mb_convert_encoding($raw, 'UTF-8', $enc);
                if ($conv !== false) { $raw = $conv; }
            }
        }
        if (function_exists('mb_check_encoding') && !mb_check_encoding($raw, 'UTF-8') && function_exists('mb_convert_encoding')) {
            $conv = @mb_convert_encoding($raw, 'UTF-8', 'SJIS-win,CP932,SJIS,EUC-JP,UTF-8');
            if ($conv !== false) { $raw = $conv; }
        }

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $raw);
        rewind($fh);

        $header = fgetcsv($fh, null, ',', '"', '');
        if (!$header) {
            $errors[] = 'CSVを読み込めませんでした。';
        } else {
            // ヘッダー名 → 列インデックス
            $map = [];
            foreach ($header as $i => $h) {
                $key = strtolower(trim((string)$h));
                $key = ltrim($key, "\xEF\xBB\xBF"); // 念のため
                $map[$key] = $i;
            }
            foreach (['id', 'category', 'name', 'price'] as $req) {
                if (!isset($map[$req])) { $errors[] = "必須列「$req」がCSVにありません。1行目のヘッダーをご確認ください。"; }
            }
        }

        if (empty($errors)) {
            // カテゴリ名 → キー の逆引き
            $catByName = [];
            foreach ($cats as $k => $c) { $catByName[$c['name']] = $k; }

            $get = function ($row, $col) use ($map) {
                return isset($map[$col]) && isset($row[$map[$col]]) ? trim((string)$row[$map[$col]]) : '';
            };

            $added = 0; $updated = 0; $rowErrors = [];
            $line = 1; // ヘッダーが1行目

            while (($row = fgetcsv($fh, null, ',', '"', '')) !== false) {
                $line++;
                if (count(array_filter($row, function ($v) { return trim((string)$v) !== ''; })) === 0) { continue; } // 空行スキップ

                $id   = $get($row, 'id');
                $catV = $get($row, 'category');
                $name = $get($row, 'name');
                $price = $get($row, 'price');

                // カテゴリ解決（キー or 名前）
                $catKey = '';
                if (isset($cats[$catV])) { $catKey = $catV; }
                elseif (isset($catByName[$catV])) { $catKey = $catByName[$catV]; }

                // バリデーション
                if (!store_valid_id($id)) { $rowErrors[] = "{$line}行目: 商品番号が不正です（半角英数・ハイフン）。"; continue; }
                if ($catKey === '') { $rowErrors[] = "{$line}行目: カテゴリ「{$catV}」が見つかりません。"; continue; }
                if ($name === '') { $rowErrors[] = "{$line}行目: 商品名が空です。"; continue; }
                if ($price === '' || !is_numeric($price)) { $rowErrors[] = "{$line}行目: 価格が数値ではありません。"; continue; }

                $idx = store_find_index($data['products'], $id);

                // 画像: image1〜image20 の非空セルを順番に集める
                $images = [];
                for ($ic = 1; $ic <= CSV_IMG_MAX; $ic++) {
                    $v = $get($row, 'image' . $ic);
                    if ($v !== '') { $images[] = $v; }
                }
                // 旧形式（images 列に | 区切り）にも対応
                if (empty($images)) {
                    $imagesRaw = $get($row, 'images');
                    if ($imagesRaw !== '') {
                        $images = array_values(array_filter(array_map('trim', explode('|', $imagesRaw)), function ($v) { return $v !== ''; }));
                    }
                }
                // 画像列が全て空欄なら 既存は保持 / 新規は空配列
                if (empty($images)) {
                    $images = ($idx >= 0) ? ($data['products'][$idx]['images'] ?? []) : [];
                }

                $ratingRaw  = $get($row, 'rating');
                $reviewsRaw = $get($row, 'reviews');
                $rating  = ($ratingRaw !== '') ? max(0, min(5, (float)$ratingRaw)) : (($idx >= 0) ? (float)($data['products'][$idx]['rating'] ?? 0) : 0);
                $reviews = ($reviewsRaw !== '') ? max(0, (int)$reviewsRaw) : (($idx >= 0) ? (int)($data['products'][$idx]['reviews'] ?? 0) : 0);

                $record = [
                    'id'       => $id,
                    'category' => $catKey,
                    'icon'     => $get($row, 'icon') ?: 'fa-box',
                    'accent'   => $get($row, 'accent') ?: '#DEF13F',
                    'images'   => $images,
                    'name'     => $name,
                    'tag'      => $get($row, 'tag'),
                    'price'    => (int)$price,
                    'badge'    => $get($row, 'badge'),
                    'desc'     => $get($row, 'desc'),
                    'rating'   => $rating,
                    'reviews'  => $reviews,
                ];
                if ($idx >= 0) {
                    $record['attributes'] = $data['products'][$idx]['attributes'] ?? [];
                }

                if ($idx >= 0) { $data['products'][$idx] = $record; $updated++; }
                else { $data['products'][] = $record; $added++; }
            }
            fclose($fh);

            if ($added + $updated > 0) {
                if (store_save($data)) {
                    $report = [
                        'ok'   => "取り込み完了：新規 {$added} 件 / 更新 {$updated} 件。",
                        'rows' => $rowErrors,
                    ];
                } else {
                    $errors[] = store_save_error_message();
                }
            } else {
                $report = ['ok' => '取り込める有効な行がありませんでした。', 'rows' => $rowErrors];
            }
        }
    }

    if (!empty($errors)) { $report = ['err' => $errors]; }
}

require_once __DIR__ . '/_layout.php';
$token = csrf_token();
admin_head('CSV取込');
?>
<div class="adm-head">
  <h2>CSV取込・書き出し</h2>
  <a href="index.php" class="adm-btn"><i class="fa-solid fa-arrow-left"></i> 一覧へ</a>
</div>

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
  <div class="adm-formfoot" style="border:none;padding-top:0;margin-top:0;">
    <a href="import.php?action=sample" class="adm-btn adm-btn-primary"><i class="fa-solid fa-file-csv"></i> サンプルCSVをダウンロード</a>
    <a href="import.php?action=export" class="adm-btn"><i class="fa-solid fa-file-export"></i> 現在の商品をエクスポート</a>
  </div>

  <hr style="border:none;border-top:1px solid var(--border);margin:24px 0;">

  <h3 style="font-size:15px;margin-bottom:14px;color:var(--heading);">2. CSVをアップロードして取り込み</h3>
  <p class="adm-note">
    商品番号（id）が既存と一致する行は<strong>更新</strong>、一致しない行は<strong>新規追加</strong>されます。<br>
    1行目はヘッダー：<code>id, category, name, tag, price, badge, icon, accent, desc, rating, reviews, image1 … image20</code>。<br>
    <code>rating</code> は 0〜5（0.5 刻み可）、<code>reviews</code> はレビュー件数。空欄の場合は既存値を維持します。<br>
    <code>category</code> はカテゴリのキー（<?= htmlspecialchars(implode(' / ', array_keys($cats))) ?>）または名称。<br>
    <strong>画像は <code>image1</code>〜<code>image20</code> の列</strong>に1枚ずつ。<code>image1</code> がメイン画像、<code>image2</code> 以降は入っていれば表示・空欄なら非表示。画像列がすべて空欄なら既存画像を維持します。<br>
    画像ファイルは「画像管理」または「商品編集」からアップロードし、表示されたパスを貼り付けてください。まずは「サンプルCSVをダウンロード」で記入例をご確認ください。
  </p>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
    <div class="adm-field">
      <label>CSVファイル</label>
      <input type="file" name="csvfile" accept=".csv,text/csv" required>
      <small>文字コードは UTF-8 / Shift_JIS（Excel）どちらも可。</small>
    </div>
    <div class="adm-formfoot">
      <button type="submit" class="adm-btn adm-btn-primary"><i class="fa-solid fa-upload"></i> 取り込む</button>
    </div>
  </form>
</div>
<?php admin_foot(); ?>
