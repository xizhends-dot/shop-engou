<?php
/** 管理画面の共通レイアウト */

function adm_current_page() {
    return basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
}

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

function adm_nav_top_link($href, $icon, $label, $extraClass = '', $attrs = '', array $activeOn = null) {
    $cur = adm_current_page();
    if ($activeOn === null) {
        $activeOn = [basename($href, '.php')];
    }
    $active = in_array($cur, $activeOn, true) ? ' active' : '';
    $iconHtml = $icon !== '' ? '<i class="fa-solid ' . htmlspecialchars($icon) . '" aria-hidden="true"></i>' : '';
    $cls = 'adm-nav-top' . $active . ($extraClass !== '' ? ' ' . htmlspecialchars($extraClass) : '');
    return '<a href="' . htmlspecialchars($href) . '" class="' . $cls . '"' . $attrs . '>' . $iconHtml
        . '<span>' . htmlspecialchars($label) . '</span></a>';
}

function admin_head($title, $showNav = true) {
    global $config;

    $productItems = [
        ['products.php', 'fa-list', __('nav.product_list'), ['products', 'delete']],
        ['edit.php', 'fa-plus', __('nav.product_add'), ['edit']],
        ['featured.php', 'fa-star', __('nav.featured'), ['featured']],
        ['categories.php', 'fa-tags', __('nav.categories'), ['categories']],
        ['media.php', 'fa-images', __('nav.media'), ['media']],
        ['import.php', 'fa-file-excel', __('nav.import'), ['import']],
    ];
    $siteItems = [
        ['banners.php', 'fa-panorama', __('nav.banners'), ['banners']],
    ];
    $testItems = [
        ['check.php', 'fa-stethoscope', __('nav.check'), ['check']],
        ['migrate.php', 'fa-database', __('nav.migrate'), ['migrate']],
    ];
    $htmlLang = __('meta.html_lang');
    ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($htmlLang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#14142e">
<title><?= htmlspecialchars($title) ?> | <?= htmlspecialchars(__('meta.admin_suffix')) ?></title>
<link rel="icon" href="../favicon.svg" type="image/svg+xml">
<link rel="alternate icon" href="../favicon.svg">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="admin.css">
</head>
<body>
<?php if ($showNav): ?>
<header class="adm-nav">
  <div class="adm-nav-inner">
    <a href="index.php" class="adm-brand"><?= htmlspecialchars($config['company_name_ja']) ?> <span><?= htmlspecialchars(__('nav.brand_suffix')) ?></span></a>
    <nav class="adm-links" aria-label="<?= htmlspecialchars(__('nav.menu')) ?>">
      <?= adm_nav_top_link('index.php', 'fa-gauge-high', __('nav.console'), '', '', ['index']) ?>
      <?= adm_nav_top_link('guide.php', 'fa-book', __('nav.guide'), '', '', ['guide']) ?>
      <?= adm_nav_dropdown(__('nav.products'), 'fa-box', $productItems) ?>
      <?= adm_nav_dropdown(__('nav.site'), 'fa-globe', $siteItems) ?>
      <?= adm_nav_dropdown(__('nav.test'), 'fa-flask', $testItems, 'adm-dropdown--test') ?>
      <?= admin_lang_switcher_html() ?>
      <div class="adm-nav-util">
        <?= adm_nav_top_link('../index.php', 'fa-up-right-from-square', __('nav.view_site'), '', ' target="_blank" rel="noopener"') ?>
        <form method="post" action="logout.php" class="adm-logout-form">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <button type="submit" class="adm-nav-top adm-logout"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i><span><?= htmlspecialchars(__('nav.logout')) ?></span></button>
        </form>
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
