<?php
require_once dirname(__DIR__) . '/lib/product_import.php';
function check_price($ok, $label) {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS: $label\n";
}
check_price(strip_tags(yen(1000)) === '¥1,000（税込）', 'legacy single price');
check_price(strip_tags(yen(1000, 2000)) === '¥1,000 ～ ¥2,000（税込）', 'range price');
check_price(strip_tags(yen(0, 0)) === '¥0 ～ ¥0（税込）', 'zero maximum is not empty');
check_price(strip_tags(yen(1000, '')) === '¥1,000（税込）', 'clear maximum');
foreach (['0', '1000', '2147483647', 123] as $v) { check_price(store_valid_price($v), 'valid integer ' . $v); }
foreach (['', '-1', '1.5', '1e3', '2147483648', 'abc', null, []] as $v) { check_price(!store_valid_price($v), 'reject invalid price'); }
$cats = ['test' => ['name' => 'Test', 'icon' => 'fa-box']];
$p = ['id' => 'test-1', 'category' => 'test', 'name' => 'Test', 'price' => 1000, 'price_max' => 2000];
$rows = import_build_export_rows([$p]);
$parsed = import_parse_sheet_rows($rows);
$result = import_apply_rows($parsed['rows'], $parsed['map'], ['categories' => $cats, 'products' => []], $cats);
check_price(!$result['rowErrors'] && $result['data']['products'][0]['price_max'] === 2000, 'export/import range round trip');
check_price(count($rows[0]) === count($rows[1]), 'export column alignment');
$legacy = import_parse_sheet_rows([['id','category','name','price'], ['test-1','test','Test','1200']]);
$result = import_apply_rows($legacy['rows'], $legacy['map'], ['categories' => $cats, 'products' => [$p]], $cats);
check_price($result['data']['products'][0]['price_max'] === 2000, 'legacy import preserves maximum');
$clear = import_parse_sheet_rows([['id','category','name','price','price_max'], ['test-1','test','Test','1200','']]);
$result = import_apply_rows($clear['rows'], $clear['map'], ['categories' => $cats, 'products' => [$p]], $cats);
check_price($result['data']['products'][0]['price_max'] === null, 'blank import clears maximum');
$bad = import_parse_sheet_rows([['id','category','name','price','price_max'], ['test-1','test','Test','3000','2000']]);
$result = import_apply_rows($bad['rows'], $bad['map'], ['categories' => $cats, 'products' => [$p]], $cats);
check_price(count($result['rowErrors']) === 1 && $result['data']['products'][0]['price'] === 1000, 'invalid range leaves product unchanged');
foreach (import_sample_rows($cats) as $row) { check_price(count($row) === count($rows[0]), 'sample column alignment'); }
