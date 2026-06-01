-- ============================================================
-- shop.engou.jp — MySQL スキーマ + 初期データ
-- 文字コード: utf8mb4 / エンジン: InnoDB
-- phpMyAdmin で対象データベースを選択してから本ファイルをインポートしてください。
-- （config.php の 'db' > 'name' と同じデータベースに作成すること）
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+09:00';

-- 既存DBに列を後から追加する場合（初回は不要）:
--   ALTER TABLE `products` ADD COLUMN `attributes` TEXT NULL AFTER `description`;
--   ALTER TABLE `products` ADD COLUMN `rating` DECIMAL(2,1) NOT NULL DEFAULT 0 AFTER `attributes`;
--   ALTER TABLE `products` ADD COLUMN `reviews` INT NOT NULL DEFAULT 0 AFTER `rating`;

-- ---- カテゴリ ----
CREATE TABLE IF NOT EXISTS `categories` (
  `slug` VARCHAR(64)  NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  `icon` VARCHAR(64)  NOT NULL DEFAULT 'fa-tag',
  `sort` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- 商品 ----
CREATE TABLE IF NOT EXISTS `products` (
  `id`          VARCHAR(64)  NOT NULL,            -- 商品番号（自由設定）
  `category`    VARCHAR(64)  NOT NULL,
  `icon`        VARCHAR(64)  NOT NULL DEFAULT 'fa-box',
  `accent`      VARCHAR(16)  NOT NULL DEFAULT '#DEF13F',
  `name`        VARCHAR(255) NOT NULL,
  `tag`         VARCHAR(255) NOT NULL DEFAULT '',
  `price`       INT          NOT NULL DEFAULT 0,
  `badge`       VARCHAR(64)  NOT NULL DEFAULT '',
  `description` TEXT         NULL,
  `attributes`  TEXT         NULL,            -- 商品属性（カラー等）JSON文字列
  `rating`      DECIMAL(2,1) NOT NULL DEFAULT 0,  -- 評価（0〜5、0.5刻み可）
  `reviews`     INT          NOT NULL DEFAULT 0,  -- 評価数
  `sort`        INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- 商品画像（1商品に複数枚・順序つき）----
CREATE TABLE IF NOT EXISTS `product_images` (
  `id`         INT          NOT NULL AUTO_INCREMENT,
  `product_id` VARCHAR(64)  NOT NULL,
  `path`       VARCHAR(255) NOT NULL,
  `sort`       INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  CONSTRAINT `fk_img_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 初期データ（デモ商品。既に admin/migrate.php で取り込む場合は不要）
-- ============================================================

INSERT INTO `categories` (`slug`, `name`, `icon`, `sort`) VALUES
('beauty',  '美容家電', 'fa-wand-magic-sparkles', 0),
('home',    '生活家電', 'fa-house-chimney',       1),
('kitchen', 'キッチン', 'fa-utensils',            2)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `icon`=VALUES(`icon`), `sort`=VALUES(`sort`);

INSERT INTO `products` (`id`, `category`, `icon`, `accent`, `name`, `tag`, `price`, `badge`, `description`, `sort`) VALUES
('nano-shower', 'beauty', 'fa-shower', '#DEF13F', 'マイクロナノバブル シャワーヘッド', '節水・高洗浄力・美肌美髪', 4980, '人気No.1', 'マイクロナノバブルが毛穴の奥の汚れまでやさしく除去。6段階の水流モードを搭載し、節水しながら高い洗浄力と保湿効果を実現します。美肌・美髪・美容ケアを毎日のシャワータイムで。工具不要で簡単に取り付けでき、G1/2の標準規格に対応しています。', 0),
('ems-beauty', 'beauty', 'fa-bolt', '#9be15d', 'EMS 美顔器 リフトケア', 'EMS・RF・LED 多機能', 12800, '', 'EMS微電流・RF温熱・LED光ケアを1台に集約した多機能美顔器。自宅で手軽にハリのある肌へ。充電式コードレスで、毎日のスキンケアに取り入れやすい設計です。5段階の強度調整、防水設計でお風呂でもご使用いただけます。', 1),
('ion-dryer', 'beauty', 'fa-wind', '#7dd3fc', '高速ドライヤー マイナスイオン', '大風量・速乾・うるおいケア', 8980, 'NEW', '大風量モーターで髪を素早く乾かしながら、マイナスイオンで毛先までうるおいのある仕上がりに。温冷切り替えと軽量ボディ（約350g）で、毎日のヘアケアを快適に。静電気を抑え、まとまりのある髪へ導きます。', 2),
('sonic-toothbrush', 'beauty', 'fa-tooth', '#a5f3fc', '音波振動 電動歯ブラシ', '高速振動・5モード・長持ちバッテリー', 3680, '', '毎分約38,000回の音波振動で歯垢を効果的に除去。クリーン・ホワイト・ケアなど5つのモードで歯ぐきにやさしくケア。1回の充電で約30日間使用できる省エネ設計。IPX7防水で丸洗いも可能です。', 3),
('cordless-vacuum', 'home', 'fa-broom', '#fca5a5', 'コードレス スティック掃除機', '軽量・強力吸引・コードレス', 15800, '', '強力なサイクロン吸引と軽量ボディを両立したコードレス掃除機。スティックからハンディへ2WAYで変形でき、床から机上、車内まで一台で対応。最大約45分の連続運転、水洗い可能なフィルターでお手入れも簡単です。', 4),
('aroma-humidifier', 'home', 'fa-droplet', '#93c5fd', 'アロマ対応 超音波加湿器', '静音・大容量・LEDライト', 4280, '', '超音波ミストでお部屋をうるおいで満たす加湿器。アロマオイル対応でリラックス空間を演出。大容量約2.5Lタンクと静音設計（約30dB）で、寝室やオフィスでも快適に。7色グラデーションのLEDライト付きです。', 5),
('temp-kettle', 'kitchen', 'fa-mug-hot', '#fcd34d', '温度調節 電気ケトル', '1℃単位・保温・急速沸騰', 6480, '', 'コーヒーや緑茶、ベビーミルクまで、用途に合わせて1℃単位（40〜100℃）で温度設定が可能。最大60分の保温機能付きで、いつでも適温の一杯を。急速沸騰、空焚き防止・自動オフ機能を搭載した安心設計です。', 6),
('hand-blender', 'kitchen', 'fa-blender', '#fdba74', 'ハンディ ブレンダー 多機能', '混ぜる・刻む・泡立て 1台4役', 5480, '', 'ブレンド・チョップ・ホイップ・つぶしの1台4役。離乳食づくりからスムージー、スープまで幅広く活躍。無段階のスピード調整、アタッチメントは取り外して丸洗いでき、お手入れも簡単。静音＆低振動モーターを採用しています。', 7)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);
