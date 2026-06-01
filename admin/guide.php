<?php
require_once __DIR__ . '/auth.php';
admin_require_login();
require_once __DIR__ . '/_layout.php';

/**
 * @param list<string> $keys
 * @return list<string>
 */
function admin_guide_steps(array $keys): array {
  $out = [];
  foreach ($keys as $key) {
    $out[] = __($key);
  }
  return $out;
}

/**
 * @param string $titleKey
 * @param list<string> $stepKeys
 * @return array{title:string,steps:list<string>}
 */
function admin_guide_block(string $titleKey, array $stepKeys): array {
  return ['title' => __($titleKey), 'steps' => admin_guide_steps($stepKeys)];
}

/**
 * @return list<array{id:string,icon:string,title:string,href:string,goto:string,blocks:list<array{title:string,steps:list<string>}>,tips?:string,warn?:string}>
 */
function admin_guide_sections(): array {
  return [
    [
      'id'    => 'banners',
      'icon'  => 'fa-panorama',
      'title' => __('guide.sec_banners'),
      'href'  => 'banners.php',
      'goto'  => __('guide.goto_page'),
      'blocks' => [
        admin_guide_block('guide.banners.h0', ['guide.banners.h0.s1', 'guide.banners.h0.s2', 'guide.banners.h0.s3']),
        admin_guide_block('guide.banners.h1', ['guide.banners.h1.s1', 'guide.banners.h1.s2', 'guide.banners.h1.s3', 'guide.banners.h1.s4', 'guide.banners.h1.s5']),
        admin_guide_block('guide.banners.h2', ['guide.banners.h2.s1', 'guide.banners.h2.s2', 'guide.banners.h2.s3', 'guide.banners.h2.s4']),
        admin_guide_block('guide.banners.h3', ['guide.banners.h3.s1', 'guide.banners.h3.s2', 'guide.banners.h3.s3', 'guide.banners.h3.s4', 'guide.banners.h3.s5', 'guide.banners.h3.s6']),
        admin_guide_block('guide.banners.h4', ['guide.banners.h4.s1', 'guide.banners.h4.s2', 'guide.banners.h4.s3', 'guide.banners.h4.s4']),
      ],
      'tips' => __('guide.banners.tip'),
      'warn' => __('guide.banners.warn'),
    ],
    [
      'id'    => 'categories',
      'icon'  => 'fa-tags',
      'title' => __('guide.sec_categories'),
      'href'  => 'categories.php',
      'goto'  => __('guide.goto_page'),
      'blocks' => [
        admin_guide_block('guide.categories.h0', ['guide.categories.h0.s1', 'guide.categories.h0.s2']),
        admin_guide_block('guide.categories.h1', ['guide.categories.h1.s1', 'guide.categories.h1.s2', 'guide.categories.h1.s3', 'guide.categories.h1.s4', 'guide.categories.h1.s5', 'guide.categories.h1.s6']),
        admin_guide_block('guide.categories.h2', ['guide.categories.h2.s1', 'guide.categories.h2.s2', 'guide.categories.h2.s3', 'guide.categories.h2.s4', 'guide.categories.h2.s5']),
        admin_guide_block('guide.categories.h3', ['guide.categories.h3.s1', 'guide.categories.h3.s2', 'guide.categories.h3.s3']),
        admin_guide_block('guide.categories.h4', ['guide.categories.h4.s1', 'guide.categories.h4.s2', 'guide.categories.h4.s3']),
      ],
      'tips' => __('guide.categories.tip'),
    ],
    [
      'id'    => 'media',
      'icon'  => 'fa-images',
      'title' => __('guide.sec_media'),
      'href'  => 'media.php',
      'goto'  => __('guide.goto_page'),
      'blocks' => [
        admin_guide_block('guide.media.h0', ['guide.media.h0.s1', 'guide.media.h0.s2', 'guide.media.h0.s3']),
        admin_guide_block('guide.media.h1', ['guide.media.h1.s1', 'guide.media.h1.s2', 'guide.media.h1.s3', 'guide.media.h1.s4', 'guide.media.h1.s5']),
        admin_guide_block('guide.media.h2', ['guide.media.h2.s1', 'guide.media.h2.s2', 'guide.media.h2.s3', 'guide.media.h2.s4', 'guide.media.h2.s5']),
        admin_guide_block('guide.media.h3', ['guide.media.h3.s1', 'guide.media.h3.s2', 'guide.media.h3.s3', 'guide.media.h3.s4']),
        admin_guide_block('guide.media.h4', ['guide.media.h4.s1', 'guide.media.h4.s2', 'guide.media.h4.s3', 'guide.media.h4.s4', 'guide.media.h4.s5']),
      ],
      'tips' => __('guide.media.tip'),
    ],
    [
      'id'    => 'products',
      'icon'  => 'fa-box',
      'title' => __('guide.sec_products'),
      'href'  => 'edit.php',
      'goto'  => __('guide.goto_add'),
      'blocks' => [
        admin_guide_block('guide.products.h0', ['guide.products.h0.s1', 'guide.products.h0.s2', 'guide.products.h0.s3', 'guide.products.h0.s4']),
        admin_guide_block('guide.products.h1', ['guide.products.h1.s1', 'guide.products.h1.s2', 'guide.products.h1.s3', 'guide.products.h1.s4', 'guide.products.h1.s5', 'guide.products.h1.s6', 'guide.products.h1.s7', 'guide.products.h1.s8']),
        admin_guide_block('guide.products.h2', ['guide.products.h2.s1', 'guide.products.h2.s2', 'guide.products.h2.s3', 'guide.products.h2.s4', 'guide.products.h2.s5', 'guide.products.h2.s6', 'guide.products.h2.s7', 'guide.products.h2.s8']),
        admin_guide_block('guide.products.h3', ['guide.products.h3.s1', 'guide.products.h3.s2', 'guide.products.h3.s3', 'guide.products.h3.s4', 'guide.products.h3.s5']),
        admin_guide_block('guide.products.h4', ['guide.products.h4.s1', 'guide.products.h4.s2', 'guide.products.h4.s3', 'guide.products.h4.s4', 'guide.products.h4.s5']),
      ],
      'tips' => __('guide.products.tip'),
      'warn' => __('guide.products.warn'),
    ],
    [
      'id'    => 'featured',
      'icon'  => 'fa-star',
      'title' => __('guide.sec_featured'),
      'href'  => 'featured.php',
      'goto'  => __('guide.goto_page'),
      'blocks' => [
        admin_guide_block('guide.featured.h0', ['guide.featured.h0.s1', 'guide.featured.h0.s2']),
        admin_guide_block('guide.featured.h1', ['guide.featured.h1.s1', 'guide.featured.h1.s2', 'guide.featured.h1.s3', 'guide.featured.h1.s4', 'guide.featured.h1.s5']),
      ],
      'tips' => __('guide.featured.tip'),
    ],
    [
      'id'    => 'import',
      'icon'  => 'fa-file-excel',
      'title' => __('guide.sec_import'),
      'href'  => 'import.php',
      'goto'  => __('guide.goto_page'),
      'blocks' => [
        admin_guide_block('guide.import.h0', ['guide.import.h0.s1', 'guide.import.h0.s2']),
        admin_guide_block('guide.import.h1', ['guide.import.h1.s1', 'guide.import.h1.s2', 'guide.import.h1.s3', 'guide.import.h1.s4']),
        admin_guide_block('guide.import.h2', ['guide.import.h2.s1', 'guide.import.h2.s2', 'guide.import.h2.s3', 'guide.import.h2.s4', 'guide.import.h2.s5']),
      ],
      'tips' => __('guide.import.tip'),
      'warn' => __('guide.import.warn'),
    ],
  ];
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
    <?php if (!empty($sec['warn'])): ?>
    <p class="adm-guide-warn"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> <?= $sec['warn'] ?></p>
    <?php endif; ?>
    <?php foreach ($sec['blocks'] as $block): ?>
    <div class="adm-guide-block">
      <h4 class="adm-guide-block-title"><?= htmlspecialchars($block['title']) ?></h4>
      <ol class="adm-guide-steps">
        <?php foreach ($block['steps'] as $step): ?>
        <li><?= $step ?></li>
        <?php endforeach; ?>
      </ol>
    </div>
    <?php endforeach; ?>
    <?php if (!empty($sec['tips'])): ?>
    <p class="adm-guide-tip"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i> <?= $sec['tips'] ?></p>
    <?php endif; ?>
  </article>
  <?php endforeach; ?>
</div>

<p class="adm-guide-foot"><?= __html('guide.foot') ?></p>

<?php admin_foot(); ?>
