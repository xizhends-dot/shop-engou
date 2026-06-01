<?php
require_once __DIR__ . '/auth.php';
admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? '')) {
    set_flash('不正なリクエストです。', 'err');
    header('Location: products.php');
    exit;
}

$id = (string)($_POST['id'] ?? '');
$data = store_load();
$idx = store_find_index($data['products'], $id);

if ($idx >= 0) {
    // 商品に紐づく画像を物理削除
    foreach (($data['products'][$idx]['images'] ?? []) as $img) {
        store_delete_image($img);
    }
    array_splice($data['products'], $idx, 1);
    // おすすめ設定からも除外
    $feat = array_values(array_filter(featured_load(), function ($x) use ($id) { return $x !== $id; }));
    featured_save($feat);
    if (store_save($data)) {
        set_flash('商品を削除しました。');
    } else {
        set_flash(store_save_error_message(), 'err');
    }
} else {
    set_flash('対象の商品が見つかりませんでした。', 'err');
}

header('Location: products.php');
exit;
