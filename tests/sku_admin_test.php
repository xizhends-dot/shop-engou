<?php
// 商品編集画面の実際のPOST処理を隔離JSONストアで検証する。
$case = $argv[1] ?? 'save';
$fixtureRoot = sys_get_temp_dir() . '/shop-sku-admin-' . bin2hex(random_bytes(8));
define('SHOP_BASE', $fixtureRoot);
mkdir($fixtureRoot . '/data', 0775, true);
mkdir($fixtureRoot . '/images/products', 0775, true);
file_put_contents($fixtureRoot . '/config.php', "<?php return ['storage' => 'json'];");
require_once dirname(__DIR__) . '/lib/store.php';
session_save_path($fixtureRoot);
admin_session_start();
$_SESSION['engou_admin_ok'] = true;
$_SESSION['csrf'] = 'test-csrf';
$_SESSION['admin_lang'] = 'zh';
$sessionFile = $fixtureRoot . '/sess_' . session_id();
$skus = [['id' => 'A', 'name' => 'ホワイト / S', 'price' => 1000], ['id' => 'B', 'name' => 'ブラック / L', 'price' => 1500]];
$original = ['id' => 'SKU-TEST', 'name' => 'Test', 'category' => 'beauty', 'price' => 1000, 'price_max' => 1500, 'skus' => $skus, 'images' => [], 'attributes' => []];
$fixture = ['categories' => ['beauty' => ['name' => 'Test', 'icon' => 'fa-box']], 'products' => [$original]];
file_put_contents(STORE_FILE, json_encode($fixture));
$_GET = ['id' => 'SKU-TEST'];
$_SERVER['SCRIPT_NAME'] = '/admin/edit.php';
$_SERVER['REQUEST_URI'] = '/admin/edit.php?id=SKU-TEST';
$_SERVER['REQUEST_METHOD'] = $case === 'render' ? 'GET' : 'POST';
$_POST = ['csrf' => 'test-csrf', 'edit_id' => 'SKU-TEST', 'id' => 'SKU-TEST', 'name' => 'Changed', 'category' => 'beauty', 'price' => '', 'price_max' => '', 'skus' => $skus];
if ($case === 'gap') { unset($_POST['skus'][0]); }
if ($case === 'duplicate') { $_POST['skus'][1]['id'] = 'a'; }
if ($case === 'invalid') { $_POST['skus'][0]['price'] = '-1'; }
if ($case === 'malformed') { $_POST['skus'][0]['price'] = ['bad']; }
if ($case === 'csrf') { $_POST['csrf'] = 'wrong'; }
if ($case === 'remove') { unset($_POST['skus']); $_POST['price'] = '2000'; $_POST['price_max'] = ''; }
if ($case === 'zero') { $_POST['skus'][0]['price'] = '0'; $_POST['skus'][1]['price'] = '0'; }
$bufferLevel = ob_get_level();
ob_start();
register_shutdown_function(function () use ($case, $fixtureRoot, $sessionFile, $original, $bufferLevel) {
    $html = ob_get_level() > $bufferLevel ? ob_get_clean() : '';
    $saved = json_decode(file_get_contents(STORE_FILE), true)['products'][0];
    $passed = true;
    if ($case === 'save') { $passed = count($saved['skus']) === 2 && $saved['price'] === 1000 && $saved['price_max'] === 1500 && $saved['name'] === 'Changed'; }
    elseif ($case === 'gap') { $passed = count($saved['skus']) === 1 && $saved['skus'][0]['id'] === 'B' && $saved['price'] === 1500 && $saved['price_max'] === null; }
    elseif ($case === 'remove') { $passed = $saved['skus'] === [] && $saved['price'] === 2000 && $saved['price_max'] === null; }
    elseif ($case === 'zero') { $passed = count($saved['skus']) === 2 && $saved['price'] === 0 && $saved['price_max'] === null; }
    elseif ($case !== 'render') { $passed = $saved === $original && strpos($html, 'adm-flash-err') !== false && strpos($html, 'id="skuRows"') !== false; }
    session_write_close();
    // 既知の隔離ファイルのみ削除する。
    foreach ([$sessionFile, STORE_FILE, $fixtureRoot . '/config.php'] as $file) {
        if (strpos($file, $fixtureRoot . '/') === 0 && is_file($file)) { unlink($file); }
    }
    rmdir($fixtureRoot . '/data'); rmdir($fixtureRoot . '/images/products'); rmdir($fixtureRoot . '/images'); rmdir($fixtureRoot);
    if (!$passed) { fwrite(STDERR, 'FAIL: admin SKU ' . $case . "\n"); exit(1); }
    if ($case === 'render') {
        $base = 'file:///' . str_replace('\\', '/', dirname(__DIR__)) . '/admin/';
        echo str_replace('<head>', '<head><base href="' . htmlspecialchars($base) . '">', $html);
    } else { echo 'PASS: admin SKU ' . $case . "\n"; }
});
require dirname(__DIR__) . '/admin/edit.php';
