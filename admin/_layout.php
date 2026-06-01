<?php
/** 管理画面の共通レイアウト */

/** 現在の admin スクリプト名（拡張子なし） */
function adm_current_page() {
    return basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
}

/** ナビリンク1件 */
function adm_nav_link($href, $icon, $label, array $activeOn) {
    $cur   = adm_current_page();
    $active = in_array($cur, $activeOn, true) ? ' active' : '';
    $iconHtml = $icon !== '' ? '<i class="fa-solid ' . htmlspecialchars($icon) . '" aria-hidden="true"></i> ' : '';
    return '<a href="' . htmlspecialchars($href) . '" class="adm-nav-item' . $active . '">'
        . $iconHtml . '<span>' . htmlspecialchars($label) . '</span></a>';
}

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
    <nav class="adm-links" aria-label="管理メニュー">
      <div class="adm-nav-group">
        <span class="adm-nav-label">商品</span>
        <div class="adm-nav-group-links">
          <?= adm_nav_link('index.php', 'fa-box', '商品一覧', ['index', 'delete']) ?>
          <?= adm_nav_link('edit.php', 'fa-plus', '新規追加', ['edit']) ?>
          <?= adm_nav_link('featured.php', 'fa-star', 'おすすめ', ['featured']) ?>
          <?= adm_nav_link('categories.php', 'fa-tags', 'カテゴリ', ['categories']) ?>
          <?= adm_nav_link('media.php', 'fa-images', '画像管理', ['media']) ?>
          <?= adm_nav_link('import.php', 'fa-file-excel', 'Excel取込', ['import']) ?>
        </div>
      </div>
      <div class="adm-nav-group">
        <span class="adm-nav-label">サイト</span>
        <div class="adm-nav-group-links">
          <?= adm_nav_link('banners.php', 'fa-panorama', 'バナー', ['banners']) ?>
        </div>
      </div>
      <div class="adm-nav-group adm-nav-group--test">
        <span class="adm-nav-label">テスト</span>
        <div class="adm-nav-group-links">
          <?= adm_nav_link('check.php', 'fa-stethoscope', '保存チェック', ['check']) ?>
          <?= adm_nav_link('migrate.php', 'fa-database', 'DB移行', ['migrate']) ?>
        </div>
      </div>
      <div class="adm-nav-group adm-nav-group--util">
        <div class="adm-nav-group-links">
          <a href="../index.php" target="_blank" rel="noopener" class="adm-nav-item"><i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i><span>サイトを見る</span></a>
          <a href="logout.php" class="adm-nav-item adm-logout"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i><span>ログアウト</span></a>
        </div>
      </div>
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
