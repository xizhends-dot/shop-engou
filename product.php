<?php
$config   = require __DIR__ . '/config.php';
$data     = require __DIR__ . '/products.php';
$cats     = $data['categories'];
$products = $data['products'];

// ?id= で商品を特定
$id = isset($_GET['id']) ? (string) $_GET['id'] : '';
$product = null;
foreach ($products as $p) {
    if ($p['id'] === $id) { $product = $p; break; }
}

// 見つからない場合は一覧へリダイレクト
if ($product === null) {
    header('Location: index.php');
    exit;
}

$cat = $cats[$product['category']] ?? null;
$pIcon = shop_sanitize_icon($product['icon'] ?? 'fa-box');
$pAccent = shop_sanitize_accent($product['accent'] ?? '#DEF13F');
$imgs = store_filter_product_image_paths($product['images'] ?? []);
$skus = store_product_skus($product);
$skuGroups = $skus ? store_product_sku_groups($product) : [];
$productPrices = store_product_prices($product);
$inquirySubject = '【商品お問い合わせ】' . $product['name'] . '（商品番号: ' . $product['id'] . '）';
$inquiryBody = '商品名：' . $product['name'] . "\n商品番号：" . $product['id'] . "\n\nお問い合わせ内容：\n";
$inquiryHref = 'mailto:' . $config['email'] . '?subject=' . rawurlencode($inquirySubject) . '&body=' . rawurlencode($inquiryBody);

// 関連商品（同カテゴリ・最大3件、自身を除く）
$related = [];
foreach ($products as $p) {
    if ($p['category'] === $product['category'] && $p['id'] !== $product['id']) {
        $related[] = $p;
        if (count($related) >= 3) break;
    }
}

$page_title = $product['name'] . ' | ' . $config['company_name_ja'] . ' SHOP';
$active     = 'shop';
require __DIR__ . '/partials/header.php';
?>

<?php if ($skus): ?>
<link rel="stylesheet" href="sku-options.css?v=<?= (int) filemtime(__DIR__ . '/sku-options.css') ?>">
<?php endif; ?>
<div class="container">
  <!-- BREADCRUMB -->
  <nav class="breadcrumb">
    <a href="list.php">商品一覧</a>
    <span class="sep">/</span>
    <?php if ($cat): ?>
    <a href="list.php"><?= shop_e($cat['name']) ?></a>
    <span class="sep">/</span>
    <?php endif; ?>
    <span><?= shop_e($product['name']) ?></span>
  </nav>

  <!-- DETAIL -->
  <section class="detail-layout">
    <div class="detail-gallery">
      <div class="main-img-wrap">
        <?php if (count($imgs) > 1): ?>
        <button type="button" class="gallery-nav gallery-prev" id="galleryPrev" aria-label="前の画像"><i class="fa-solid fa-chevron-left"></i></button>
        <?php endif; ?>
        <div class="main-img" id="mainImg" <?= empty($imgs) ? 'style="' . shop_gradient_style($pAccent) . '"' : '' ?>>
          <?php if (!empty($imgs)): ?>
            <img src="<?= shop_e($imgs[0]) ?>" alt="<?= shop_e($product['name']) ?>" id="mainImgEl">
          <?php else: ?>
            <i class="fa-solid <?= shop_e($pIcon) ?> ph-icon"></i>
          <?php endif; ?>
        </div>
        <?php if (count($imgs) > 1): ?>
        <button type="button" class="gallery-nav gallery-next" id="galleryNext" aria-label="次の画像"><i class="fa-solid fa-chevron-right"></i></button>
        <?php endif; ?>
      </div>
      <?php if (count($imgs) > 1): ?>
      <div class="thumbs" id="thumbs">
        <?php foreach ($imgs as $k => $im): ?>
        <button type="button" class="thumb <?= $k === 0 ? 'active' : '' ?>" data-index="<?= $k ?>" data-src="<?= shop_e($im) ?>">
          <img src="<?= shop_e($im) ?>" alt="<?= shop_e($product['name']) ?> <?= $k + 1 ?>">
        </button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <div class="detail-info">
      <?php if ($cat): ?>
      <span class="d-cat"><i class="fa-solid <?= shop_e(shop_sanitize_icon($cat['icon'] ?? 'fa-tag')) ?>"></i> <span><?= shop_e($cat['name']) ?></span></span>
      <?php endif; ?>
      <h1><?= shop_e($product['name']) ?></h1>
      <p class="d-sku">商品番号：<?= shop_e($product['id']) ?></p>
      <?php if (!empty($product['rating']) || !empty($product['reviews'])): ?>
      <div class="d-rating">
        <span class="stars"><?= render_stars($product['rating']) ?></span>
        <?php if (!empty($product['rating'])): ?><span class="d-rating-num"><?= rtrim(rtrim(number_format((float)$product['rating'], 1), '0'), '.') ?></span><?php endif; ?>
        <?php if (!empty($product['reviews'])): ?><span class="d-rating-cnt">（<?= number_format((int)$product['reviews']) ?>件のレビュー）</span><?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="d-price" id="productPrice"<?= $skus ? ' aria-live="polite" aria-atomic="true"' : '' ?>><?= yen($productPrices['price'], $productPrices['price_max']) ?></div>

      <?php if ($skus): ?>
      <fieldset class="sku-options" id="skuOptions" aria-describedby="skuHelp">
        <legend class="sku-options__legend">仕様を選ぶ</legend>
        <p class="sku-options__help" id="skuHelp"><?= $skuGroups ? '各項目を選ぶと組み合わせの価格が表示されます。選択中のボタンをもう一度押すと解除できます。選べない場合は、ほかの項目を解除してから変更してください。' : '仕様を選択すると、価格とお問い合わせ内容に反映されます。' ?></p>
        <?php if ($skuGroups): ?>
        <div class="sku-groups">
          <?php foreach ($skuGroups as $groupIndex => $group): ?>
          <fieldset class="sku-group" data-group-index="<?= (int) $groupIndex ?>">
            <legend class="sku-group__legend"><?= shop_e($group['name']) ?></legend>
            <div class="sku-group__list">
              <?php foreach ($group['options'] as $optionIndex => $option): ?>
              <button type="button" class="sku-group__option" data-group-index="<?= (int) $groupIndex ?>" data-option-index="<?= (int) $optionIndex ?>" aria-pressed="false"><?= shop_e($option) ?></button>
              <?php endforeach; ?>
            </div>
          </fieldset>
          <?php endforeach; ?>
        </div>
        <script type="application/json" id="skuData"><?= json_encode(['groups' => $skuGroups, 'skus' => $skus], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
        <?php else: ?>
        <div class="sku-options__list">
          <?php foreach ($skus as $skuIndex => $sku): ?>
          <div class="sku-options__item">
            <input class="sku-options__radio" type="radio" name="product_sku" id="product-sku-<?= (int) $skuIndex ?>" value="<?= shop_e($sku['id']) ?>" data-sku-name="<?= shop_e($sku['name']) ?>" data-sku-price="<?= (int) $sku['price'] ?>">
            <label class="sku-options__label" for="product-sku-<?= (int) $skuIndex ?>">
              <span class="sku-options__identity">
                <span class="sku-options__name"><?= shop_e($sku['name']) ?></span>
              </span>
              <span class="sku-options__price"><?= yen($sku['price']) ?></span>
            </label>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="sku-options__footer">
          <p class="sku-options__status" id="skuStatus" role="status" aria-live="polite" aria-atomic="true"><?= $skuGroups ? '未選択・すべての組み合わせの価格を表示。各項目を選択してください。' : '未選択・すべての仕様の価格を表示' ?></p>
          <button class="sku-options__reset" id="skuReset" type="button" aria-controls="skuOptions productPrice" aria-disabled="true">選択をクリア</button>
        </div>
      </fieldset>
      <?php if (!empty($product['attributes'])): ?>
      <section class="sku-specs" aria-labelledby="skuSpecsTitle">
        <h2 class="sku-specs__title" id="skuSpecsTitle">商品仕様（参考）</h2>
        <dl class="sku-specs__list">
          <?php foreach ($product['attributes'] as $a): if (empty($a['name'])) continue; ?>
          <div class="sku-specs__row">
            <dt><?= shop_e($a['name']) ?></dt>
            <dd><?= !empty($a['options']) ? shop_e(implode(' / ', $a['options'])) : '—' ?></dd>
          </div>
          <?php endforeach; ?>
        </dl>
      </section>
      <?php endif; ?>
      <?php elseif (!empty($product['attributes'])): ?>
      <div class="d-attrs" id="dAttrs">
        <?php foreach ($product['attributes'] as $a): if (empty($a['name'])) continue; ?>
        <div class="d-attr" data-attr-group="<?= shop_e($a['name']) ?>">
          <span class="d-attr-name"><?= shop_e($a['name']) ?></span>
          <span class="d-attr-opts">
            <?php if (!empty($a['options'])): foreach ($a['options'] as $oi => $opt): ?>
              <button type="button" class="d-attr-chip <?= $oi === 0 ? 'selected' : '' ?>" data-val="<?= shop_e($opt) ?>"><?= shop_e($opt) ?></button>
            <?php endforeach; else: ?>
              <span class="d-attr-empty">—</span>
            <?php endif; ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="detail-actions">
        <a id="inquiryBtn" href="<?= shop_e($inquiryHref) ?>" data-inquiry-email="<?= shop_e($config['email']) ?>" data-product-name="<?= shop_e($product['name']) ?>" data-product-id="<?= shop_e($product['id']) ?>" class="btn-primary">
          <i class="fa-solid fa-envelope"></i><span>問い合わせ</span>
        </a>
        <a href="list.php" class="btn-ghost">
          <i class="fa-solid fa-arrow-left"></i><span>一覧へ戻る</span>
        </a>
      </div>
    </div>
  </section>

  <!-- 商品詳細 -->
  <section class="product-detail-body">
    <h3 class="block-title">商品詳細</h3>
    <p><?= nl2br(shop_e($product['desc'] ?? ''), false) ?></p>
  </section>

  <?php if (!empty($related)): ?>
  <!-- 関連商品 -->
  <section class="related">
    <h3 class="block-title">関連商品</h3>
    <div class="product-grid">
      <?php foreach ($related as $p) { include __DIR__ . '/partials/product_card.php'; } ?>
    </div>
  </section>
  <?php endif; ?>
</div>

<script>
(function () {
  // 画像ギャラリー：左右ボタン・サムネイルでメイン画像を切り替え
  var thumbs = document.querySelectorAll('#thumbs .thumb');
  var mainEl = document.getElementById('mainImgEl');
  var prevBtn = document.getElementById('galleryPrev');
  var nextBtn = document.getElementById('galleryNext');
  var current = 0;

  function showGallery(index) {
    if (!thumbs.length || !mainEl) return;
    current = (index + thumbs.length) % thumbs.length;
    var t = thumbs[current];
    mainEl.src = t.dataset.src;
    mainEl.alt = t.querySelector('img') ? t.querySelector('img').alt : '';
    thumbs.forEach(function (x, i) { x.classList.toggle('active', i === current); });
  }

  if (thumbs.length && mainEl) {
    thumbs.forEach(function (t) {
      t.addEventListener('click', function () { showGallery(parseInt(t.dataset.index, 10)); });
    });
    if (prevBtn) prevBtn.addEventListener('click', function () { showGallery(current - 1); });
    if (nextBtn) nextBtn.addEventListener('click', function () { showGallery(current + 1); });
  }

})();
</script>
<script src="product-skus.js?v=<?= (int) filemtime(__DIR__ . '/product-skus.js') ?>" defer></script>

<?php require __DIR__ . '/partials/footer.php'; ?>
