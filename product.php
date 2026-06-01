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
      <div class="d-price"><?= yen($product['price']) ?></div>

      <?php if (!empty($product['attributes'])): ?>
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
        <a id="inquiryBtn" href="mailto:<?= shop_e($config['email']) ?>?subject=<?= rawurlencode('【商品お問い合わせ】' . $product['name'] . '（商品番号: ' . $product['id'] . '）') ?>" class="btn-primary">
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

  // 商品属性：選択肢のクリック（グループ内で単一選択）＋ 問い合わせメールに反映
  var attrs = document.getElementById('dAttrs');
  var inquiry = document.getElementById('inquiryBtn');
  var EMAIL = <?= json_encode($config['email']) ?>;
  var PNAME = <?= json_encode($product['name']) ?>;
  var PID   = <?= json_encode($product['id']) ?>;

  function updateInquiry() {
    if (!inquiry) return;
    var lines = [];
    if (attrs) {
      attrs.querySelectorAll('.d-attr').forEach(function (g) {
        var sel = g.querySelector('.d-attr-chip.selected');
        if (sel) { lines.push(g.dataset.attrGroup + '：' + sel.dataset.val); }
      });
    }
    var subject = '【商品お問い合わせ】' + PNAME + '（商品番号: ' + PID + '）';
    var body = '商品名：' + PNAME + '\n商品番号：' + PID + '\n';
    if (lines.length) { body += '\nご希望の仕様：\n' + lines.join('\n') + '\n'; }
    body += '\nお問い合わせ内容：\n';
    inquiry.href = 'mailto:' + EMAIL + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
  }

  if (attrs) {
    attrs.querySelectorAll('.d-attr').forEach(function (g) {
      g.querySelectorAll('.d-attr-chip').forEach(function (c) {
        c.addEventListener('click', function () {
          g.querySelectorAll('.d-attr-chip').forEach(function (x) { x.classList.remove('selected'); });
          c.classList.add('selected');
          updateInquiry();
        });
      });
    });
  }
  updateInquiry();
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
