<?php
// 保存先を隔離し、業務データを読み書きしない。
$root = sys_get_temp_dir() . '/shop-skus-' . bin2hex(random_bytes(8));
define('SHOP_BASE', $root);
require_once dirname(__DIR__) . '/lib/product_import.php';

function verify_sku($ok, $label) {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS: $label\n";
}
function expect_sku_error($rows, $code) {
    $error = '';
    verify_sku(store_parse_skus($rows, $error) === false && $error === $code, 'reject ' . $code);
}

$valid = [
    ['id' => 'A', 'name' => '白 / S', 'price' => '1000'],
    ['id' => 'b', 'name' => '黒 / M', 'price' => 2000],
];
$error = '';
$parsed = store_parse_skus([[], ['id' => '', 'name' => ' ', 'price' => ''], $valid[0], $valid[1]], $error);
verify_sku($error === '' && $parsed === [
    ['id' => 'A', 'name' => '白 / S', 'price' => 1000],
    ['id' => 'b', 'name' => '黒 / M', 'price' => 2000],
], 'normalize and ignore blank rows');
verify_sku(store_parse_skus([2 => $valid[0], 7 => $valid[1]], $error) === $parsed, 'sparse form indices normalize');
expect_sku_error(['sku' => $valid[0]], 'format');
expect_sku_error('invalid', 'format');
expect_sku_error([['id' => ['A'], 'name' => 'X', 'price' => 2]], 'format');
expect_sku_error([['id' => 'A', 'name' => new stdClass(), 'price' => 2]], 'format');
expect_sku_error([['id' => 'bad id', 'name' => 'X', 'price' => 2]], 'id');
expect_sku_error([['id' => 'A', 'name' => ' ', 'price' => 2]], 'name');
expect_sku_error([['id' => 'A', 'name' => str_repeat('あ', 201), 'price' => 2]], 'name');
expect_sku_error([['id' => 'A', 'name' => str_repeat('a', 201), 'price' => 2]], 'name');
verify_sku(count(store_parse_skus([['id' => 'A', 'name' => str_repeat('😀', 200), 'price' => 2]], $error)) === 1, '200 emoji name accepted');
expect_sku_error([['id' => 'A', 'name' => 'X', 'price' => '2147483648']], 'price');
expect_sku_error([$valid[0], ['id' => 'a', 'name' => 'X', 'price' => 3]], 'duplicate');
$hundred = [];
for ($i = 0; $i < 100; $i++) {
    $hundred[] = ['id' => 'sku-' . $i, 'name' => str_repeat('白', 200), 'price' => $i];
}
verify_sku(count(store_parse_skus($hundred, $error)) === 100, '100 long Unicode names accepted');
expect_sku_error(array_merge($hundred, [['id' => 'extra', 'name' => 'X', 'price' => 1]]), 'limit');
verify_sku(store_product_skus(['skus' => 'invalid']) === [], 'invalid SKU is not shown');
verify_sku(store_product_prices(['skus' => $valid, 'price' => 9999, 'price_max' => 99999]) === ['price' => 1000, 'price_max' => 2000], 'SKU prices override stale parent prices');
verify_sku(store_product_prices(['skus' => [$valid[0]]]) === ['price' => 1000, 'price_max' => null], 'single SKU has no range');
verify_sku(store_product_prices(['skus' => [$valid[0], ['id' => 'B', 'name' => 'B', 'price' => 1000]]]) === ['price' => 1000, 'price_max' => null], 'equal SKU prices have no range');
verify_sku(store_product_prices(['price' => 1000, 'price_max' => 2000]) === ['price' => 1000, 'price_max' => 2000], 'legacy price range unchanged');
verify_sku(store_price_text(['skus' => $valid, 'price' => 9]) === '¥1,000 ～ ¥2,000', 'admin price display uses SKU prices');
$unsafeName = '\"><script>alert(1)</script>';
verify_sku(store_parse_skus([['id' => 'safe', 'name' => $unsafeName, 'price' => 1]], $error)[0]['name'] === $unsafeName
    && strpos(shop_e($unsafeName), '<script>') === false, 'SKU names stay data and escape in HTML');

$cats = ['test' => ['name' => 'Test']];
$original = ['id' => 'SKU-1', 'category' => 'test', 'name' => 'Sample', 'price' => 9, 'price_max' => 5000, 'skus' => $valid, 'images' => []];
$data = ['categories' => $cats, 'products' => [$original]];
$export = import_build_export_rows([$original]);
$skuColumn = array_search('skus', $export[0], true);
verify_sku($skuColumn !== false && json_decode($export[1][$skuColumn], true) === $parsed, 'export includes SKU JSON');
$sheet = import_parse_sheet_rows($export);
$imported = import_apply_rows($sheet['rows'], $sheet['map'], ['categories' => $cats, 'products' => []], $cats);
verify_sku(!$imported['rowErrors'] && $imported['data']['products'][0]['price'] === 1000 && $imported['data']['products'][0]['price_max'] === 2000, 'export/import computes prices');
$legacy = import_parse_sheet_rows([['id', 'category', 'name', 'price'], ['SKU-1', 'test', 'Changed', '']]);
$result = import_apply_rows($legacy['rows'], $legacy['map'], $data, $cats);
verify_sku(!$result['rowErrors'] && $result['data']['products'][0]['skus'] === $parsed && $result['data']['products'][0]['price'] === 1000, 'legacy import retains existing SKUs with empty price');
foreach ([' ', '[]'] as $clear) {
    $sheet = import_parse_sheet_rows([['id', 'category', 'name', 'price', 'skus'], ['SKU-1', 'test', 'Changed', '1200', $clear]]);
    $result = import_apply_rows($sheet['rows'], $sheet['map'], $data, $cats);
    verify_sku(!$result['rowErrors'] && $result['data']['products'][0]['skus'] === [] && $result['data']['products'][0]['price'] === 1200, 'explicit clear SKU ' . $clear);
}
foreach (['{ "0": {"id":"A","name":"A","price":1} }', '[{"id":"A","name":"X","price":-1}]'] as $bad) {
    $sheet = import_parse_sheet_rows([['id', 'category', 'name', 'price', 'skus'], ['SKU-1', 'test', 'Changed', '', $bad]]);
    $result = import_apply_rows($sheet['rows'], $sheet['map'], $data, $cats);
    verify_sku(count($result['rowErrors']) === 1 && $result['data']['products'][0] === $original, 'invalid import leaves original untouched');
}
$sheet = import_parse_sheet_rows([['id', 'category', 'name', 'price', 'price_max', 'skus'], ['NEW', 'test', 'New', '', 'bad', json_encode($valid, JSON_UNESCAPED_UNICODE)]]);
$result = import_apply_rows($sheet['rows'], $sheet['map'], ['categories' => $cats, 'products' => []], $cats);
verify_sku(!$result['rowErrors'] && $result['data']['products'][0]['price_max'] === 2000, 'new SKU import permits blank price and ignores price_max cell');

mkdir($root . '/data', 0775, true);
try {
    verify_sku(store_save_json(['categories' => $cats, 'products' => [$original]]), 'JSON save');
    $saved = json_decode(file_get_contents(STORE_FILE), true)['products'][0];
    verify_sku($saved['price'] === 1000 && $saved['price_max'] === 2000 && $saved['skus'] === $parsed, 'JSON writes normalized SKU-derived prices');
    $loaded = store_load_json()['products'][0];
    verify_sku($loaded['skus'] === $parsed && $loaded['price'] === 1000, 'JSON reads normalized SKUs');
    $invalid = $original;
    $invalid['skus'] = [['id' => 'A', 'name' => 'X', 'price' => ['invalid']]];
    verify_sku(!store_save_json(['categories' => $cats, 'products' => [$invalid]]) && strpos(store_last_error(), 'format') !== false, 'JSON rejects invalid SKU without overwriting data');
    verify_sku(!store_save_mysql(['categories' => $cats, 'products' => [$invalid]]) && strpos(store_last_error(), 'format') !== false, 'MySQL rejects invalid SKU before connecting');
    verify_sku(json_decode(file_get_contents(STORE_FILE), true)['products'][0] === $saved, 'rejected save leaves data intact');
    $long = $original;
    $long['skus'] = $hundred;
    verify_sku(store_save_json(['categories' => $cats, 'products' => [$long]]) && count(store_load_json()['products'][0]['skus']) === 100, 'JSON saves 100 long Unicode SKUs');

    $csv = $root . '/skus.csv';
    $handle = fopen($csv, 'wb');
    foreach ($export as $row) { fputcsv($handle, $row, ',', '"', ''); }
    fclose($handle);
    $read = import_read_csv($csv);
    $sheet = import_parse_sheet_rows($read['rows']);
    verify_sku(empty($sheet['error']) && json_decode($sheet['rows'][0][$sheet['map']['skus']], true) === $parsed, 'CSV SKU JSON round trip');
    unlink($csv);

    if (class_exists('ZipArchive')) {
        require_once dirname(__DIR__) . '/lib/SimpleXLSXGen.php';
        $xlsx = $root . '/skus.xlsx';
        verify_sku(\Shuchkin\SimpleXLSXGen::fromArray($export, '商品')->saveAs($xlsx), 'XLSX generation');
        $read = import_read_xlsx($xlsx);
        $sheet = import_parse_sheet_rows($read['rows']);
        verify_sku(empty($sheet['error']) && json_decode($sheet['rows'][0][$sheet['map']['skus']], true) === $parsed, 'XLSX SKU JSON round trip');
        unlink($xlsx);
    }
} finally {
    unlink(STORE_FILE);
    rmdir($root . '/data');
    rmdir($root);
}
