<?php if (!empty($homeGroups)): ?>
<section class="section catalog-discover" id="collections">
  <div class="container">
    <div class="catalog-section-heading"><div><span class="catalog-kicker">ラインナップ</span><h2>カテゴリー別に、もっと見る。</h2></div></div>
    <p class="catalog-section-intro">それぞれの商品の特徴や仕様を、詳細ページでご確認ください。</p>
    <?php foreach (array_slice($homeGroups, 0, 4, true) as $slug => $group): ?>
    <div class="catalog-collection">
      <div class="catalog-collection-heading"><h3><i class="fa-solid <?= shop_e(shop_sanitize_icon($group['category']['icon'] ?? 'fa-box')) ?>" aria-hidden="true"></i> <?= shop_e($group['category']['name']) ?></h3><a class="catalog-text-link" href="list.php?cat=<?= rawurlencode($slug) ?>"><?= count($group['products']) ?> 点を見る <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></div>
      <div class="product-grid"><?php foreach (array_slice($group['products'], 0, 4) as $p) { include __DIR__ . '/product_card.php'; } ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<section class="section catalog-brand-section" id="brand-story">
  <div class="container catalog-brand-layout">
    <div class="catalog-brand-mark"><span>遠豪の自社ブランド</span><strong><?= shop_e($config['brand']) ?></strong><p>Knowledge · Marketing · E-commerce<br>AI · Global</p></div>
    <div class="catalog-brand-copy"><span class="catalog-kicker">私たちについて</span><h2>商品だけでなく、<br>その背景も知ってほしい。</h2><p><?= shop_e($config['company_name_ja']) ?>は、2021年に設立した越境ECを軸とする企業です。中国と日本をつなぐサプライチェーンと、EC・貿易のノウハウをもとに商品をご紹介しています。</p><p><?= shop_e($config['brand']) ?>は、Knowledge・Marketing・E-commerce・AI・Global の頭文字から生まれた自社ブランド。商品企画やEC運営をテクノロジーで支えています。</p><a href="about.php" class="catalog-text-link">会社・ブランドについて <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></div>
  </div>
</section>
<section class="section" id="shopping-guide">
  <div class="container">
    <div class="catalog-section-heading"><div><span class="catalog-kicker">ご利用ガイド</span><h2>気になる商品が見つかったら。</h2></div></div>
    <p class="catalog-section-intro">当サイトは商品をご紹介するカタログサイトです。ご購入・お取引については、お問い合わせください。</p>
    <ol class="catalog-guide-grid">
      <li><span class="catalog-step">01</span><h3>商品を見つける</h3><p>カテゴリーや検索から、気になる商品を探します。商品番号でも検索できます。</p><a href="list.php" class="catalog-text-link">商品一覧へ <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></li>
      <li><span class="catalog-step">02</span><h3>仕様・価格を確認する</h3><p>商品詳細ページで画像や説明、属性をご確認ください。価格帯がある商品は最低価格から最高価格を表示しています。</p></li>
      <li><span class="catalog-step">03</span><h3>商品番号を添えて相談する</h3><p>商品番号、ご希望の数量、ご質問をお知らせください。在庫や納期などの詳細はお問い合わせ時にご確認ください。</p><a href="contact.php" class="catalog-text-link">お問い合わせ <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></li>
    </ol>
  </div>
</section>
<section class="section catalog-faq-section" id="faq">
  <div class="container catalog-faq-layout">
    <div><span class="catalog-kicker">よくあるご質問</span><h2>ご利用の前に。</h2><p>商品選びやお問い合わせに<br>役立つ情報をまとめました。</p><a href="contact.php" class="catalog-text-link">その他のご質問はこちら <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></div>
    <div class="catalog-faq-list">
      <details><summary>このサイトから注文・決済できますか？</summary><p>当サイトは商品情報をご覧いただくためのサイトで、カートやオンライン決済機能はありません。ご購入については、商品番号を添えてお問い合わせください。</p></details>
      <details><summary>表示価格は税込ですか？価格に幅があるのはなぜですか？</summary><p>表示価格は税込です。価格帯が設定されている商品は「最低価格 ～ 最高価格」で表示しています。ご希望の仕様や数量での価格はお問い合わせください。</p></details>
      <details><summary>在庫・納期・送料を知りたいです。</summary><p>商品番号とご希望の数量、お届け先の地域を添えてお問い合わせください。在庫・納期・送料などのお取引条件は、個別にご確認ください。</p></details>
      <details><summary>問い合わせには何を記載すればよいですか？</summary><p>商品番号・商品名・ご希望の数量と、ご相談内容をお知らせいただくと確認がスムーズです。商品番号は各商品の詳細ページに記載しています。</p></details>
    </div>
  </div>
</section>
<section class="catalog-contact-section">
  <div class="container catalog-contact-inner"><div><span class="catalog-kicker">お問い合わせ</span><h2>商品選びの疑問を、ご相談ください。</h2><p>受付時間：<?= shop_e($config['hours_ja']) ?></p></div><a class="catalog-contact-button" href="contact.php"><i class="fa-regular fa-envelope" aria-hidden="true"></i> お問い合わせ窓口 <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></div>
</section>
