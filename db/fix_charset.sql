-- ============================================================
-- 文字化け・1366 エラー対策：DB / テーブルを utf8mb4 に統一
-- phpMyAdmin で shop_engou_jp を選択してから実行してください。
-- ============================================================

ALTER DATABASE `shop_engou_jp` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `categories` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `products` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `product_images` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
