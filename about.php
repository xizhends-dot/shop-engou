<?php
$config = require __DIR__ . '/config.php';

$page_title = '遠豪について | ' . $config['company_name_ja'] . ' SHOP';
$active     = 'about';
require __DIR__ . '/partials/header.php';

// KMEAG ブランドの頭文字
$kmeag = [
    ['l' => 'K', 'en' => 'Knowledge',  'jp' => '知識・ノウハウ',     'desc' => '中日両市場への深い理解と、豊富な実務ノウハウ。'],
    ['l' => 'M', 'en' => 'Marketing',  'jp' => 'マーケティング',     'desc' => 'データに基づく戦略的なマーケティング。'],
    ['l' => 'E', 'en' => 'E-commerce', 'jp' => 'EC運営',            'desc' => '主要プラットフォームに精通したEC運営力。'],
    ['l' => 'A', 'en' => 'AI',         'jp' => 'AI技術',            'desc' => 'AIを活用した商品企画・コンテンツ制作・分析。'],
    ['l' => 'G', 'en' => 'Global',     'jp' => 'グローバル',         'desc' => '国境を越えたサプライチェーンと展開力。'],
];
?>

<section class="list-head">
  <div class="container">
    <div class="eyebrow">ABOUT ENGOU</div>
    <h1>遠豪について</h1>
    <p>会社紹介</p>
  </div>
</section>

<!-- 会社紹介 + 会社概要 -->
<section class="section section-about">
  <div class="container">
    <div class="about-layout">
      <div class="about-text">
        <h3><?= $config['company_name_ja'] ?>（<?= $config['company_name_en'] ?>）</h3>
        <p><?= $config['about_ja'] ?></p>
      </div>
      <table class="company-table">
        <tr><th>会社名</th><td><?= $config['company_name_ja'] ?>（<?= $config['company_name_en'] ?>）</td></tr>
        <tr><th>設立</th><td><?= $config['established_ja'] ?></td></tr>
        <tr><th>代表者</th><td><?= $config['representative_ja'] ?></td></tr>
        <tr><th>所在地</th><td><?= $config['zipcode'] ?><br><?= $config['address_ja'] ?></td></tr>
        <tr><th>事業内容</th><td><?= $config['business_ja'] ?></td></tr>
        <tr><th>電話番号</th><td><?= $config['phone'] ?></td></tr>
        <tr><th>メール</th><td><a href="mailto:<?= $config['email'] ?>"><?= $config['email'] ?></a></td></tr>
      </table>
    </div>
  </div>
</section>

<!-- 自社ブランド KMEAG -->
<section class="section">
  <div class="container">
    <h2 class="section-title">自社ブランド <span style="color:var(--accent-strong);">KMEAG</span></h2>
    <p class="section-sub">テクノロジーで、暮らしをアップデート</p>
    <p class="kmeag-lead">
      KMEAG は遠豪合同会社が展開する自社ブランドです。<br>
      <strong>Knowledge・Marketing・E-commerce・AI・Global</strong> の頭文字に由来し、AI技術を核としたスマートなEC運営を追求。商品企画から品質管理、マーケティングまでをテクノロジーで支え、より良い商品を、より手軽にお届けします。
    </p>
    <div class="kmeag-grid">
      <?php foreach ($kmeag as $k): ?>
      <div class="kmeag-card">
        <span class="kmeag-letter"><?= $k['l'] ?></span>
        <h3><?= $k['en'] ?></h3>
        <span class="kmeag-jp"><?= $k['jp'] ?></span>
        <p><?= $k['desc'] ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
