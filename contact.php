<?php
$config = require __DIR__ . '/config.php';

$page_title = 'お問い合わせ | ' . $config['company_name_ja'] . ' SHOP';
$active     = 'contact';
require __DIR__ . '/partials/header.php';
?>

<section class="list-head">
  <div class="container">
    <div class="eyebrow">CONTACT</div>
    <h1>お問い合わせ</h1>
    <p>お見積り・OEM・卸売のご相談もお気軽にどうぞ</p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="contact-page">
      <!-- 連絡先情報 -->
      <div class="contact-info-col">
        <h3>連絡先</h3>
        <ul class="contact-info-list">
          <li><i class="fa-solid fa-location-dot"></i><span><?= $config['zipcode'] ?><br><?= $config['address_ja'] ?></span></li>
          <li><i class="fa-solid fa-phone"></i><span><a href="tel:<?= preg_replace('/[^0-9+]/', '', $config['phone']) ?>"><?= $config['phone'] ?></a></span></li>
          <li><i class="fa-solid fa-envelope"></i><span><a href="mailto:<?= $config['email'] ?>"><?= $config['email'] ?></a></span></li>
          <li><i class="fa-solid fa-clock"></i><span><?= $config['hours_ja'] ?></span></li>
        </ul>
        <a href="mailto:<?= $config['email'] ?>" class="btn-primary"><i class="fa-solid fa-envelope"></i><span>メールで問い合わせる</span></a>
      </div>

      <!-- お問い合わせフォーム -->
      <div class="contact-form-col">
        <h3>お問い合わせフォーム</h3>
        <p class="contact-note">下記フォームにご記入のうえ送信してください。ご利用のメールソフトが起動します。</p>
        <form id="contactForm" class="contact-form">
          <div class="form-row">
            <div class="form-group">
              <label for="cf-name">お名前 <span class="req">*</span></label>
              <input type="text" id="cf-name" name="name" required>
            </div>
            <div class="form-group">
              <label for="cf-company">会社名</label>
              <input type="text" id="cf-company" name="company">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="cf-email">メールアドレス <span class="req">*</span></label>
              <input type="email" id="cf-email" name="email" required>
            </div>
            <div class="form-group">
              <label for="cf-tel">電話番号</label>
              <input type="tel" id="cf-tel" name="tel">
            </div>
          </div>
          <div class="form-group">
            <label for="cf-message">お問い合わせ内容 <span class="req">*</span></label>
            <textarea id="cf-message" name="message" rows="6" required></textarea>
          </div>
          <button type="submit" class="btn-primary btn-block"><i class="fa-solid fa-paper-plane"></i><span>送信する</span></button>
        </form>
      </div>
    </div>
  </div>
</section>

<script>
(function () {
  var form = document.getElementById('contactForm');
  if (!form) return;
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var name = form.name.value.trim();
    var company = form.company.value.trim();
    var email = form.email.value.trim();
    var tel = form.tel.value.trim();
    var msg = form.message.value.trim();
    var subject = 'お問い合わせ - ' + name;
    var body =
      'お名前: ' + name + '\n' +
      '会社名: ' + company + '\n' +
      'メール: ' + email + '\n' +
      '電話番号: ' + tel + '\n\n' +
      'お問い合わせ内容:\n' + msg + '\n';
    window.location.href = 'mailto:<?= $config['email'] ?>?subject=' +
      encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
  });
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
