<?php
/** 管理画面の共通レイアウト */
function admin_head($title, $showNav = true) {
    global $config;
    ?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#14142e">
<title><?= htmlspecialchars($title) ?> | 管理画面</title>
<link rel="icon" href="../favicon.svg" type="image/svg+xml">
<link rel="alternate icon" href="../favicon.svg">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="admin.css">
</head>
<body>
<?php if ($showNav): ?>
<header class="adm-nav">
  <div class="adm-nav-inner">
    <a href="index.php" class="adm-brand"><?= htmlspecialchars($config['company_name_ja']) ?> <span>SHOP 管理</span></a>
    <nav class="adm-links">
      <a href="index.php"><i class="fa-solid fa-box"></i> 商品一覧</a>
      <a href="edit.php"><i class="fa-solid fa-plus"></i> 新規追加</a>
      <a href="featured.php"><i class="fa-solid fa-star"></i> おすすめ</a>
      <a href="categories.php"><i class="fa-solid fa-tags"></i> カテゴリ</a>
      <a href="media.php"><i class="fa-solid fa-images"></i> 画像管理</a>
      <a href="banners.php"><i class="fa-solid fa-panorama"></i> バナー管理</a>
      <a href="import.php"><i class="fa-solid fa-file-csv"></i> CSV取込</a>
      <a href="../index.php" target="_blank"><i class="fa-solid fa-up-right-from-square"></i> サイトを見る</a>
      <a href="logout.php" class="adm-logout"><i class="fa-solid fa-right-from-bracket"></i> ログアウト</a>
    </nav>
  </div>
</header>
<?php endif; ?>
<main class="adm-main">
<?php
    $flash = function_exists('take_flash') ? take_flash() : null;
    if ($flash) {
        echo '<div class="adm-flash adm-flash-' . htmlspecialchars($flash['type']) . '">' . htmlspecialchars($flash['msg']) . '</div>';
    }
}

function admin_foot() {
    ?>
</main>
</body>
</html>
<?php
}
