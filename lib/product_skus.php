<?php
/** 商品 SKU の検証と価格計算。store.php から読み込む。 */

/** POST の欠番インデックスも許容し、出力時は連続配列にする。 */
function store_sku_numeric_index($index) {
    return (is_int($index) && $index >= 0)
        || (is_string($index) && preg_match('/\A[0-9]+\z/', $index) === 1);
}

function store_sku_text_valid($text, $max) {
    return $text !== '' && @preg_match('//u', $text) === 1
        && (function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : preg_match_all('/./us', $text)) <= $max;
}

/** JSON の配列/オブジェクトを区別し、options のオブジェクトを配列へ誤変換しない。 */
function store_sku_decode_json_rows($json) {
    if (!is_string($json)) {
        return false;
    }
    $rows = json_decode($json);
    if (!is_array($rows)) {
        return false;
    }
    foreach ($rows as $index => $row) {
        if (!$row instanceof stdClass) {
            return false;
        }
        $rows[$index] = get_object_vars($row);
    }
    return $rows;
}

/** [{name, options:[string,...]},...] を検証する。全項目空欄の行だけ無視する。 */
function store_parse_sku_groups($rows, &$error) {
    $error = '';
    if (!is_array($rows)) {
        $error = 'format';
        return false;
    }
    $out = [];
    $names = [];
    foreach ($rows as $index => $row) {
        if (!store_sku_numeric_index($index) || !is_array($row)
            || ($row && array_keys($row) === range(0, count($row) - 1))) {
            $error = 'format';
            return false;
        }
        foreach ($row as $key => $value) {
            if ($key !== 'options' && (is_array($value) || is_object($value) || is_resource($value))) {
                $error = 'format';
                return false;
            }
        }
        if (array_key_exists('name', $row) && !is_string($row['name']) && !is_int($row['name'])) {
            $error = 'format';
            return false;
        }
        $name = trim((string)($row['name'] ?? ''));
        $rawOptions = array_key_exists('options', $row) ? $row['options'] : [];
        if (!is_array($rawOptions)) {
            $error = 'options';
            return false;
        }
        $options = [];
        foreach ($rawOptions as $optionIndex => $option) {
            if (!store_sku_numeric_index($optionIndex) || (!is_string($option) && !is_int($option))) {
                $error = 'options';
                return false;
            }
            $options[] = trim((string)$option);
        }
        if ($name === '' && !array_filter($options, function ($value) { return $value !== ''; })) {
            continue;
        }
        if (!store_sku_text_valid($name, 60)) {
            $error = 'name';
            return false;
        }
        if (in_array($name, $names, true)) {
            $error = 'duplicate';
            return false;
        }
        if (!$options || count($options) > 30) {
            $error = $options ? 'limit' : 'options';
            return false;
        }
        $seen = [];
        foreach ($options as $option) {
            if (!store_sku_text_valid($option, 100)) {
                $error = 'options';
                return false;
            }
            if (in_array($option, $seen, true)) {
                $error = 'duplicate';
                return false;
            }
            $seen[] = $option;
        }
        $names[] = $name;
        $out[] = ['name' => $name, 'options' => $options];
        if (count($out) > 3) {
            $error = 'limit';
            return false;
        }
    }
    return $out;
}

/** 不正な保存値は表示に使わない。保存時は別途厳密に検証する。 */
function store_product_sku_groups(array $product) {
    $error = '';
    $groups = store_parse_sku_groups($product['sku_groups'] ?? [], $error);
    return $groups === false ? [] : $groups;
}

function store_parse_skus($rows, &$error, $groups = []) {
    $error = '';
    $groups = store_parse_sku_groups($groups, $error);
    if ($groups === false) {
        $error = 'groups';
        return false;
    }
    if (!is_array($rows)) {
        $error = 'format';
        return false;
    }
    $out = [];
    $seen = [];
    $combinations = [];
    foreach ($rows as $index => $row) {
        if (!store_sku_numeric_index($index)) {
            $error = 'format';
            return false;
        }
        if (!is_array($row) || ($row && array_keys($row) === range(0, count($row) - 1))) {
            $error = 'format';
            return false;
        }
        foreach ($row as $key => $value) {
            if ($key !== 'options' && (is_array($value) || is_object($value) || is_resource($value))) {
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
        $options = array_key_exists('options', $row) ? $row['options'] : [];
        if (!is_array($options)) {
            $error = 'options';
            return false;
        }
        $normalizedOptions = [];
        foreach ($options as $optionIndex => $option) {
            if (!store_sku_numeric_index($optionIndex) || (!is_string($option) && !is_int($option))) {
                $error = 'options';
                return false;
            }
            $normalizedOptions[] = trim((string)$option);
        }
        $options = $normalizedOptions;
        if (!$groups && $options) {
            $error = 'options';
            return false;
        }
        if ($id === '' && $name === '' && $price === ''
            && !array_filter($options, function ($value) { return $value !== ''; })) {
            continue;
        }
        if ($groups) {
            if (count($options) !== count($groups)) {
                $error = 'options';
                return false;
            }
            foreach ($groups as $groupIndex => $group) {
                if (!in_array($options[$groupIndex], $group['options'], true)) {
                    $error = 'options';
                    return false;
                }
            }
            $combination = json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (isset($combinations[$combination])) {
                $error = 'combination';
                return false;
            }
            $combinations[$combination] = true;
            if ($name === '') {
                $name = implode(' / ', $options);
            }
        }
        if (!store_valid_id($id)) {
            $error = 'id';
            return false;
        }
        // 3グループ各100文字の自動生成名を収める。旧SKUは200文字のまま。
        if (!store_sku_text_valid($name, $groups ? 400 : 200)) {
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
        $sku = ['id' => $id, 'name' => $name, 'price' => (int)$price];
        if ($groups) {
            $sku['options'] = $options;
        }
        $out[] = $sku;
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
    $groups = store_parse_sku_groups($product['sku_groups'] ?? [], $error);
    if ($groups === false) {
        return [];
    }
    $skus = store_parse_skus($product['skus'], $error, $groups);
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
