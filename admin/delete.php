<?php
require_once __DIR__ . '/auth.php';
admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? '')) {
    set_flash(__('flash.invalid_request'), 'err');
    header('Location: products.php');
    exit;
}

$returnPage = max(1, (int)($_POST['page'] ?? 1));

$idSet = [];
if (isset($_POST['ids']) && is_array($_POST['ids'])) {
    foreach ($_POST['ids'] as $raw) {
        $id = trim((string)$raw);
        if ($id !== '' && store_valid_id($id)) {
            $idSet[$id] = true;
        }
    }
} elseif (trim((string)($_POST['id'] ?? '')) !== '') {
    $id = trim((string)$_POST['id']);
    if (store_valid_id($id)) {
        $idSet[$id] = true;
    }
}

if ($idSet === []) {
    set_flash(__('flash.products_batch_none'), 'err');
    header('Location: products.php?page=' . $returnPage);
    exit;
}

$data    = store_load();
$deleted = 0;
$remain  = [];

foreach ($data['products'] as $p) {
    $pid = (string)($p['id'] ?? '');
    if ($pid !== '' && isset($idSet[$pid])) {
        foreach (($p['images'] ?? []) as $img) {
            store_delete_product_image($img);
        }
        $deleted++;
    } else {
        $remain[] = $p;
    }
}

if ($deleted === 0) {
    set_flash(__('flash.product_not_found'), 'err');
    header('Location: products.php?page=' . $returnPage);
    exit;
}

$data['products'] = $remain;
$feat             = array_values(array_filter(featured_load(), function ($x) use ($idSet) {
    return !isset($idSet[$x]);
}));
featured_save($feat);

if (store_save($data)) {
    if ($deleted === 1 && count($idSet) === 1) {
        set_flash(__('flash.product_deleted'));
    } else {
        set_flash(__('flash.products_deleted_batch', ['n' => $deleted]));
    }
} else {
    set_flash(store_save_error_message(), 'err');
}

$perPage  = 20;
$maxPage  = max(1, (int)ceil(count($remain) / $perPage));
$returnPage = min($returnPage, $maxPage);

header('Location: products.php?page=' . $returnPage);
exit;
