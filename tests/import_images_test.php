<?php
// 画像・商品データはテスト専用フォルダに隔離する。
$testRoot = sys_get_temp_dir() . '/shop-import-' . bin2hex(random_bytes(8));
define('SHOP_BASE', $testRoot);
require_once dirname(__DIR__) . '/lib/product_import.php';
function check_import_image($ok, $label) {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS: $label\n";
}
mkdir(UPLOAD_DIR, 0775, true);
$fixture = UPLOAD_DIR . '/test.png';
file_put_contents($fixture, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9AAAAABJRU5ErkJggg=='));
try {
    $cats = ['test' => ['name' => 'Test']];
    $old = ['id' => 'one', 'category' => 'test', 'name' => 'Original', 'price' => 100, 'images' => ['images/products/test.png']];
    $data = ['categories' => $cats, 'products' => [$old]];
    foreach (['http://127.0.0.1/a.webp', 'file:///etc/passwd', 'images/products/missing.webp'] as $source) {
        $sheet = import_parse_sheet_rows([['id', 'category', 'name', 'price', 'image1'], ['one', 'test', 'Changed', '200', $source]]);
        $result = import_apply_rows($sheet['rows'], $sheet['map'], $data, $cats);
        check_import_image(count($result['rowErrors']) === 1 && $result['updated'] === 0 && $result['data']['products'][0] === $old, 'invalid image preserves entire original product');
    }
    foreach (['', 'images/products/test.png'] as $source) {
        $sheet = import_parse_sheet_rows([['id', 'category', 'name', 'price', 'image1'], ['one', 'test', 'Changed', '200', $source]]);
        $result = import_apply_rows($sheet['rows'], $sheet['map'], $data, $cats);
        check_import_image(!$result['rowErrors'] && $result['updated'] === 1 && $result['data']['products'][0]['images'] === $old['images'], 'local image and empty image compatibility');
    }
    $sheet = import_parse_sheet_rows([['id', 'category', 'name', 'price', 'image1', 'image2'], ['one', 'test', 'Changed', '200', 'images/products/test.png', 'http://127.0.0.1/b.png']]);
    $result = import_apply_rows($sheet['rows'], $sheet['map'], $data, $cats);
    check_import_image($result['data']['products'][0] === $old && $result['updated'] === 0, 'second image failure does not partially update product');
} finally {
    // このテストで作った既知のファイルと空ディレクトリのみ削除する。
    unlink($fixture);
    rmdir(UPLOAD_DIR);
    rmdir($testRoot . '/images');
    rmdir($testRoot);
}
