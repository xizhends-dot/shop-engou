<?php
/**
 * shop 配置示例 — 复制为 config.php 后填写真实值（勿将含密码的 config.php 提交到公开仓库）
 */
return [
    'company_name_ja' => '遠豪合同会社',
    'company_name_en' => 'ENGOU LLC',
    'brand'           => 'KMEAG',
    'zipcode'         => '〒000-0000',
    'address_ja'      => '地址',
    'phone'           => '000-0000-0000',
    'email'           => 'info@example.com',
    'hours_ja'        => '月〜金 9:00 - 18:00',
    'established_ja'    => '2021年',
    'representative_ja' => '',
    'business_ja'       => '',
    'about_ja'          => '',
    'main_site_url' => 'https://engou.jp',
    'shop_site_url' => 'https://shop.engou.jp',

    // 请使用 password_hash('你的强密码', PASSWORD_DEFAULT) 生成的哈希值
    'admin_password' => '$2y$10$CHANGE_ME_USE_password_hash',

    'storage' => 'mysql',
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'shop_db_name',
        'user'    => 'shop_db_user',
        'pass'    => 'STRONG_DB_PASSWORD',
        'charset' => 'utf8mb4',
    ],
];
