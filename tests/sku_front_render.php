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
front_check(strpos($label, $hostile) !== false && strpos($label, 'SKU番号') === false && strpos($label, '¥1,000') !== false && strpos($label, '税込') !== false, 'Labels must show name and tax-inclusive price without SKU numbers.');
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

// 実 helper とテンプレートを使い、色・サイズの疎な組み合わせを描画する。
$grouped = $base;
$grouped['sku_groups'] = [
    ['name' => 'カラー', 'options' => ['RED', 'BLUE', 'BLACK']],
    ['name' => 'サイズ', 'options' => ['S', 'M', 'L']],
];
$grouped['skus'] = [
    ['id' => 'private-red-s-001', 'name' => 'RED / S', 'price' => 1000, 'options' => ['RED', 'S']],
    ['id' => 'private-red-m-002', 'name' => 'RED / M', 'price' => 1200, 'options' => ['RED', 'M']],
    ['id' => 'private-blue-m-003', 'name' => 'BLUE / M', 'price' => 1500, 'options' => ['BLUE', 'M']],
    ['id' => 'private-black-l-004', 'name' => 'BLACK / L', 'price' => 2000, 'options' => ['BLACK', 'L']],
];
$fixture['data']['products'] = [$grouped];
$groupHtml = front_render_product($fixture);
$groupDom = front_document($groupHtml);
front_check(strpos($groupDom->query('//*[@id="productPrice"]')->item(0)->textContent, '¥1,000 ～ ¥2,000') !== false, 'Grouped prices must come from actual combinations.');
front_check($groupDom->query('//*[@id="skuOptions"]//fieldset')->length === 2, 'Each group needs its own fieldset.');
front_check($groupDom->query('//*[@id="skuOptions"]//fieldset/legend')->item(0)->textContent === 'カラー', 'First legend must be the color group name.');
front_check($groupDom->query('//*[@id="skuOptions"]//fieldset/legend')->item(1)->textContent === 'サイズ', 'Second legend must be the size group name.');
front_check($groupDom->query('//*[@id="skuOptions"]//button[@data-group-index][@data-option-index][@type="button"][@aria-pressed="false"]')->length === 6, 'Every option needs an initially unpressed native button.');
front_check($groupDom->query('//*[@id="skuOptions"]//input[@name="product_sku"]')->length === 0, 'Grouped controls must not mix with flat radios.');
front_check($groupDom->query('//*[@id="dAttrs"]')->length === 0, 'Grouped SKUs must not mix with independent attribute controls.');
front_check($groupDom->query('//*[@id="skuOptions"]//*[contains(@class, "sku-options__price")]')->length === 0, 'Group option buttons must not repeat per-SKU prices.');
front_check($groupDom->query('//*[@id="skuStatus"][@role="status"][@aria-live="polite"]')->length === 1, 'Selection instructions need an accessible live status.');
front_check(strpos($groupDom->query('//*[@id="skuHelp"]')->item(0)->textContent, '解除してから変更') !== false, 'Help must explain how to unlock unavailable combinations.');
front_check($groupDom->query('//*[@id="skuReset"][@type="button"]')->length === 1, 'Grouped selections need a whole-selection reset.');
$groupJson = $groupDom->query('//script[@id="skuData"][@type="application/json"]');
front_check($groupJson->length === 1, 'Grouped data must use one non-executable JSON script.');
$decoded = json_decode($groupJson->item(0)->textContent, true);
front_check($decoded === ['groups' => $grouped['sku_groups'], 'skus' => $grouped['skus']], 'JSON must preserve group order and complete SKU options.');
$visible = '';
foreach ($groupDom->query('//body//text()[not(ancestor::script) and not(ancestor::style)]') as $textNode) {
    $visible .= $textNode->textContent;
}
foreach ($grouped['skus'] as $groupSku) {
    front_check(strpos($visible, $groupSku['id']) === false, 'Internal SKU IDs must never appear as visible page text.');
}
front_check(strpos($visible, 'SKU番号') === false, 'The page must not label any SKU number.');
parse_str(parse_url($groupDom->query('//*[@id="inquiryBtn"]')->item(0)->getAttribute('href'), PHP_URL_QUERY), $groupMail);
front_check(strpos($groupMail['body'], 'SKU番号') === false, 'Initial grouped inquiry must not claim a SKU.');

// </script>、引用符、HTMLがグループ名・選択肢・SKU名に含まれても構造を壊さない。
$grouped['sku_groups'] = [
    ['name' => '色 </script> & "引用"', 'options' => [$hostile, 'BLACK']],
    ['name' => 'サイズ', 'options' => ['S', 'M']],
];
$grouped['skus'] = [
    ['id' => 'safe-001', 'name' => $hostile, 'price' => 0, 'options' => [$hostile, 'S']],
    ['id' => 'safe-002', 'name' => 'BLACK / M', 'price' => 2147483647, 'options' => ['BLACK', 'M']],
];
$fixture['data']['products'] = [$grouped];
$safeHtml = front_render_product($fixture);
$safeDom = front_document($safeHtml);
$safeJson = $safeDom->query('//script[@id="skuData"]')->item(0)->textContent;
front_check(strpos($safeJson, '<') === false && strpos($safeJson, '>') === false && strpos($safeJson, '&') === false, 'JSON data must hex-escape HTML metacharacters.');
front_check(strpos($safeJson, '\u0022') !== false && strpos($safeJson, '\u0027') !== false, 'JSON data must hex-escape both quote types.');
front_check(json_decode($safeJson, true) === ['groups' => $grouped['sku_groups'], 'skus' => $grouped['skus']], 'Hostile group and option text must round-trip through JSON.');
front_check($safeDom->query('//button[@data-group-index="0"][@data-option-index="0"]')->item(0)->textContent === $hostile, 'Option text must render literally.');
front_check($safeDom->query('//script[not(@type="application/json")][contains(text(), "alert(")]')->length === 0, 'Hostile option text must never become executable JavaScript.');
front_check($safeDom->query('//button[@data-option-index]//script | //button[@data-option-index]//b')->length === 0, 'Option text must never become nested HTML.');
front_check(strpos($safeDom->query('//*[@id="productPrice"]')->item(0)->textContent, '¥0 ～ ¥2,147,483,647') !== false, 'Grouped boundary prices must remain exact.');

$grouped['skus'][0]['options'] = ['UNKNOWN', 'S'];
$fixture['data']['products'] = [$grouped];
$invalidGroups = front_document(front_render_product($fixture));
front_check($invalidGroups->query('//*[@id="skuOptions"]')->length === 0, 'Invalid combinations must fall back without broken group controls.');
front_check($invalidGroups->query('//*[@id="dAttrs"]//button')->length === 4, 'Safe fallback must preserve legacy attribute behavior.');

echo "SKU frontend rendering: all flat, grouped and legacy assertions passed.\n";
