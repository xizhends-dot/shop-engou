<?php
$config   = require __DIR__ . '/config.php';
$data     = require __DIR__ . '/products.php';
$cats     = $data['categories'];
$products = $data['products'];
$banners  = banners_load();

$page_title = $config['company_name_ja'] . ' SHOP — オンラインショップ';
$active     = 'home';

// バナー共通設定（管理画面「バナー管理」で設定）
$bt = banner_settings_load();

// おすすめ商品（未設定なら先頭の商品で代替）
$featured = featured_products($data);
if (empty($featured)) { $featured = array_slice($products, 0, 8); }
$featured = array_slice($featured, 0, 12);

require __DIR__ . '/partials/header.php';
?>

<!-- ============ BANNER ============ -->
<?php
// バナー1枚分のキャプションを出力するヘルパー
function banner_caption($bt, $title, $subtitle, $btn1link) {
    ob_start(); ?>
    <div class="banner-inner">
      <?php if (!empty($bt['eyebrow'])): ?><div class="eyebrow"><?= $bt['eyebrow'] ?></div><?php endif; ?>
      <?php if ($title !== ''): ?><h1><?= $title ?></h1><?php endif; ?>
      <?php if ($subtitle !== ''): ?><p><?= $subtitle ?></p><?php endif; ?>
      <?php if (!empty($bt['btn1_text']) || !empty($bt['btn2_text'])): ?>
      <div class="banner-actions">
        <?php if (!empty($bt['btn1_text'])): ?><a href="<?= htmlspecialchars($btn1link) ?>" class="btn-primary"><i class="fa-solid fa-bag-shopping"></i><span><?= htmlspecialchars($bt['btn1_text']) ?></span></a><?php endif; ?>
        <?php if (!empty($bt['btn2_text'])): ?><a href="<?= htmlspecialchars($bt['btn2_link']) ?>" class="btn-ghost-light"><i class="fa-solid fa-envelope"></i><span><?= htmlspecialchars($bt['btn2_text']) ?></span></a><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php return ob_get_clean();
}
?>
<section class="banner <?= !empty($banners) ? 'banner-has-slides' : '' ?>" id="top">
  <?php if (!empty($banners)): ?>
  <div class="banner-slides" id="bannerSlides">
    <?php foreach ($banners as $k => $b):
      $st = $b['title'] !== ''    ? $b['title']    : $bt['title'];
      $ss = $b['subtitle'] !== '' ? $b['subtitle'] : $bt['subtitle'];
      $b1 = $b['link'] !== ''     ? $b['link']     : $bt['btn1_link'];
    ?>
    <div class="banner-slide <?= $k === 0 ? 'active' : '' ?>" style="background-image:url('<?= htmlspecialchars($b['image']) ?>')">
      <?= banner_caption($bt, $st, $ss, $b1) ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php if (count($banners) > 1): ?>
  <div class="banner-dots" id="bannerDots">
    <?php foreach ($banners as $k => $b): ?>
    <button type="button" class="banner-dot <?= $k === 0 ? 'active' : '' ?>" data-index="<?= $k ?>" aria-label="スライド<?= $k + 1 ?>"></button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php else: ?>
  <div class="banner-bg"></div>
  <div class="banner-scrim"></div>
  <?= banner_caption($bt, $bt['title'], $bt['subtitle'], $bt['btn1_link']) ?>
  <?php endif; ?>
</section>

<!-- ============ おすすめ商品 ============ -->
<section class="section" id="featured">
  <div class="container">
    <h2 class="section-title">おすすめ商品</h2>
    <p class="section-sub">人気・注目のアイテムをピックアップ</p>

    <div class="product-grid">
      <?php foreach ($featured as $p) { include __DIR__ . '/partials/product_card.php'; } ?>
    </div>

    <div class="more-wrap">
      <a href="list.php" class="btn-more"><span>すべての商品を見る</span><i class="fa-solid fa-arrow-right"></i></a>
    </div>
  </div>
</section>

<script>
(function () {
  // バナースライド（自動 + ドット）
  var slides = document.querySelectorAll('#bannerSlides .banner-slide');
  var dots = document.querySelectorAll('#bannerDots .banner-dot');
  if (slides.length > 1) {
    var cur = 0, timer;
    function show(i) {
      cur = (i + slides.length) % slides.length;
      slides.forEach(function (s, k) { s.classList.toggle('active', k === cur); });
      dots.forEach(function (d, k) { d.classList.toggle('active', k === cur); });
    }
    function start() { timer = setInterval(function () { show(cur + 1); }, 5000); }
    function reset() { clearInterval(timer); start(); }
    dots.forEach(function (d) {
      d.addEventListener('click', function () { show(parseInt(d.dataset.index, 10)); reset(); });
    });
    start();
  }
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
