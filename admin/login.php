<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_layout.php';

if (admin_is_logged_in()) { header('Location: index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = __('login.error_session');
    } elseif (admin_verify_password($_POST['password'] ?? '', $config)) {
        admin_login();
        header('Location: index.php');
        exit;
    } else {
        $error = __('login.error_password');
    }
}
$token = csrf_token();
admin_head(__('page.login'), false);
?>
<div class="adm-login">
  <div class="adm-login-card">
    <h1><?= htmlspecialchars($config['company_name_ja']) ?> SHOP</h1>
    <p class="adm-login-sub"><?= htmlspecialchars(__('login.subtitle')) ?></p>
    <?php if ($error): ?><div class="adm-flash adm-flash-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
      <label><?= htmlspecialchars(__('login.password')) ?></label>
      <input type="password" name="password" required autofocus>
      <button type="submit" class="adm-btn adm-btn-primary"><i class="fa-solid fa-right-to-bracket"></i> <?= htmlspecialchars(__('login.submit')) ?></button>
    </form>
    <p class="adm-login-lang"><?= admin_lang_switcher_html() ?></p>
  </div>
</div>
<?php admin_foot(); ?>
