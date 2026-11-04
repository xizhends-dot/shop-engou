<?php
// ページを固定データで実行する。業務データやDBへの書き込みは行わない。
$scenario = $argv[1] ?? 'home';
function storefront_fixture() {
    global $scenario;
    $products = [];
    if ($scenario !== 'empty-home') {
        for ($i = 1; $i <= 45; $i++) {
            $products[] = ['id' => 'ENG-' . $i, 'category' => 'beauty', 'name' => '静音 Test ' . $i, 'price' => 1000, 'tag' => '', 'images' => [], 'accent' => '#DEF13F', 'icon' => 'fa-box'];
        }
        $products[] = ['id' => 'OTHER', 'category' => 'home', 'name' => '別商品', 'price' => 2000, 'tag' => '', 'images' => []];
    }
    return ['categories' => ['beauty' => ['name' => '美容家電', 'icon' => 'fa-box'], 'home' => ['name' => '生活家電', 'icon' => 'fa-box']], 'products' => $products];
}
function storefront_check($ok, $label) {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS: $label\n";
}
$isHome = in_array($scenario, ['home', 'empty-home'], true);
$_GET = [];
if ($scenario === 'search') { $_GET = ['q' => 'test', 'cat' => 'beauty', 'page' => '2']; }
if ($scenario === 'id') { $_GET = ['q' => 'eng-45']; }
if ($scenario === 'escape') { $_GET = ['q' => '"><script>alert(1)</script>']; }
if ($scenario === 'array') { $_GET = ['q' => [], 'cat' => [], 'page' => []]; }
$root = dirname(__DIR__);
$source = file_get_contents($root . ($isHome ? '/index.php' : '/list.php'));
$source = str_replace("\$data     = require __DIR__ . '/products.php';", "require_once __DIR__ . '/lib/store.php'; \$data = storefront_fixture();", $source);
$source = str_replace('__DIR__', var_export($root, true), $source);
ob_start();
eval('?>' . $source);
$html = ob_get_clean();
if ($isHome) {
    foreach (['categories', 'featured', 'shopping-guide', 'faq', 'brand-story'] as $id) {
        storefront_check(strpos($html, 'id="' . $id . '"') !== false, 'home section ' . $id);
    }
    storefront_check(strpos($html, 'name="q"') !== false, 'header search');
    if ($scenario === 'empty-home') { storefront_check(strpos($html, '商品情報を準備しています') !== false, 'empty catalog feedback'); }
} else {
    if ($scenario === 'search') {
        storefront_check($total === 45 && count($items) === 5 && $page === 2, 'search before pagination');
        storefront_check(strpos($html, 'q=test') !== false && strpos($html, 'cat=beauty') !== false && strpos($html, 'cat%5B') === false, 'filter and pagination keep query');
        parse_str(parse_url(list_url('home', 1, '静音 & +'), PHP_URL_QUERY), $params);
        storefront_check($params['q'] === '静音 & +' && $params['cat'] === 'home', 'URL encoding round trip');
    }
    if ($scenario === 'id') { storefront_check($total === 1 && $items[0]['id'] === 'ENG-45', 'case-insensitive product ID search'); }
    if ($scenario === 'escape') { storefront_check(strpos($html, '<script>alert(1)</script>') === false && $total === 0, 'escaped search query and empty results'); }
    if ($scenario === 'array') { storefront_check($query === '' && $cat === 'all' && $page === 1 && $total === 46, 'array query ignored safely'); }
}
