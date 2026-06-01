<?php
/**
 * 商品データのローダー（後方互換）
 * ------------------------------------------------------------------
 * 実データは data/products.json に保存され、管理画面（admin/）から編集します。
 * このファイルは従来どおり ['categories' => ..., 'products' => ...] を返します。
 */
require_once __DIR__ . '/lib/store.php';
return store_load();
