<?php
/**
 * 商品一括取込（Excel .xlsx / 従来 CSV）共通ロジック
 */
require_once __DIR__ . '/store.php';

define('IMPORT_IMG_MAX', 20);

function import_column_names() {
    $cols = ['id', 'category', 'name', 'tag', 'price', 'badge', 'icon', 'accent', 'desc', 'rating', 'reviews'];
    for ($i = 1; $i <= IMPORT_IMG_MAX; $i++) {
        $cols[] = 'image' . $i;
    }
    return $cols;
}

/** セル文字列（CSV のみレガシー文字コード変換） */
function import_cell_text($value, $fromCsv = false) {
    $s = trim((string)$value);
    if ($s === '') {
        return '';
    }
    if ($fromCsv) {
        return store_utf8_normalize($s);
    }
    if (store_utf8_suspect_legacy_encoding($s)) {
        return store_utf8_normalize($s);
    }
    return $s;
}

/** 2次元配列（1行目=ヘッダー）をパースしてヘッダーマップとデータ行を返す */
function import_parse_sheet_rows(array $rows) {
    while (!empty($rows) && count(array_filter($rows[0], function ($v) {
        return trim((string)$v) !== '';
    })) === 0) {
        array_shift($rows);
    }
    if (empty($rows)) {
        return ['error' => 'ファイルにデータがありません。'];
    }
    $header = array_map(function ($h) {
        $key = strtolower(trim((string)$h));
        return ltrim($key, "\xEF\xBB\xBF");
    }, $rows[0]);
    $map = [];
    foreach ($header as $i => $h) {
        if ($h !== '') {
            $map[$h] = $i;
        }
    }
    foreach (['id', 'category', 'name', 'price'] as $req) {
        if (!isset($map[$req])) {
            return ['error' => "必須列「{$req}」がありません。1行目のヘッダーをご確認ください。"];
        }
    }
    $dataRows = array_slice($rows, 1);
    return ['map' => $map, 'rows' => $dataRows];
}

/** CSV ファイル → 行配列 */
function import_read_csv($tmpPath) {
    $raw = file_get_contents($tmpPath);
    if ($raw === false) {
        return ['error' => 'CSVを読み込めませんでした。'];
    }
    if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
        $raw = substr($raw, 3);
    }
    if (!store_utf8_conversion_ok($raw)) {
        if (function_exists('iconv')) {
            foreach (['EUC-JP', 'CP932', 'SJIS', 'SJIS-win'] as $from) {
                $conv = @iconv($from, 'UTF-8//IGNORE', $raw);
                if ($conv !== false && store_utf8_conversion_ok($conv)) {
                    $raw = $conv;
                    break;
                }
            }
        }
        if (!store_utf8_conversion_ok($raw) && function_exists('mb_convert_encoding')) {
            $conv = @mb_convert_encoding($raw, 'UTF-8', 'EUC-JP,CP932,SJIS-win,SJIS,UTF-8');
            if ($conv !== false) {
                $raw = $conv;
            }
        }
    }
    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $raw);
    rewind($fh);
    $rows = [];
    while (($row = fgetcsv($fh, null, ',', '"', '')) !== false) {
        $rows[] = $row;
    }
    fclose($fh);
    if (empty($rows)) {
        return ['error' => 'CSVを読み込めませんでした。'];
    }
    return ['rows' => $rows, 'from_csv' => true];
}

/** XLSX ファイル → 行配列 */
function import_read_xlsx($tmpPath) {
    if (!class_exists('ZipArchive')) {
        return ['error' => 'PHP の zip 拡張が必要です。宝塔 → PHP → 安装扩展 → zip を有効にしてください。'];
    }
    require_once __DIR__ . '/SimpleXLSX.php';
    $xlsx = \Shuchkin\SimpleXLSX::parse($tmpPath);
    if (!$xlsx) {
        return ['error' => 'Excel を読み込めませんでした: ' . \Shuchkin\SimpleXLSX::parseError()];
    }
    $rows = $xlsx->rows();
    if (empty($rows)) {
        return ['error' => 'Excel にシートデータがありません。'];
    }
    return ['rows' => $rows, 'from_csv' => false];
}

/** 取込み実行 */
function import_apply_rows(array $dataRows, array $map, array $data, array $cats, $fromCsv = false) {
    $catByName = [];
    foreach ($cats as $k => $c) {
        $catByName[$c['name']] = $k;
    }
    $get = function ($row, $col) use ($map, $fromCsv) {
        if (!isset($map[$col]) || !isset($row[$map[$col]])) {
            return '';
        }
        return import_cell_text($row[$map[$col]], $fromCsv);
    };

    $added = 0;
    $updated = 0;
    $rowErrors = [];
    $line = 1;

    foreach ($dataRows as $row) {
        $line++;
        if (!is_array($row)) {
            continue;
        }
        if (count(array_filter($row, function ($v) {
            return trim((string)$v) !== '';
        })) === 0) {
            continue;
        }

        $id    = $get($row, 'id');
        $catV  = $get($row, 'category');
        $name  = $get($row, 'name');
        $price = $get($row, 'price');

        $catKey = '';
        if (isset($cats[$catV])) {
            $catKey = $catV;
        } elseif (isset($catByName[$catV])) {
            $catKey = $catByName[$catV];
        }

        if (!store_valid_id($id)) {
            $rowErrors[] = "{$line}行目: 商品番号が不正です（半角英数・ハイフン）。";
            continue;
        }
        if ($catKey === '') {
            $rowErrors[] = "{$line}行目: カテゴリ「{$catV}」が見つかりません。";
            continue;
        }
        if ($name === '') {
            $rowErrors[] = "{$line}行目: 商品名が空です。";
            continue;
        }
        if ($price === '' || !is_numeric($price)) {
            $rowErrors[] = "{$line}行目: 価格が数値ではありません。";
            continue;
        }

        $idx = store_find_index($data['products'], $id);

        $images = [];
        for ($ic = 1; $ic <= IMPORT_IMG_MAX; $ic++) {
            $v = $get($row, 'image' . $ic);
            if ($v !== '') {
                $images[] = $v;
            }
        }
        if (empty($images)) {
            $imagesRaw = $get($row, 'images');
            if ($imagesRaw !== '') {
                $images = array_values(array_filter(array_map('trim', explode('|', $imagesRaw)), function ($v) {
                    return $v !== '';
                }));
            }
        }
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

        if ($idx >= 0) {
            $data['products'][$idx] = $record;
            $updated++;
        } else {
            $data['products'][] = $record;
            $added++;
        }
    }

    return [
        'data'      => $data,
        'added'     => $added,
        'updated'   => $updated,
        'rowErrors' => $rowErrors,
    ];
}

/** エクスポート用の行（ヘッダー含む） */
function import_build_export_rows(array $products) {
    $header = import_column_names();
    $rows = [$header];
    foreach ($products as $p) {
        $imgs = array_slice($p['images'] ?? [], 0, IMPORT_IMG_MAX);
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
        ], array_pad($imgs, IMPORT_IMG_MAX, ''));
    }
    return $rows;
}

function import_sample_rows(array $cats) {
    $catKeys = array_keys($cats);
    $c1 = $catKeys[0] ?? 'beauty';
    $c2 = $catKeys[1] ?? $c1;
    $rows = import_build_export_rows([]);
    $rows = [$rows[0]];
    $rows[] = array_merge(
        ['SAMPLE-001', $c1, 'サンプル商品（画像1枚）', 'キャッチコピー例', '4980', '人気No.1', 'fa-wand-magic-sparkles', '#DEF13F', '商品の詳細説明をここに記入します。', '4.5', '128'],
        array_pad(['images/products/sample/main.jpg'], IMPORT_IMG_MAX, '')
    );
    $rows[] = array_merge(
        ['SAMPLE-002', $c2, 'サンプル商品（複数画像）', '静音・大容量', '12800', 'NEW', 'fa-house-chimney', '#93c5fd', 'image1 がメイン画像、image2 以降は任意です。', '0', '0'],
        array_pad(['images/products/sample/1.jpg', 'images/products/sample/2.jpg', 'images/products/sample/3.jpg'], IMPORT_IMG_MAX, '')
    );
    return $rows;
}

function import_send_xlsx($filename, array $rows) {
    require_once __DIR__ . '/SimpleXLSXGen.php';
    \Shuchkin\SimpleXLSXGen::fromArray($rows, '商品')->downloadAs($filename);
    exit;
}

function import_send_csv($filename, array $rows) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    foreach ($rows as $r) {
        fputcsv($out, $r, ',', '"', '');
    }
    fclose($out);
    exit;
}

function import_detect_format($filename) {
    $ext = strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION));
    if ($ext === 'xlsx') {
        return 'xlsx';
    }
    if ($ext === 'csv') {
        return 'csv';
    }
    return '';
}
