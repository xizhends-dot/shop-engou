<?php
// 保存先と画像ダウンロードを隔離し、実商品・ネットワークに触れない。
$root = sys_get_temp_dir() . '/shop-sku-groups-' . bin2hex(random_bytes(8));
define('SHOP_BASE', $root);
require_once dirname(__DIR__) . '/lib/product_import.php';

function verify_group($ok, $label) {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS: $label\n";
}
function expect_group_error($rows, $code) {
    $error = '';
    verify_group(store_parse_sku_groups($rows, $error) === false && $error === $code, 'group rejects ' . $code);
}
function expect_group_sku_error($rows, $groups, $code) {
    $error = '';
    verify_group(store_parse_skus($rows, $error, $groups) === false && $error === $code, 'group SKU rejects ' . $code);
}
function group_import($headers, $row, $data, $cats) {
    $sheet = import_parse_sheet_rows([$headers, $row]);
    return import_apply_rows($sheet['rows'], $sheet['map'], $data, $cats);
}

set_error_handler(function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
$groups = [['name' => 'カラー', 'options' => ['RED', 'BLUE']], ['name' => 'サイズ', 'options' => ['S', 'M']]];
$error = '';
verify_group(store_parse_sku_groups([2 => ['name' => ' カラー ', 'options' => [1 => 'RED', 9 => ' BLUE ']], 8 => $groups[1]], $error) === $groups, 'sparse groups and options normalize');
verify_group(store_parse_sku_groups([[], ['name' => ' ', 'options' => [' ', '']]], $error) === [], 'entirely blank groups ignored');
verify_group(store_parse_sku_groups([['name' => '番号', 'options' => [0, '1']]], $error)[0]['options'] === ['0', '1'], 'numeric options normalized as strings');
foreach (['bad', new stdClass(), ['bad' => $groups[0]], [['name' => []]], [['name' => new stdClass()]], [['name' => false]]] as $bad) {
    expect_group_error($bad, 'format');
}
foreach ([null, 'RED', new stdClass(), [['RED']], [new stdClass()], [false], ['a' => 'RED']] as $badOptions) {
    expect_group_error([['name' => 'カラー', 'options' => $badOptions]], 'options');
}
expect_group_error([['name' => 'カラー', 'options' => []]], 'options');
expect_group_error([['name' => 'カラー', 'options' => ['RED', ' ']]], 'options');
expect_group_error([['name' => '', 'options' => ['RED']]], 'name');
expect_group_error([['name' => str_repeat('あ', 61), 'options' => ['RED']]], 'name');
expect_group_error([['name' => 'カラー', 'options' => [str_repeat('😀', 101)]]], 'options');
expect_group_error([['name' => 'カラー', 'options' => ['RED', ' RED ']]], 'duplicate');
expect_group_error([$groups[0], $groups[0]], 'duplicate');
expect_group_error([['name' => 'カラー', 'options' => array_map('strval', range(0, 30))]], 'limit');
expect_group_error([['name'=>'A','options'=>['1']], ['name'=>'B','options'=>['1']], ['name'=>'C','options'=>['1']], ['name'=>'D','options'=>['1']]], 'limit');
verify_group(count(store_parse_sku_groups([['name' => str_repeat('😀', 60), 'options' => array_map('strval', range(0, 29))]], $error)[0]['options']) === 30, 'Unicode group and 30-option boundaries accepted');
verify_group(store_product_sku_groups(['sku_groups' => new stdClass()]) === [] && store_product_sku_groups([]) === [], 'invalid or absent group safe for display');

$skus = [['id'=>'red-s', 'name'=>'', 'price'=>'1000', 'options'=>['RED','S']], ['id'=>'blue-m', 'name'=>'ブルー M', 'price'=>'1600', 'options'=>['BLUE','M']]];
$normalized = store_parse_skus($skus, $error, $groups);
verify_group(store_parse_skus([['id'=>'','name'=>' ','price'=>'','options'=>['',' ']]], $error, $groups) === [], 'grouped empty select row ignored');
expect_group_sku_error([['options'=>['RED','S']]], $groups, 'id');
verify_group($normalized === [['id'=>'red-s','name'=>'RED / S','price'=>1000,'options'=>['RED','S']], ['id'=>'blue-m','name'=>'ブルー M','price'=>1600,'options'=>['BLUE','M']]], 'partial Cartesian combinations and automatic name accepted');
verify_group(store_parse_skus([4 => ['id'=>'red-s','price'=>1000,'options'=>[5=>'RED',9=>'S']]], $error, $groups) === [$normalized[0]], 'sparse SKU options preserve group order');
expect_group_sku_error([['id'=>'a','name'=>'A','price'=>1]], $groups, 'options');
expect_group_sku_error([['id'=>'a','price'=>1,'options'=>['RED']]], $groups, 'options');
expect_group_sku_error([['id'=>'a','price'=>1,'options'=>['RED','S','X']]], $groups, 'options');
expect_group_sku_error([['id'=>'a','price'=>1,'options'=>['S','RED']]], $groups, 'options');
expect_group_sku_error([['id'=>'a','price'=>1,'options'=>['GREEN','S']]], $groups, 'options');
expect_group_sku_error([$skus[0], ['id'=>'other','price'=>1,'options'=>['RED','S']]], $groups, 'combination');
expect_group_sku_error([$skus[0]], [], 'options');
foreach (['RED', null, new stdClass(), [['RED'],'S'], [new stdClass(),'S'], [true,'S']] as $badOptions) {
    expect_group_sku_error([['id'=>'a','name'=>'A','price'=>1,'options'=>$badOptions]], $groups, 'options');
}
$legacy = [['id'=>'a','name'=>'A','price'=>1]];
verify_group(store_parse_skus($legacy, $error) === $legacy && store_parse_skus([array_merge($legacy[0], ['options'=>[]])], $error) === $legacy, 'legacy output has precisely id/name/price');
$longGroups = [];
foreach (['A','B','C'] as $name) { $longGroups[] = ['name'=>$name,'options'=>[str_repeat('😀',100)]]; }
$longOptions = array_fill(0, 3, str_repeat('😀', 100));
verify_group(mb_strlen(store_parse_skus([['id'=>'long','price'=>1,'options'=>$longOptions]], $error, $longGroups)[0]['name'], 'UTF-8') === 306, '306-character generated grouped SKU name accepted');
expect_group_sku_error([['id'=>'a','name'=>str_repeat('あ',401),'price'=>1,'options'=>['RED','S']]], $groups, 'name');

$cats = ['test'=>['name'=>'Test']];
$product = ['id'=>'GROUPS', 'category'=>'test', 'name'=>'Sample', 'price'=>9999, 'price_max'=>99999, 'images'=>[], 'sku_groups'=>$groups, 'skus'=>$normalized];
$data = ['categories'=>$cats, 'products'=>[$product]];
$export = import_build_export_rows([$product]);
verify_group(count($export[0]) === count($export[1]), 'export columns align');
foreach (import_sample_rows($cats) as $sampleRow) { verify_group(count($sampleRow) === count($export[0]), 'sample columns align'); }
$sheet = import_parse_sheet_rows($export);
$result = import_apply_rows($sheet['rows'], $sheet['map'], ['categories'=>$cats,'products'=>[]], $cats);
verify_group(!$result['rowErrors'] && $result['data']['products'][0]['sku_groups'] === $groups && $result['data']['products'][0]['skus'] === $normalized && $result['data']['products'][0]['price'] === 1000 && $result['data']['products'][0]['price_max'] === 1600, 'sheet import preserves groups and derives prices');
$result = group_import(['id','category','name','price'], ['GROUPS','test','Changed',''], $data, $cats);
verify_group(!$result['rowErrors'] && $result['data']['products'][0]['sku_groups'] === $groups && $result['data']['products'][0]['skus'] === $normalized, 'legacy missing columns retain groups and SKUs');
foreach (['', '[]'] as $clear) {
    foreach (['skus','sku_groups'] as $column) {
        $result = group_import(['id','category','name','price',$column,'image1'], ['GROUPS','test','Changed','1000',$clear,'https://example.invalid/must-not-download.jpg'], $data, $cats);
        verify_group(count($result['rowErrors']) === 1 && $result['updated'] === 0 && $result['data'] === $data && strpos($result['rowErrors'][0], $column === 'skus' ? 'group_skus' : 'options') !== false, 'clearing only ' . $column . ' skips complete row before images');
    }
    $result = group_import(['id','category','name','price','sku_groups','skus'], ['GROUPS','test','Changed','1200',$clear,$clear], $data, $cats);
    verify_group(!$result['rowErrors'] && $result['data']['products'][0]['sku_groups'] === [] && $result['data']['products'][0]['skus'] === [], 'explicit clearing both columns succeeds');
}
verify_group(!function_exists('remote_image_download'), 'invalid combinations never load image downloader');
$badJson = ['{}', '{"0":{"name":"カラー","options":["RED"]}}', '[{"name":"カラー","options":{"0":"RED"}}]', '[{"name":"カラー","options":[{}]}]'];
foreach ($badJson as $raw) {
    $result = group_import(['id','category','name','price','sku_groups'], ['GROUPS','test','Changed','1000',$raw], $data, $cats);
    verify_group(count($result['rowErrors']) === 1 && $result['data'] === $data, 'malformed group JSON leaves row untouched');
}
$result = group_import(['id','category','name','price','skus'], ['GROUPS','test','Changed','1000','[{"id":"a","price":1,"options":{"0":"RED","1":"S"}}]'], $data, $cats);
verify_group(count($result['rowErrors']) === 1 && $result['data'] === $data, 'object options rejected in SKU JSON');
$result = group_import(['id','category','name','price','sku_groups'], ['NEW','test','Changed','1000',json_encode($groups)], ['categories'=>$cats,'products'=>[]], $cats);
verify_group($result['added'] === 0 && strpos($result['rowErrors'][0], 'group_skus') !== false, 'new grouped product without SKU rejected');

mkdir($root . '/data', 0775, true);
try {
    verify_group(store_save_json($data), 'grouped product JSON save');
    $saved = file_get_contents(STORE_FILE);
    $loaded = store_load_json()['products'][0];
    verify_group($loaded['sku_groups'] === $groups && $loaded['skus'] === $normalized && $loaded['price'] === 1000 && $loaded['price_max'] === 1600, 'JSON groups/options round trip and price derivation');
    foreach ([['sku_groups'=>'bad'], ['skus'=>[]], ['sku_groups'=>[]]] as $change) {
        $badData = ['categories'=>$cats,'products'=>[array_merge($product,$change)]];
        verify_group(!store_save_json($badData) && !store_save_mysql($badData) && file_get_contents(STORE_FILE) === $saved, 'invalid grouped save rejected without touching JSON or connecting DB');
    }
    $badProduct = $product;
    $badProduct['skus'][0]['options'] = (object)['0'=>'RED','1'=>'S'];
    file_put_contents(STORE_FILE, json_encode(['categories'=>$cats,'products'=>[$badProduct]]));
    verify_group(store_load_json()['products'][0]['skus'] === [], 'JSON object options never become a valid array on read');
    file_put_contents(STORE_FILE, $saved);
    foreach (['csv','xlsx'] as $format) {
        if ($format === 'xlsx' && !class_exists('ZipArchive')) { echo "SKIP: XLSX requires zip\n"; continue; }
        $path = $root . '/groups.' . $format;
        if ($format === 'csv') {
            $handle = fopen($path, 'wb');
            foreach ($export as $row) { fputcsv($handle, $row, ',', '"', ''); }
            fclose($handle);
            $read = import_read_csv($path);
        } else {
            require_once dirname(__DIR__) . '/lib/SimpleXLSXGen.php';
            verify_group(\Shuchkin\SimpleXLSXGen::fromArray($export, '商品')->saveAs($path), 'XLSX generation');
            $read = import_read_xlsx($path);
        }
        $sheet = import_parse_sheet_rows($read['rows']);
        $result = import_apply_rows($sheet['rows'], $sheet['map'], ['categories'=>$cats,'products'=>[]], $cats, !empty($read['from_csv']));
        verify_group(!$result['rowErrors'] && $result['data']['products'][0]['sku_groups'] === $groups && $result['data']['products'][0]['skus'] === $normalized, strtoupper($format) . ' complete group/SKU round trip');
    }
} finally {
    // テスト自身が作成した一時ディレクトリ配下の既知ファイルのみ削除する。
    foreach ([$root . '/groups.csv', $root . '/groups.xlsx', STORE_FILE] as $path) {
        if (is_file($path)) { unlink($path); }
    }
    rmdir($root . '/data');
    rmdir($root);
    restore_error_handler();
}
