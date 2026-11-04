<?php
$root = sys_get_temp_dir() . '/shop-remote-' . bin2hex(random_bytes(8));
define('SHOP_BASE', $root);
require_once dirname(__DIR__) . '/lib/remote_image.php';
function remote_check($ok, $label) {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS: $label\n";
}
$error = '';
foreach (['http://127.0.0.1/x', 'https://[::1]/x', 'file:///etc/passwd', 'https://user:pass@example.com/a', 'https://example.com:8080/a', "https://example.com/a\r\nX:evil", 'https://localhost/x', 'http://2130706433/x', 'https://example.com/%0a'] as $url) {
    remote_check(remote_image_validate_url($url, $error) === false, 'reject unsafe URL');
}
remote_check(remote_image_validate_url('https://image.rakuten.co.jp/test/image.webp?x=1', $error) !== false, 'accept public domain URL syntax');
foreach (['127.0.0.1', '10.0.0.1', '169.254.169.254', '100.64.0.1', '198.18.0.1', '192.168.1.1', '224.0.0.1', '::1', 'fc00::1', '::ffff:127.0.0.1'] as $ip) {
    remote_check(!remote_image_is_public_ip($ip), 'reject special address');
}
remote_check(remote_image_is_public_ip('8.8.8.8'), 'allow public IPv4');
remote_check(remote_image_validate_dns_records([['type' => 'A', 'ip' => '8.8.8.8'], ['type' => 'AAAA', 'ipv6' => '::1']], $error) === false, 'reject mixed public/private DNS');
remote_check(remote_image_save_content('<html>not an image</html>', $error) === false, 'reject HTML');
remote_check(remote_image_save_content('<svg xmlns="http://www.w3.org/2000/svg"/>', $error) === false, 'reject SVG');
remote_check(remote_image_save_content(str_repeat('x', 8 * 1024 * 1024 + 1), $error) === false, 'reject oversized body');
remote_check(remote_image_download('https://example.com/a', $error, microtime(true) - 1) === false, 'expired budget makes no request');
$created = [];
try {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9AAAAABJRU5ErkJggg==');
    $saved = remote_image_save_content($png, $error);
    remote_check($saved !== false && substr($saved, -4) === '.png', 'detect real PNG and use local extension');
    $created[] = SHOP_BASE . '/' . $saved;
    remote_check(store_validate_product_image_path($saved), 'saved image accepted by existing store');
    if (function_exists('imagewebp')) {
        $im = imagecreatetruecolor(2, 2);
        ob_start(); imagewebp($im); $webp = ob_get_clean(); imagedestroy($im);
        $saved = remote_image_save_content($webp, $error);
        remote_check($saved !== false && substr($saved, -5) === '.webp', 'real WebP saved without conversion');
        $created[] = SHOP_BASE . '/' . $saved;
    }
} finally {
    foreach ($created as $file) {
        if (strpos($file, $root . '/images/products/remote_') === 0 && is_file($file)) { unlink($file); }
    }
    if (is_dir(UPLOAD_DIR)) { rmdir(UPLOAD_DIR); rmdir($root . '/images'); rmdir($root); }
}
