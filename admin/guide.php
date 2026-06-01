<?php
require_once __DIR__ . '/auth.php';
admin_require_login();
require_once __DIR__ . '/_layout.php';

/**
 * @return list<array{id:string,icon:string,title:string,href:string,goto:string,steps:list<string>,tips?:string}>
 */
function admin_guide_sections(): array {
  $sections = [
    [
      'id'    => 'banners',
      'icon'  => 'fa-panorama',
      'title' => __('guide.sec_banners'),
      'href'  => 'banners.php',
      'goto'  => __('guide.goto_page'),
      'steps' => [
        __('guide.banners.s1'),
        __('guide.banners.s2'),
        __('guide.banners.s3'),
        __('guide.banners.s4'),
        __('guide.banners.s5'),
      ],
      'tips' => __('guide.banners.tip'),
    ],
    [
      'id'    => 'categories',
      'icon'  => 'fa-tags',
      'title' => __('guide.sec_categories'),
      'href'  => 'categories.php',
      'goto'  => __('guide.goto_page'),
      'steps' => [
        __('guide.categories.s1'),
        __('guide.categories.s2'),
        __('guide.categories.s3'),
        __('guide.categories.s4'),
        __('guide.categories.s5'),
      ],
      'tips' => __('guide.categories.tip'),
    ],
    [
      'id'    => 'media',
      'icon'  => 'fa-images',
      'title' => __('guide.sec_media'),
      'href'  => 'media.php',
      'goto'  => __('guide.goto_page'),
      'steps' => [
        __('guide.media.s1'),
        __('guide.media.s2'),
        __('guide.media.s3'),
        __('guide.media.s4'),
        __('guide.media.s5'),
      ],
      'tips' => __('guide.media.tip'),
    ],
    [
      'id'    => 'products',
      'icon'  => 'fa-box',
      'title' => __('guide.sec_products'),
      'href'  => 'edit.php',
      'goto'  => __('guide.goto_add'),
      'steps' => [
        __('guide.products.s1'),
        __('guide.products.s2'),
        __('guide.products.s3'),
        __('guide.products.s4'),
        __('guide.products.s5'),
        __('guide.products.s6'),
      ],
      'tips' => __('guide.products.tip'),
    ],
    [
      'id'    => 'featured',
      'icon'  => 'fa-star',
      'title' => __('guide.sec_featured'),
      'href'  => 'featured.php',
      'goto'  => __('guide.goto_page'),
      'steps' => [
        __('guide.featured.s1'),
        __('guide.featured.s2'),
        __('guide.featured.s3'),
      ],
    ],
  ];
  return $sections;
}

$sections = admin_guide_sections();
admin_head(__('page.guide'));
?>
<div class="adm-head">
  <h2><?= htmlspecialchars(__('page.guide')) ?></h2>
  <a href="index.php" class="adm-btn adm-btn-sm"><i class="fa-solid fa-gauge-high"></i> <?= htmlspecialchars(__('btn.back_console')) ?></a>
</div>

<p class="adm-guide-intro"><?= __html('guide.intro') ?></p>

<nav class="adm-guide-toc" aria-label="<?= htmlspecialchars(__('guide.toc_label')) ?>">
  <span class="adm-guide-toc-title"><?= htmlspecialchars(__('guide.toc_title')) ?></span>
  <ul>
    <?php foreach ($sections as $sec): ?>
    <li><a href="#guide-<?= htmlspecialchars($sec['id']) ?>"><i class="fa-solid <?= htmlspecialchars($sec['icon']) ?>" aria-hidden="true"></i> <?= htmlspecialchars($sec['title']) ?></a></li>
    <?php endforeach; ?>
  </ul>
</nav>

<div class="adm-guide-sections">
  <?php foreach ($sections as $sec): ?>
  <article id="guide-<?= htmlspecialchars($sec['id']) ?>" class="adm-guide-card">
    <header class="adm-guide-card-head">
      <h3><i class="fa-solid <?= htmlspecialchars($sec['icon']) ?>" aria-hidden="true"></i> <?= htmlspecialchars($sec['title']) ?></h3>
      <a href="<?= htmlspecialchars($sec['href']) ?>" class="adm-btn adm-btn-sm"><i class="fa-solid fa-arrow-right"></i> <?= htmlspecialchars($sec['goto']) ?></a>
    </header>
    <ol class="adm-guide-steps">
      <?php foreach ($sec['steps'] as $step): ?>
      <li><?= $step ?></li>
      <?php endforeach; ?>
    </ol>
    <?php if (!empty($sec['tips'])): ?>
    <p class="adm-guide-tip"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i> <?= $sec['tips'] ?></p>
    <?php endif; ?>
  </article>
  <?php endforeach; ?>
</div>

<p class="adm-guide-foot"><?= __html('guide.foot') ?></p>

<?php admin_foot(); ?>
