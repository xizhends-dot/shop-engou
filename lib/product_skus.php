<?php
/** 商品 SKU の検証と価格計算。store.php から読み込む。 */

function store_parse_skus($rows, &$error) {
    $error = '';
    if (!is_array($rows)) {
        $error = 'format';
        return false;
    }
    $out = [];
    $seen = [];
    foreach ($rows as $index => $row) {
        if (!is_int($index) || $index < 0) {
            $error = 'format';
            return false;
        }
        if (!is_array($row) || ($row && array_keys($row) === range(0, count($row) - 1))) {
            $error = 'format';
            return false;
        }
        foreach ($row as $value) {
            if (is_array($value) || is_object($value)) {
                $error = 'format';
                return false;
            }
        }
        foreach (['id', 'name', 'price'] as $key) {
            if (isset($row[$key]) && !is_string($row[$key]) && !is_int($row[$key])) {
                $error = 'format';
                return false;
            }
        }
        $id = trim((string)($row['id'] ?? ''));
        $name = trim((string)($row['name'] ?? ''));
        $price = trim((string)($row['price'] ?? ''));
        if ($id === '' && $name === '' && $price === '') {
            continue;
        }
        if (!store_valid_id($id)) {
            $error = 'id';
            return false;
        }
        if ($name === '' || @preg_match('//u', $name) !== 1
            || (function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : preg_match_all('/./us', $name)) > 200) {
            $error = 'name';
            return false;
        }
        if (!store_valid_price($price)) {
            $error = 'price';
            return false;
        }
        $folded = strtolower($id);
        if (isset($seen[$folded])) {
            $error = 'duplicate';
            return false;
        }
        $seen[$folded] = true;
        $out[] = ['id' => $id, 'name' => $name, 'price' => (int)$price];
        if (count($out) > 100) {
            $error = 'limit';
            return false;
        }
    }
    return $out;
}

/** 不正な保存値は表示に使わない。保存時は別途厳密に検証する。 */
function store_product_skus(array $product) {
    if (!isset($product['skus'])) {
        return [];
    }
    $error = '';
    $skus = store_parse_skus($product['skus'], $error);
    return $skus === false ? [] : $skus;
}

function store_product_prices(array $product) {
    $skus = store_product_skus($product);
    if ($skus) {
        $prices = array_column($skus, 'price');
        $min = min($prices);
        $max = max($prices);
        return ['price' => $min, 'price_max' => $max > $min ? $max : null];
    }
    return ['price' => $product['price'] ?? 0, 'price_max' => $product['price_max'] ?? null];
}
