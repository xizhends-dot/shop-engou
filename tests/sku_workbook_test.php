<?php
// 実際の複数シートXLSXを生成し、商品・SKUシートの取込みを検証する。
$root = sys_get_temp_dir() . '/shop-sku-workbook-' . bin2hex(random_bytes(8));
define('SHOP_BASE', $root);
require_once dirname(__DIR__) . '/lib/product_import.php';
require_once dirname(__DIR__) . '/lib/SimpleXLSXGen.php';
function workbook_check($condition, $label) {
    if (!$condition) { throw new RuntimeException($label); }
    echo "PASS: {$label}\n";
}
function workbook_rows($skuRows, $products = []) {
    return ['rows' => $products ?: [import_xlsx_product_rows(import_build_export_rows([]))[0]], 'sku_rows' => $skuRows, 'from_csv' => false];
}
$cats = ['test' => ['name' => 'Test']];
$sample = import_sample_rows($cats);
$sampleProduct = import_sample_grouped_product($cats);
$sampleMain = import_sample_xlsx_product_rows($cats);
$sampleSku = import_build_sku_sheet_rows([$sampleProduct]);
$old = ['id' => 'SAMPLE-003', 'category' => 'test', 'name' => 'Original', 'desc' => 'Keep this description', 'images' => ['images/products/keep.jpg'], 'attributes' => [['name' => '素材', 'options' => ['綿']]], 'tag' => 'Keep tag', 'icon' => 'fa-box', 'accent' => '#123456', 'badge' => 'Keep badge', 'rating' => 4.5, 'reviews' => 12, 'price' => 3000, 'price_max' => null, 'skus' => [], 'sku_groups' => []];
$data = ['categories' => $cats, 'products' => [$old]];
$empty = ['categories' => $cats, 'products' => []];
$path = $root . '.xlsx';
try {
    $book = import_create_xlsx([$sampleMain[0], $sampleMain[3]], $sampleSku);
    workbook_check($book->saveAs($path), 'two-sheet XLSX created');
    $parsed = import_read_xlsx($path);
    workbook_check(!isset($parsed['error']) && count($parsed['sku_rows']) === 5 && !in_array('skus', $parsed['rows'][0], true), 'workbook reads a plain product sheet and four SKU rows');
    $result = import_apply_workbook($parsed, $empty, $cats);
    $added = $result['data']['products'][0];
    workbook_check(!$result['rowErrors'] && $result['added'] === 1 && $added['sku_groups'] === $sampleProduct['sku_groups'] && $added['skus'] === $sampleProduct['skus'] && $added['price'] === 1000 && $added['price_max'] === 1600, 'new product gets grouped SKU prices from second sheet');
    $oldColumns = [$sample[0], $sample[3]];
    $prioritySku = $sampleSku;
    $prioritySku[1][3] = '777';
    $priority = import_apply_workbook(workbook_rows($prioritySku, $oldColumns), $empty, $cats);
    workbook_check(!$priority['rowErrors'] && $priority['data']['products'][0]['skus'][0]['price'] === 777 && $priority['data']['products'][0]['price'] === 777, 'SKU sheet overrides JSON columns for the same CODE');
    $missingRows = import_apply_workbook(workbook_rows([import_sku_sheet_header()], $oldColumns), $empty, $cats);
    workbook_check(!$missingRows['rowErrors'] && $missingRows['data']['products'][0]['skus'] === $sampleProduct['skus'], 'product row retains its JSON SKUs when SKU sheet contains no matching CODE');

    $repeatedMain = [$sampleMain[0], $sampleMain[3], $sampleMain[3]];
    $repeatedMain[2][1] = 'missing-category';
    $result = import_apply_workbook(workbook_rows($sampleSku, $repeatedMain), $data, $cats);
    workbook_check($result['data'] === $data && $result['updated'] === 0 && $result['rowErrors'], 'duplicate product CODE rejects all main and SKU changes for that CODE');
    $onlySkuBook = \Shuchkin\SimpleXLSXGen::fromArray(import_xlsx_text_rows($sampleSku), 'SKU');
    $onlySkuBook->saveAs($path);
    $result = import_apply_workbook(import_read_xlsx($path), $data, $cats);
    workbook_check(!$result['rowErrors'] && $result['updated'] === 1 && $result['data']['products'][0]['skus'] === $sampleProduct['skus'], 'standalone SKU workbook updates existing product');
    $reversedBook = \Shuchkin\SimpleXLSXGen::fromArray(import_xlsx_text_rows($sampleSku), 'SKU');
    $reversedBook->addSheet(import_xlsx_text_rows([$sampleMain[0], $sampleMain[3]]), '商品')->saveAs($path);
    $result = import_apply_workbook(import_read_xlsx($path), $empty, $cats);
    workbook_check(!$result['rowErrors'] && $result['added'] === 1, 'worksheet order can be changed without losing SKU rows');

    $skuOnly = workbook_rows($sampleSku);
    $result = import_apply_workbook($skuOnly, $data, $cats);
    $modified = $result['data']['products'][0];
    $expected = $old;
    $expected['sku_groups'] = $sampleProduct['sku_groups'];
    $expected['skus'] = $sampleProduct['skus'];
    $expected['price'] = 1000;
    $expected['price_max'] = 1600;
    workbook_check($result['updated'] === 1 && !$result['rowErrors'] && $modified === $expected, 'SKU-only update preserves all non-SKU product fields');
    $exportSku = import_build_sku_sheet_rows([$modified]);
    workbook_check(import_apply_workbook(workbook_rows($exportSku), $data, $cats)['data']['products'][0] === $expected, 'exported SKU rows retain IDs, titles, options and names when re-imported');
    $autoIds = $sampleSku;
    foreach (array_slice($autoIds, 1) as $offset => $_) { $autoIds[$offset + 1][8] = ''; }
    $generated = import_apply_workbook(workbook_rows($autoIds), $data, $cats);
    workbook_check(!$generated['rowErrors'] && array_column($generated['data']['products'][0]['skus'], 'id') === ['SKU-1', 'SKU-2', 'SKU-3', 'SKU-4'], 'empty SKU IDs are generated per CODE');
    $single = [import_sku_sheet_header(), ['SAMPLE-003', 'カラー', 'RED', '750']];
    $result = import_apply_workbook(workbook_rows($single), $data, $cats);
    workbook_check(!$result['rowErrors'] && $result['updated'] === 1 && $result['data']['products'][0]['skus'][0] === ['id' => 'SKU-1', 'name' => 'RED', 'price' => 750, 'options' => ['RED']], 'four primary columns alone can set a SKU option and its price');
    $flat = ['id'=>'SAMPLE-003','skus'=>[['id'=>'A','name'=>'ホワイト','price'=>880]],'sku_groups'=>[]];
    $result = import_apply_workbook(workbook_rows(import_build_sku_sheet_rows([$flat])), $data, $cats);
    workbook_check(!$result['rowErrors'] && $result['data']['products'][0]['sku_groups'] === [] && $result['data']['products'][0]['skus'] === $flat['skus'], 'legacy flat SKU exports re-import without creating groups');
    workbook_check(import_apply_workbook(workbook_rows([import_sku_sheet_header()]), $data, $cats)['data'] === $data, 'header-only SKU sheet does not clear existing SKUs');
    workbook_check(isset(import_apply_workbook(workbook_rows([]), $data, $cats)['error']), 'empty SKU sheet reports missing header');
    $badHeader = import_apply_workbook(workbook_rows([['CODE', 'wrong']]), $data, $cats);
    workbook_check(isset($badHeader['error']) && !isset($badHeader['data']), 'malformed SKU header rejects workbook');

    $missing = $sampleSku;
    $missing[1][0] = 'UNKNOWN';
    $result = import_apply_workbook(workbook_rows($missing), $data, $cats);
    workbook_check($result['updated'] === 1 && count($result['rowErrors']) === 1 && $result['data']['products'][0]['skus'] === array_slice($sampleProduct['skus'], 1), 'unknown CODE is reported while other CODEs remain importable');

    $invalid = $sampleSku;
    $invalid[2][3] = 'bad-price';
    $result = import_apply_workbook(workbook_rows($invalid), $data, $cats);
    workbook_check($result['updated'] === 0 && count($result['rowErrors']) === 1 && $result['data'] === $data, 'one invalid row rejects every SKU for its CODE');
    $invalidProduct = [$sampleMain[0], $sampleMain[3]];
    $result = import_apply_workbook(workbook_rows($invalid, $invalidProduct), $data, $cats);
    workbook_check($result['updated'] === 0 && count($result['rowErrors']) >= 2 && $result['data'] === $data, 'invalid SKU prevents the matching product row from updating');
    $duplicate = $sampleSku;
    $duplicate[2][2] = $duplicate[1][2];
    $duplicate[2][5] = $duplicate[1][5];
    $result = import_apply_workbook(workbook_rows($duplicate), $data, $cats);
    workbook_check($result['updated'] === 0 && $result['rowErrors'] && $result['data'] === $data, 'duplicate combination rejects full CODE');
    $wrongMain = [$sampleMain[0], $sampleMain[3]];
    $wrongMain[1][1] = 'no-category';
    $result = import_apply_workbook(workbook_rows($sampleSku, $wrongMain), $data, $cats);
    workbook_check($result['updated'] === 0 && $result['rowErrors'] && $result['data'] === $data, 'failed product row does not apply its SKU sheet');

    $special = $old;
    $special['id'] = '-A';
    $special['images'] = [];
    $special['name'] = '=商品';
    $special['desc'] = '<b>そのまま</b>';
    $special['sku_groups'] = [['name' => '@仕様', 'options' => ['-10', '001', '2026-01-02', '<b>RED</b>']]];
    $special['skus'] = [
        ['id'=>'-SKU','name'=>'@X','price'=>10,'options'=>['-10']],
        ['id'=>'001','name'=>'+Y','price'=>20,'options'=>['001']],
        ['id'=>'DATE','name'=>'=Z','price'=>30,'options'=>['2026-01-02']],
        ['id'=>'HTML','name'=>"'literal",'price'=>40,'options'=>['<b>RED</b>']],
    ];
    $book = import_create_xlsx(import_xlsx_product_rows(import_build_export_rows([$special], false)), import_build_sku_sheet_rows([$special]));
    workbook_check($book->saveAs($path), 'special-value export created');
    $result = import_apply_workbook(import_read_xlsx($path), $empty, $cats);
    workbook_check(!$result['rowErrors'] && $result['added'] === 1 && $result['data']['products'][0]['id'] === '-A' && $result['data']['products'][0]['name'] === '=商品' && $result['data']['products'][0]['desc'] === $special['desc'] && $result['data']['products'][0]['skus'] === $special['skus'] && $result['data']['products'][0]['sku_groups'] === $special['sku_groups'], 'real XLSX roundtrip preserves leading zeros, formula prefixes, dates and literal markup');
    $zip = new ZipArchive();
    $zip->open($path);
    workbook_check(strpos($zip->getFromName('xl/worksheets/sheet1.xml'), '<f') === false && strpos($zip->getFromName('xl/worksheets/sheet2.xml'), '<f') === false, 'exported text never becomes an Excel formula');
    $zip->close();

    $legacy = import_apply_workbook(['rows' => [$sample[0], $sample[3]], 'from_csv' => true], $empty, $cats);
    workbook_check(!$legacy['rowErrors'] && $legacy['data']['products'][0]['skus'] === $sampleProduct['skus'], 'old CSV JSON columns remain supported');
    $legacyBook = \Shuchkin\SimpleXLSXGen::fromArray([$sample[0], $sample[3]], '商品');
    workbook_check($legacyBook->saveAs($path), 'old single-sheet XLSX created');
    $legacyParsed = import_read_xlsx($path);
    $legacy = import_apply_workbook($legacyParsed, $empty, $cats);
    workbook_check($legacyParsed['sku_rows'] === null && !$legacy['rowErrors'] && $legacy['data']['products'][0]['skus'] === $sampleProduct['skus'], 'old single-sheet XLSX JSON columns remain supported');
} finally {
    if (is_file($path)) { unlink($path); }
}
