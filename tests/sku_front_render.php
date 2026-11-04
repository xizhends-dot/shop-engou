<?php
/** 実データを変更せずに SKU の画面出力を検証する。実行: php tests/sku_front_render.php */
require_once dirname(__DIR__) . '/lib/store.php';

function front_check($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function front_document($html) {
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return new DOMXPath($document);
}

function front_render_product(array $fixture) {
    // エントリのデータ取得のみ固定し、実際のテンプレート・helper・共通パーツを実行する。
    $root = dirname(__DIR__);
    $source = file_get_contents($root . '/product.php');
    $source = str_replace(
        ["\$config   = require __DIR__ . '/config.php';", "\$data     = require __DIR__ . '/products.php';"],
        ["\$config = \$fixture['config'];", "\$data = \$fixture['data'];"],
        $source,
        $replacements
    );
    front_check($replacements === 2, 'Product loader changed; update the render test seam.');
    $code = '';
    foreach (token_get_all($source) as $token) {
        $code .= is_array($token) ? ($token[0] === T_DIR ? var_export($root, true) : $token[1]) : $token;
    }
    $_GET['id'] = $fixture['data']['products'][0]['id'];
    ob_start();
    try {
        eval('?>' . $code);
        return ob_get_contents();
    } finally {
        ob_end_clean();
    }
}

function front_render_card(array $p, array $cats) {
    ob_start();
    try {
        include dirname(__DIR__) . '/partials/product_card.php';
        return ob_get_contents();
    } finally {
        ob_end_clean();
    }
}

$hostile = '白 "S" \'特別\' </script><script>alert("sku")</script> & <b>色</b>';
$base = [
    'id' => 'sku-front-test', 'category' => 'home', 'name' => '商品 "確認" </script><script>alert("product")</script>',
    'tag' => '', 'price' => 9000, 'price_max' => 9900, 'images' => [], 'desc' => '説明',
    'attributes' => [['name' => '色', 'options' => ['白', '黒']], ['name' => 'サイズ', 'options' => ['S', 'M']]],
];
$cats = ['home' => ['name' => '生活家電', 'icon' => 'fa-house']];
$fixture = [
    'config' => [
        'company_name_ja' => 'テスト', 'company_name_en' => 'TEST', 'brand' => 'KMEAG',
        'email' => 'test@example.test', 'main_site_url' => 'https://example.test',
        'zipcode' => '', 'address_ja' => '', 'phone' => '', 'hours_ja' => '',
    ],
    'data' => ['categories' => $cats, 'products' => []],
];

$product = $base;
$product['skus'] = [
    ['id' => 'A', 'name' => $hostile, 'price' => 1000],
    ['id' => 'B', 'name' => '黒 / M', 'price' => 1500],
];
$related = $product;
$related['id'] = 'related';
$fixture['data']['products'] = [$product, $related];
$html = front_render_product($fixture);
$dom = front_document($html);
$price = $dom->query('//*[@id="productPrice"]')->item(0);
front_check(strpos($price->textContent, '¥1,000 ～ ¥1,500') !== false, 'Detail must derive its range from SKUs.');
front_check($dom->query('//*[@id="skuOptions"]//input[@type="radio"]')->length === 2, 'Each SKU needs a native radio.');
front_check($dom->query('//*[@id="skuOptions"]//input[@checked]')->length === 0, 'Initial SKU state must be unselected.');
front_check($dom->query('//*[@id="dAttrs"]')->length === 0, 'SKU products must not render independent attribute controls.');
front_check($dom->query('//*[contains(concat(" ", normalize-space(@class), " "), " sku-specs ")]')->length === 1, 'SKU attributes must remain readable as specifications.');
$radio = $dom->query('//*[@id="product-sku-0"]')->item(0);
front_check($radio->getAttribute('data-sku-name') === $hostile, 'SKU names must round-trip safely through HTML attributes.');
front_check($radio->getAttribute('data-sku-price') === '1000', 'Prices must be integer data attributes.');
front_check($dom->query('//label[@for="product-sku-0"]')->length === 1, 'Radio must have an associated label.');
$label = $dom->query('//label[@for="product-sku-0"]')->item(0)->textContent;
front_check(strpos($label, $hostile) !== false && strpos($label, 'SKU番号：A') !== false && strpos($label, '¥1,000') !== false && strpos($label, '税込') !== false, 'Labels must expose name, SKU ID and tax-inclusive price.');
front_check($dom->query('//script[contains(text(), "alert(")]')->length === 0, 'Hostile names must never become executable scripts.');
front_check($dom->query('//script[contains(@src, "product-skus.js")]')->length === 1, 'Exactly one inquiry/SKU script must load.');
front_check(strpos($html, 'function updateInquiry') === false, 'No second inline inquiry updater may overwrite the external one.');
$inquiry = $dom->query('//*[@id="inquiryBtn"]')->item(0);
front_check($inquiry->getAttribute('data-product-name') === $base['name'], 'Product data must remain safely escaped outside scripts.');
parse_str(parse_url($inquiry->getAttribute('href'), PHP_URL_QUERY), $mail);
front_check(strpos($mail['body'], $base['id']) !== false && strpos($mail['body'], 'SKU番号') === false, 'Fallback inquiry must identify the product without a selected SKU.');
front_check(strpos($dom->query('//*[contains(@class, "product-foot")]')->item(0)->textContent, '¥1,000 ～ ¥1,500') !== false, 'Related cards must use SKU ranges.');

$card = front_render_card($product, $cats);
front_check(strpos($card, '1,000') !== false && strpos($card, '1,500') !== false && strpos($card, '9,000') === false, 'The shared home/list card must use SKU prices.');
$product['skus'][1]['price'] = 1000;
$fixture['data']['products'] = [$product];
$equal = front_document(front_render_product($fixture));
front_check(strpos($equal->query('//*[@id="productPrice"]')->item(0)->textContent, '～') === false, 'Equal SKU prices must display once in detail.');
front_check(strpos(front_render_card($product, $cats), '～') === false, 'Equal SKU prices must display once in cards.');

$fixture['data']['products'] = [$base];
$legacyHtml = front_render_product($fixture);
$legacy = front_document($legacyHtml);
front_check(strpos($legacy->query('//*[@id="productPrice"]')->item(0)->textContent, '¥9,000 ～ ¥9,900') !== false, 'Legacy price range must be preserved.');
front_check($legacy->query('//*[@id="dAttrs"]//button')->length === 4, 'Legacy attribute buttons must be preserved.');
front_check($legacy->query('//*[@id="skuOptions"]')->length === 0 && strpos($legacyHtml, 'sku-options.css') === false, 'Legacy products must not load SKU controls or styles.');

$product['skus'] = [['id' => 'A', 'name' => '不正価格', 'price' => '1.5']];
$fixture['data']['products'] = [$product];
$invalid = front_document(front_render_product($fixture));
front_check($invalid->query('//*[@id="skuOptions"]')->length === 0, 'Invalid persisted SKUs must fall back safely.');

foreach ([0, 2147483647] as $amount) {
    $product['skus'] = [['id' => 'A', 'name' => '境界価格', 'price' => $amount]];
    $fixture['data']['products'] = [$product];
    $boundary = front_document(front_render_product($fixture));
    front_check($boundary->query('//*[@id="product-sku-0"]')->item(0)->getAttribute('data-sku-price') === (string)$amount, 'Boundary prices must stay exact.');
}

echo "SKU frontend rendering: all assertions passed.\n";
