<?php
/** 商品番号・商品名の部分一致検索（両方指定した場合は AND）。 */
function product_search(array $products, $id, $name) {
    $contains = function ($value, $query) {
        if ($query === '') { return true; }
        return function_exists('mb_stripos')
            ? mb_stripos((string)$value, $query, 0, 'UTF-8') !== false
            : stripos((string)$value, $query) !== false;
    };
    return array_values(array_filter($products, function ($product) use ($id, $name, $contains) {
        return $contains($product['id'] ?? '', $id) && $contains($product['name'] ?? '', $name);
    }));
}

/** 配列などの不正な GET 値は検索条件として使用しない。 */
function product_search_query(array $query, $key) {
    return isset($query[$key]) && is_string($query[$key]) ? trim($query[$key]) : '';
}

function product_search_page_url($page, $id, $name) {
    return 'products.php?' . http_build_query(['page' => $page, 'search_id' => $id, 'search_name' => $name]);
}
