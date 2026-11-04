<?php
/**
 * 商品一括取込（Excel .xlsx / 従来 CSV）共通ロジック
 */
require_once __DIR__ . '/store.php';

define('IMPORT_IMG_MAX', 20);

function import_column_names() {
    $cols = ['id', 'category', 'name', 'tag', 'price', 'price_max', 'skus', 'sku_groups', 'badge', 'icon', 'accent', 'desc', 'rating', 'reviews'];
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
    $names = $xlsx->sheetNames();
    $skuIndex = array_search('SKU', $names, true);
    $productIndex = array_search('商品', $names, true);
    if ($productIndex === false) {
        foreach ($names as $index => $name) {
            if ($index !== $skuIndex) { $productIndex = $index; break; }
        }
    }
    $rows = $productIndex !== false ? $xlsx->rows($productIndex) : [['id', 'category', 'name', 'price']];
    if (empty($rows)) {
        return ['error' => '商品シートのヘッダーがありません。テンプレートの1行目を残してください。'];
    }
    $skuRows = $skuIndex !== false ? $xlsx->rows($skuIndex) : null;
    return ['rows' => $rows, 'sku_rows' => $skuRows, 'from_csv' => false];
}

function import_sku_sheet_header() {
    return ['CODE', 'タイトル1', '内容1', '価格', 'タイトル2', '内容2', 'タイトル3', '内容3', 'SKU番号', '表示名'];
}

/** SKUシートはCODE単位で検証し、不正な組み合わせを部分保存しない。 */
function import_parse_sku_sheet_rows(array $rows) {
    if (!$rows) { return ['error' => 'SKUシートのヘッダーがありません。テンプレートの1行目を残してください。']; }
    $header = array_map(function ($cell) { return trim((string)$cell); }, $rows[0]);
    $header[0] = isset($header[0]) ? ltrim($header[0], "\xEF\xBB\xBF") : '';
    if (array_slice($header, 0, 9) !== array_slice(import_sku_sheet_header(), 0, 9)
        || (isset($header[9]) && $header[9] !== '' && $header[9] !== '表示名')) {
        return ['error' => 'SKUシートの1行目は CODE, タイトル1, 内容1, 価格, タイトル2, 内容2, タイトル3, 内容3, SKU番号 にしてください。'];
    }
    $raw = [];
    $invalid = [];
    $errors = [];
    foreach (array_slice($rows, 1) as $offset => $row) {
        if (!is_array($row) || !array_filter($row, function ($v) { return trim((string)$v) !== ''; })) { continue; }
        $line = $offset + 2;
        $fields = array_map(function ($v) { return trim((string)$v); }, array_pad(array_slice($row, 0, 10), 10, ''));
        $code = $fields[0];
        if (!store_valid_id($code)) {
            $errors[] = "SKUシート {$line}行目: CODE が不正です。";
            continue;
        }
        $pairs = [];
        $gap = false;
        $outOfOrder = false;
        foreach ([[1, 2], [4, 5], [6, 7]] as $indices) {
            $title = $fields[$indices[0]];
            $content = $fields[$indices[1]];
            if ($title === '' && $content === '') { $gap = true; continue; }
            if ($gap) { $outOfOrder = true; }
            $pairs[] = [$title, $content];
        }
        if ($outOfOrder || !$pairs || !store_valid_price($fields[3]) || ($fields[8] !== '' && !store_valid_id($fields[8]))) {
            $invalid[$code] = true;
            $errors[] = "SKUシート {$line}行目（{$code}）: 仕様または価格が不正です。";
            continue;
        }
        $raw[$code][] = ['id' => $fields[8], 'pairs' => $pairs, 'price' => $fields[3], 'name' => $fields[9], 'line' => $line];
        if (count($raw[$code]) > 100) {
            $invalid[$code] = true;
            $errors[] = "SKUシート {$line}行目（{$code}）: 1商品100SKUまでです。";
        }
    }
    $byCode = [];
    foreach ($raw as $code => $items) {
        if (isset($invalid[$code])) { continue; }
        $titles = array_column($items[0]['pairs'], 0);
        $grouped = $titles[0] !== '';
        $groups = [];
        $skus = [];
        $usedIds = [];
        $reservedIds = array_map('strtolower', array_filter(array_column($items, 'id'), function ($id) { return $id !== ''; }));
        $bad = '';
        foreach ($items as $item) {
            $itemTitles = array_column($item['pairs'], 0);
            if ($itemTitles !== $titles || ($grouped && in_array('', $titles, true)) || (!$grouped && (count($titles) !== 1 || $item['pairs'][0][1] === ''))) {
                $bad = "SKUシート {$item['line']}行目（{$code}）: タイトルの順序または内容が一致しません。";
                break;
            }
            foreach ($item['pairs'] as $i => $pair) {
                if ($pair[1] === '') { $bad = "SKUシート {$item['line']}行目（{$code}）: 内容が空です。"; break; }
                if ($grouped) {
                    if (!isset($groups[$i])) { $groups[$i] = ['name' => $pair[0], 'options' => []]; }
                    if (!in_array($pair[1], $groups[$i]['options'], true)) { $groups[$i]['options'][] = $pair[1]; }
                }
            }
            if ($bad !== '') { break; }
            $id = $item['id'];
            if ($id === '') {
                $serial = 1;
                do { $id = 'SKU-' . $serial++; } while (isset($usedIds[strtolower($id)]) || in_array(strtolower($id), $reservedIds, true));
            }
            if (isset($usedIds[strtolower($id)])) {
                $bad = "SKUシート {$item['line']}行目（{$code}）: SKU番号が重複しています。";
                break;
            }
            $usedIds[strtolower($id)] = true;
            $sku = ['id' => $id, 'name' => $item['name'] !== '' ? $item['name'] : ($grouped ? '' : $item['pairs'][0][1]), 'price' => $item['price']];
            if ($grouped) { $sku['options'] = array_column($item['pairs'], 1); }
            $skus[] = $sku;
        }
        $validationError = '';
        $groups = $bad === '' ? store_parse_sku_groups($groups, $validationError) : false;
        $skus = $groups !== false ? store_parse_skus($skus, $validationError, $groups) : false;
        if ($groups === false || $skus === false || !$skus) {
            $invalid[$code] = true;
            $errors[] = $bad !== '' ? $bad : "SKUシート（{$code}）: 仕様・SKU番号・価格が不正です（{$validationError}）。";
            continue;
        }
        $byCode[$code] = ['sku_groups' => $groups, 'skus' => $skus];
    }
    return ['by_code' => $byCode, 'invalid' => $invalid, 'rowErrors' => $errors];
}

/** 取込み実行 */
function import_apply_rows(array $dataRows, array $map, array $data, array $cats, $fromCsv = false, array $skuOverrides = [], array $invalidSkuCodes = []) {
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
    $downloadCache = [];
    $downloadDeadline = microtime(true) + 20;
    $downloadCount = 0;

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
        if (isset($invalidSkuCodes[$id])) {
            $rowErrors[] = "{$line}行目（{$id}）: SKUシートにエラーがあるため商品を変更していません。";
            continue;
        }
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
        $idx = store_find_index($data['products'], $id);
        // 省略列は既存値を保つ。両列の最終状態を検証してから画像を処理する。
        $existing = $idx >= 0 ? $data['products'][$idx] : [];
        $groupsInput = array_key_exists('sku_groups', $existing) ? $existing['sku_groups'] : [];
        if (isset($skuOverrides[$id])) {
            $groupsInput = $skuOverrides[$id]['sku_groups'];
        } elseif (isset($map['sku_groups'])) {
            $groupsRaw = $get($row, 'sku_groups');
            $groupsInput = $groupsRaw === '' ? [] : store_sku_decode_json_rows($groupsRaw);
        }
        $skuError = '';
        $groups = store_parse_sku_groups($groupsInput, $skuError);
        if ($groups === false) {
            $rowErrors[] = "{$line}行目: SKU グループ（sku_groups）の JSON または値が不正です（" . ($skuError ?: 'format') . '）。';
            continue;
        }
        $skusInput = array_key_exists('skus', $existing) ? $existing['skus'] : [];
        if (isset($map['skus'])) {
            $skusRaw = $get($row, 'skus');
            $skusInput = $skusRaw === '' ? [] : store_sku_decode_json_rows($skusRaw);
        }
        if (isset($skuOverrides[$id])) {
            $groups = $skuOverrides[$id]['sku_groups'];
            $skusInput = $skuOverrides[$id]['skus'];
        }
        $skus = store_parse_skus($skusInput, $skuError, $groups);
        if ($skus === false || ($groups && !$skus)) {
            $rowErrors[] = "{$line}行目: SKU（skus）の JSON または値が不正です（" . ($skus === false ? ($skuError ?: 'format') : 'group_skus') . '）。';
            continue;
        }
        // SKU があれば price / price_max のセルは参照せず、各 SKU の価格から算出する。
        if ($skus) {
            $skuPrices = store_product_prices(['skus' => $skus, 'sku_groups' => $groups]);
            $price = $skuPrices['price'];
            $priceMax = $skuPrices['price_max'];
        } else {
            if (!store_valid_price($price)) {
                $rowErrors[] = "{$line}行目: 最低価格（price）は0〜2147483647の半角整数で指定してください。";
                continue;
            }
            // 旧形式の列省略は既存値を維持し、列がある場合の空欄は最高価格を解除する。
            $priceMax = ($idx >= 0) ? ($data['products'][$idx]['price_max'] ?? null) : null;
            if (isset($map['price_max'])) {
                $priceMaxRaw = $get($row, 'price_max');
                if ($priceMaxRaw !== '' && !store_valid_price($priceMaxRaw)) {
                    $rowErrors[] = "{$line}行目: 最高価格（price_max）は0〜2147483647の半角整数で指定してください。";
                    continue;
                }
                $priceMax = ($priceMaxRaw === '') ? null : (int)$priceMaxRaw;
            }
            if ($priceMax !== null && (!store_valid_price($priceMax) || (int)$priceMax < (int)$price)) {
                $rowErrors[] = "{$line}行目: 最高価格（price_max）は最低価格（price）以上にしてください。";
                continue;
            }
        }

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
        } else {
            $resolvedImages = [];
            $imageError = null;
            foreach ($images as $imageIndex => $source) {
                if (preg_match('#^https?://#i', $source)) {
                    if (!isset($downloadCache[$source])) {
                        if ($downloadCount >= 50 || microtime(true) >= $downloadDeadline) {
                            $imageError = '外部画像の処理上限（50枚／20秒）に達しました。行数を減らして再度取り込んでください。';
                            break;
                        }
                        require_once __DIR__ . '/remote_image.php';
                        $downloadCount++;
                        $downloadError = null;
                        $local = remote_image_download($source, $downloadError, $downloadDeadline);
                        $downloadCache[$source] = ['path' => $local, 'error' => $downloadError];
                    }
                    $download = $downloadCache[$source];
                    if (!$download['path']) {
                        $imageError = '画像' . ($imageIndex + 1) . ': ' . $download['error'];
                        break;
                    }
                    $resolvedImages[] = $download['path'];
                } elseif (store_validate_product_image_path($source)) {
                    $resolvedImages[] = $source;
                } else {
                    $imageError = '画像' . ($imageIndex + 1) . ': 有効なローカル画像パスまたは公開 HTTP(S) 画像URLを指定してください。';
                    break;
                }
            }
            if ($imageError !== null) {
                $rowErrors[] = "{$line}行目: {$imageError} この行の商品は変更していません。";
                continue;
            }
            $images = $resolvedImages;
        }
        $images = store_filter_product_image_paths($images);

        $ratingRaw  = $get($row, 'rating');
        $reviewsRaw = $get($row, 'reviews');
        $rating  = ($ratingRaw !== '') ? max(0, min(5, (float)$ratingRaw)) : (($idx >= 0) ? (float)($data['products'][$idx]['rating'] ?? 0) : 0);
        $reviews = ($reviewsRaw !== '') ? max(0, (int)$reviewsRaw) : (($idx >= 0) ? (int)($data['products'][$idx]['reviews'] ?? 0) : 0);

        $record = [
            'id'       => $id,
            'category' => $catKey,
            'icon'     => shop_sanitize_icon($get($row, 'icon') ?: 'fa-box'),
            'accent'   => shop_sanitize_accent($get($row, 'accent') ?: '#DEF13F'),
            'images'   => $images,
            'name'     => $name,
            'tag'      => $get($row, 'tag'),
            'price'    => (int)$price,
            'price_max' => $priceMax,
            'skus'     => $skus,
            'sku_groups' => $groups,
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

/** SKUシートだけで既存商品の価格・仕様を更新する。商品情報はそのまま残す。 */
function import_apply_sku_only(array $data, array $overrides, array $skipCodes = []) {
    $updated = 0;
    $errors = [];
    foreach ($overrides as $code => $skuData) {
        if (isset($skipCodes[$code])) { continue; }
        $index = store_find_index($data['products'], $code);
        if ($index < 0) {
            $errors[] = "SKUシート（{$code}）: 商品CODEが見つかりません。";
            continue;
        }
        $prices = store_product_prices($skuData);
        $data['products'][$index]['sku_groups'] = $skuData['sku_groups'];
        $data['products'][$index]['skus'] = $skuData['skus'];
        $data['products'][$index]['price'] = $prices['price'];
        $data['products'][$index]['price_max'] = $prices['price_max'];
        $updated++;
    }
    return ['data' => $data, 'updated' => $updated, 'rowErrors' => $errors];
}

function import_apply_workbook(array $parsed, array $data, array $cats) {
    $sheet = import_parse_sheet_rows($parsed['rows']);
    $skuSheet = isset($parsed['sku_rows']) && $parsed['sku_rows'] !== null
        ? import_parse_sku_sheet_rows($parsed['sku_rows'])
        : ['by_code' => [], 'invalid' => [], 'rowErrors' => []];
    if (!empty($sheet['error']) || !empty($skuSheet['error'])) {
        return ['error' => !empty($sheet['error']) ? $sheet['error'] : $skuSheet['error']];
    }
    $mainCodes = [];
    foreach ($sheet['rows'] as $row) {
        if (!is_array($row)) { continue; }
        $code = trim((string)($row[$sheet['map']['id']] ?? ''));
        if ($code !== '') {
            if (isset($mainCodes[$code], $skuSheet['by_code'][$code]) && !isset($skuSheet['invalid'][$code])) {
                $skuSheet['invalid'][$code] = true;
                $skuSheet['rowErrors'][] = "商品シート（{$code}）: CODEが重複しています。商品シートは1商品1行にしてください。";
            }
            $mainCodes[$code] = true;
        }
    }
    $result = import_apply_rows($sheet['rows'], $sheet['map'], $data, $cats, !empty($parsed['from_csv']), $skuSheet['by_code'], $skuSheet['invalid']);
    $skipCodes = $skuSheet['invalid'] + $mainCodes;
    $skuOnly = import_apply_sku_only($result['data'], $skuSheet['by_code'], $skipCodes);
    $result['data'] = $skuOnly['data'];
    $result['updated'] += $skuOnly['updated'];
    $result['rowErrors'] = array_merge($result['rowErrors'], $skuSheet['rowErrors'], $skuOnly['rowErrors']);
    return $result;
}

function import_build_sku_sheet_rows(array $products) {
    $rows = [import_sku_sheet_header()];
    foreach ($products as $product) {
        $groups = store_product_sku_groups($product);
        foreach (store_product_skus($product) as $sku) {
            $fields = array_fill(0, 6, '');
            if ($groups) {
                foreach ($groups as $index => $group) {
                    $fields[$index * 2] = $group['name'];
                    $fields[$index * 2 + 1] = $sku['options'][$index];
                }
            } else {
                $fields[1] = $sku['name'];
            }
            $rows[] = array_map('strval', [
                $product['id'], $fields[0], $fields[1], $sku['price'],
                $fields[2], $fields[3], $fields[4], $fields[5], $sku['id'], $sku['name'],
            ]);
        }
    }
    return $rows;
}

/** エクスポート用の行（ヘッダー含む） */
function import_build_export_rows(array $products, $escape = true) {
    $cell = $escape ? 'import_escape_cell' : 'strval';
    $header = import_column_names();
    $rows = [$header];
    foreach ($products as $p) {
        $imgs = array_slice($p['images'] ?? [], 0, IMPORT_IMG_MAX);
        $rows[] = array_merge([
            $cell($p['id']),
            $cell($p['category']),
            $cell($p['name']),
            $cell($p['tag'] ?? ''),
            $cell($p['price']),
            $cell($p['price_max'] ?? ''),
            $cell(json_encode(store_product_skus($p), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            $cell(json_encode(store_product_sku_groups($p), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            $cell($p['badge'] ?? ''),
            $cell($p['icon'] ?? 'fa-box'),
            $cell($p['accent'] ?? '#DEF13F'),
            $cell($p['desc'] ?? ''),
            $cell($p['rating'] ?? 0),
            $cell($p['reviews'] ?? 0),
        ], array_map($cell, array_pad($imgs, IMPORT_IMG_MAX, '')));
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
        ['SAMPLE-001', $c1, 'サンプル商品（単一価格・画像1枚）', 'キャッチコピー例', '4980', '', '[]', '[]', '人気No.1', 'fa-wand-magic-sparkles', '#DEF13F', '商品の詳細説明をここに記入します。', '4.5', '128'],
        array_pad(['images/products/sample/main.jpg'], IMPORT_IMG_MAX, '')
    );
    $rows[] = array_merge(
        ['SAMPLE-002', $c2, 'サンプル商品（価格帯・複数画像）', '静音・大容量', '12800', '19800', '[]', '[]', 'NEW', 'fa-house-chimney', '#93c5fd', 'image1 がメイン画像、image2 以降は任意です。', '0', '0'],
        array_pad(['images/products/sample/1.jpg', 'images/products/sample/2.jpg', 'images/products/sample/3.jpg'], IMPORT_IMG_MAX, '')
    );
    $groups = [
        ['name' => 'カラー', 'options' => ['RED', 'BLUE']],
        ['name' => 'サイズ', 'options' => ['S', 'M']],
    ];
    $skus = [
        ['id' => 'RED-S', 'name' => 'RED / S', 'price' => 1000, 'options' => ['RED', 'S']],
        ['id' => 'RED-M', 'name' => 'RED / M', 'price' => 1200, 'options' => ['RED', 'M']],
        ['id' => 'BLUE-S', 'name' => 'BLUE / S', 'price' => 1400, 'options' => ['BLUE', 'S']],
        ['id' => 'BLUE-M', 'name' => 'BLUE / M', 'price' => 1600, 'options' => ['BLUE', 'M']],
    ];
    $rows[] = array_merge(
        ['SAMPLE-003', $c1, 'サンプル商品（カラー・サイズ別価格）', 'カラー・サイズを選択', '', '', json_encode($skus, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), json_encode($groups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), '', 'fa-box', '#DEF13F', 'SKUごとに価格が異なります。', '0', '0'],
        array_fill(0, IMPORT_IMG_MAX, '')
    );
    return $rows;
}

function import_xlsx_product_rows(array $rows) {
    $header = $rows[0] ?? [];
    $omit = [];
    foreach (['skus', 'sku_groups'] as $column) {
        $index = array_search($column, $header, true);
        if ($index !== false) { $omit[] = $index; }
    }
    return array_map(function ($row) use ($omit) {
        return array_values(array_diff_key($row, array_flip($omit)));
    }, $rows);
}

function import_sample_xlsx_product_rows(array $cats) {
    return import_xlsx_product_rows(import_sample_rows($cats));
}

function import_sample_grouped_product(array $cats) {
    $rows = import_sample_rows($cats);
    $row = $rows[3];
    return ['id' => $row[0], 'sku_groups' => json_decode($row[7], true), 'skus' => json_decode($row[6], true)];
}

/** 文字列をそのまま保存し、日付・数値・書式タグ・数式への自動変換を防ぐ。 */
function import_xlsx_text_rows(array $rows) {
    return array_map(function ($row) {
        return array_map(function ($value) {
            return is_int($value) || is_float($value) ? $value : "\0" . (string)$value;
        }, $row);
    }, $rows);
}

function import_create_xlsx(array $rows, ?array $skuRows = null) {
    require_once __DIR__ . '/SimpleXLSXGen.php';
    $workbook = \Shuchkin\SimpleXLSXGen::fromArray(import_xlsx_text_rows($rows), '商品')->freezePanes('A2');
    if ($skuRows !== null) {
        $workbook->addSheet(import_xlsx_text_rows($skuRows), 'SKU');
        $workbook->setColWidth('A', 20)->setColWidth('B:C', 17)->setColWidth('D', 15)
            ->setColWidth('E:H', 17)->setColWidth('I:J', 22)->freezePanes('A2');
    }
    return $workbook;
}

function import_send_xlsx($filename, array $rows, ?array $skuRows = null) {
    import_create_xlsx($rows, $skuRows)->downloadAs($filename);
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
