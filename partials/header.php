<?php
/**
 * 共通ヘッダー（ナビゲーション含む）
 * 使い方: ページ冒頭で $config を読み込み、$page_title / $active を設定してから require する。
 *   $active … 'shop' | 'company' | 'contact' など、現在ページのハイライト用
 */
if (!isset($config)) { $config = require __DIR__ . '/../config.php'; }
if (!function_exists('shop_e')) { require_once __DIR__ . '/../lib/store.php'; }
$active = $active ?? '';
$page_title = $page_title ?? ($config['company_name_ja'] . ' SHOP');
$catalog_search_query = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$catalog_stylesheet = __DIR__ . '/../storefront-content.css';
$catalog_style_version = is_file($catalog_stylesheet) ? (string)filemtime($catalog_stylesheet) : '1';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?></title>
<meta name="description" content="<?= shop_e($config['company_name_ja']) ?>（<?= shop_e($config['company_name_en']) ?>）公式オンラインショップ。美容家電・生活家電・キッチン家電を厳選してお届けします。">
<meta property="og:title" content="<?= htmlspecialchars($page_title) ?>">
<meta property="og:type" content="website">
<meta name="theme-color" content="#14142e">
<link rel="icon" href="favicon.svg" type="image/svg+xml">
<link rel="alternate icon" href="favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;700;800&family=Noto+Sans+SC:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="storefront-content.css?v=<?= shop_e($catalog_style_version) ?>">
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
    <form class="catalog-search" action="list.php" method="get" role="search" aria-label="商品検索">
      <label class="catalog-search-label" for="catalog-search-query">商品名・商品番号で検索</label>
      <div class="catalog-search-control">
        <input id="catalog-search-query" type="search" name="q" value="<?= shop_e($catalog_search_query) ?>" placeholder="商品名・商品番号を入力">
        <button type="submit" aria-label="検索"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg></button>
      </div>
    </form>
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

<div class="catalog-header-tools">
  <div class="container">
    <p class="catalog-utility-note">商品情報・税込価格をご覧いただけます</p>
    <nav class="catalog-content-nav" aria-label="商品選びのご案内">
      <a href="index.php#categories">カテゴリーから探す</a>
      <a href="index.php#featured">おすすめ商品</a>
      <a href="index.php#shopping-guide">ご利用ガイド</a>
      <a href="index.php#faq">よくあるご質問</a>
      <a href="contact.php">商品について相談する</a>
    </nav>
  </div>
</div>

<main>
