<?php
/** 管理画面の共通レイアウト */

function adm_current_page() {
    return basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
}

/** 二级菜单：下拉组 */
function adm_nav_dropdown($label, $icon, array $items, $modifier = '') {
    $cur = adm_current_page();
    $hasActive = false;
    foreach ($items as $item) {
        if (in_array($cur, $item[3], true)) {
            $hasActive = true;
            break;
        }
    }
    $modClass = $modifier !== '' ? ' ' . htmlspecialchars($modifier) : '';
    $openClass = $hasActive ? ' has-active' : '';
    $iconHtml = $icon !== '' ? '<i class="fa-solid ' . htmlspecialchars($icon) . '" aria-hidden="true"></i>' : '';

    $html = '<div class="adm-dropdown' . $modClass . $openClass . '">';
    $html .= '<button type="button" class="adm-nav-top' . ($hasActive ? ' active' : '') . '" aria-haspopup="true" aria-expanded="' . ($hasActive ? 'true' : 'false') . '">';
    $html .= $iconHtml . '<span>' . htmlspecialchars($label) . '</span>';
    $html .= '<i class="fa-solid fa-chevron-down adm-chevron" aria-hidden="true"></i>';
    $html .= '</button>';
    $html .= '<div class="adm-submenu" role="menu">';
    foreach ($items as $item) {
        [$href, $itemIcon, $itemLabel, $activeOn] = $item;
        $active = in_array($cur, $activeOn, true) ? ' active' : '';
        $itemIconHtml = $itemIcon !== '' ? '<i class="fa-solid ' . htmlspecialchars($itemIcon) . '" aria-hidden="true"></i>' : '';
        $html .= '<a href="' . htmlspecialchars($href) . '" class="adm-submenu-item' . $active . '" role="menuitem">'
            . $itemIconHtml . '<span>' . htmlspecialchars($itemLabel) . '</span></a>';
    }
    $html .= '</div></div>';
    return $html;
}

function adm_nav_top_link($href, $icon, $label, $extraClass = '', $attrs = '') {
    $iconHtml = $icon !== '' ? '<i class="fa-solid ' . htmlspecialchars($icon) . '" aria-hidden="true"></i>' : '';
    $cls = 'adm-nav-top' . ($extraClass !== '' ? ' ' . htmlspecialchars($extraClass) : '');
    return '<a href="' . htmlspecialchars($href) . '" class="' . $cls . '"' . $attrs . '>' . $iconHtml
        . '<span>' . htmlspecialchars($label) . '</span></a>';
}

function admin_head($title, $showNav = true) {
    global $config;

    $productItems = [
        ['index.php', 'fa-box', '商品一覧', ['index', 'delete']],
        ['edit.php', 'fa-plus', '新規追加', ['edit']],
        ['featured.php', 'fa-star', 'おすすめ', ['featured']],
        ['categories.php', 'fa-tags', 'カテゴリ', ['categories']],
        ['media.php', 'fa-images', '画像管理', ['media']],
        ['import.php', 'fa-file-excel', 'Excel取込', ['import']],
    ];
    $siteItems = [
        ['banners.php', 'fa-panorama', 'バナー管理', ['banners']],
    ];
    $testItems = [
        ['check.php', 'fa-stethoscope', '保存チェック', ['check']],
        ['migrate.php', 'fa-database', 'DB移行', ['migrate']],
    ];
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
      <?= adm_nav_dropdown('商品', 'fa-box', $productItems) ?>
      <?= adm_nav_dropdown('サイト', 'fa-globe', $siteItems) ?>
      <?= adm_nav_dropdown('テスト', 'fa-flask', $testItems, 'adm-dropdown--test') ?>
      <div class="adm-nav-util">
        <?= adm_nav_top_link('../index.php', 'fa-up-right-from-square', 'サイトを見る') ?>
        <?= adm_nav_top_link('logout.php', 'fa-right-from-bracket', 'ログアウト', 'adm-logout') ?>
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
<script>
(function () {
  var dropdowns = document.querySelectorAll('.adm-dropdown');
  if (!dropdowns.length) return;

  dropdowns.forEach(function (dd) {
    var btn = dd.querySelector('.adm-nav-top');
    if (!btn || btn.tagName !== 'BUTTON') return;

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = dd.classList.contains('is-open');
      dropdowns.forEach(function (other) {
        if (other !== dd) other.classList.remove('is-open');
        var b = other.querySelector('.adm-nav-top');
        if (b && b.tagName === 'BUTTON') b.setAttribute('aria-expanded', 'false');
      });
      dd.classList.toggle('is-open', !open);
      btn.setAttribute('aria-expanded', !open ? 'true' : 'false');
    });
  });

  document.addEventListener('click', function () {
    dropdowns.forEach(function (dd) {
      if (!dd.classList.contains('has-active')) {
        dd.classList.remove('is-open');
        var b = dd.querySelector('.adm-nav-top');
        if (b && b.tagName === 'BUTTON') b.setAttribute('aria-expanded', 'false');
      }
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      dropdowns.forEach(function (dd) {
        if (!dd.classList.contains('has-active')) dd.classList.remove('is-open');
      });
    }
  });
})();
</script>
</body>
</html>
<?php
}
