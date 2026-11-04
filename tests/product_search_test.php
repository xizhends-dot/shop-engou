<?php
require_once dirname(__DIR__) . '/lib/product_search.php';
function verify_search($ok, $label) {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS: $label\n";
}
$products = [
    ['id' => 'ENG-001', 'name' => 'シャワーヘッド'],
    ['id' => 'ENG-002', 'name' => '静音 Dryer'],
    ['id' => 'ABC-003', 'name' => '静音シャワー'],
];
verify_search(count(product_search($products, '', '')) === 3, 'empty filters');
verify_search(count(product_search($products, 'eng', '')) === 2, 'partial case-insensitive ID');
verify_search(product_search($products, '001', '')[0]['id'] === 'ENG-001', 'ID fragment');
verify_search(count(product_search($products, '', 'シャワー')) === 2, 'Japanese partial name');
verify_search(count(product_search($products, '', '静音')) === 2, 'Chinese partial name');
verify_search(product_search($products, '', 'dryer')[0]['id'] === 'ENG-002', 'case-insensitive name');
verify_search(product_search($products, 'eng', '静音')[0]['id'] === 'ENG-002', 'combined filters');
verify_search(product_search($products, '001', '静音') === [], 'no matches');
verify_search(product_search_query(['q' => '  001  '], 'q') === '001', 'trim query');
verify_search(product_search_query(['q' => ['x']], 'q') === '', 'reject array query');
verify_search(product_search_query([], 'q') === '', 'missing query');
verify_search(product_search_query(['q' => '0'], 'q') === '0', 'zero query retained');
parse_str(parse_url(product_search_page_url(2, 'A&B', '静音 + #'), PHP_URL_QUERY), $params);
verify_search($params === ['page' => '2', 'search_id' => 'A&B', 'search_name' => '静音 + #'], 'pagination query round trip');
$many = array_fill(0, 25, ['id' => 'ENG-001', 'name' => 'Test']);
$filtered = product_search($many, 'eng', 'test');
verify_search(count(array_slice($filtered, 20, 20)) === 5, 'filter before pagination');
