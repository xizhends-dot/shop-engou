<?php
/** 共通フッター + 共通スクリプト（ハンバーガーメニュー） */
if (!isset($config)) { $config = require __DIR__ . '/../config.php'; }
?>
</main>

<!-- FOOTER -->
<footer class="footer">
  <div class="footer-inner">
    <div class="footer-col">
      <h4><?= $config['company_name_ja'] ?> SHOP</h4>
      <p>美容家電・生活家電・キッチン家電を厳選してお届け。</p>
      <p style="margin-top:10px;">&copy; <?= date('Y') ?> <?= $config['company_name_ja'] ?>（<?= $config['company_name_en'] ?>）</p>
    </div>
    <div class="footer-col">
      <h4>サイト</h4>
      <a href="index.php#top">ホーム</a>
      <a href="list.php">商品一覧</a>
      <a href="about.php">遠豪について</a>
      <a href="contact.php">お問い合わせ</a>
      <a href="<?= htmlspecialchars($config['main_site_url']) ?>">本社サイト（engou.jp）</a>
    </div>
    <div class="footer-col" id="contact">
      <h4>連絡先</h4>
      <p><i class="fa-solid fa-location-dot"></i><span><?= $config['zipcode'] ?><br><?= $config['address_ja'] ?></span></p>
      <p><i class="fa-solid fa-phone"></i><span><?= $config['phone'] ?></span></p>
      <p><i class="fa-solid fa-envelope"></i><span><a href="mailto:<?= $config['email'] ?>"><?= $config['email'] ?></a></span></p>
      <p><i class="fa-solid fa-clock"></i><span><?= $config['hours_ja'] ?></span></p>
    </div>
  </div>
  <div class="footer-bottom">
    <span>本オンラインショップは <?= $config['company_name_ja'] ?> が運営しています。</span>
  </div>
</footer>

<script>
(function () {
  // ハンバーガーメニュー
  var hb = document.getElementById('hamburger');
  var nl = document.getElementById('navLinks');
  if (hb && nl) hb.addEventListener('click', function () { nl.classList.toggle('open'); });

  // スクロール連動でナビをハイライト（トップページのみ）
  if (document.getElementById('top')) {
    var items = [];
    document.querySelectorAll('.nav-links a[href*="#"]').forEach(function (a) {
      var hash = (a.getAttribute('href').split('#')[1] || '');
      var sec = hash ? document.getElementById(hash) : null;
      if (sec) { items.push({ a: a, sec: sec }); }
    });
    if (items.length) {
      var onScroll = function () {
        var pos = window.scrollY + 100;
        var cur = items[0];
        items.forEach(function (o) { if (o.sec.offsetTop <= pos) cur = o; });
        items.forEach(function (o) { o.a.classList.remove('active'); });
        cur.a.classList.add('active');
      };
      window.addEventListener('scroll', onScroll, { passive: true });
      onScroll();
    }
  }
})();
</script>
</body>
</html>
