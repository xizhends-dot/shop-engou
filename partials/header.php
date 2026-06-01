<?php
/**
 * 共通ヘッダー（ナビゲーション含む）
 * 使い方: ページ冒頭で $config を読み込み、$page_title / $active を設定してから require する。
 *   $active … 'shop' | 'company' | 'contact' など、現在ページのハイライト用
 */
if (!isset($config)) { $config = require __DIR__ . '/../config.php'; }
$active = $active ?? '';
$page_title = $page_title ?? ($config['company_name_ja'] . ' SHOP');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?></title>
<meta name="description" content="<?= $config['company_name_ja'] ?>（<?= $config['company_name_en'] ?>）公式オンラインショップ。美容家電・生活家電・キッチン家電を厳選してお届けします。">
<meta property="og:title" content="<?= htmlspecialchars($page_title) ?>">
<meta property="og:type" content="website">
<meta name="theme-color" content="#14142e">
<link rel="icon" href="favicon.svg" type="image/svg+xml">
<link rel="alternate icon" href="favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;700;800&family=Noto+Sans+SC:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
</head>
<body>

<!-- NAV（主站 engou.jp と連携） -->
<nav class="nav" id="nav">
  <div class="nav-inner">
    <a href="index.php" class="nav-logo">
      <div class="logo-group">
        <span class="logo-main"><?= $config['company_name_ja'] ?></span>
        <span class="logo-sub"><?= $config['company_name_en'] ?> SHOP</span>
      </div>
      <span class="logo-divider"></span>
      <span class="logo-brand"><?= $config['brand'] ?></span>
    </a>
    <div class="nav-links" id="navLinks">
      <a href="index.php#top">ホーム</a>
      <a href="list.php" class="<?= $active === 'shop' ? 'active' : '' ?>">商品一覧</a>
      <a href="about.php" class="<?= $active === 'about' ? 'active' : '' ?>">遠豪について</a>
      <a href="contact.php" class="<?= $active === 'contact' ? 'active' : '' ?>">お問い合わせ</a>
      <a href="<?= htmlspecialchars($config['main_site_url']) ?>" class="nav-home-link">
        <i class="fa-solid fa-arrow-up-right-from-square"></i><span>本社サイト</span>
      </a>
    </div>
    <div class="hamburger" id="hamburger" aria-label="メニュー" role="button" tabindex="0">
      <span></span><span></span><span></span>
    </div>
  </div>
</nav>

<main>
