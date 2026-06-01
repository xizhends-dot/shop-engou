<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_layout.php';

if (admin_is_logged_in()) { header('Location: index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'セッションの有効期限が切れました。もう一度お試しください。';
    } elseif (admin_verify_password($_POST['password'] ?? '', $config)) {
        admin_login();
        header('Location: index.php');
        exit;
    } else {
        $error = 'パスワードが正しくありません。';
    }
}
$token = csrf_token();
admin_head('ログイン', false);
?>
<div class="adm-login">
  <div class="adm-login-card">
    <h1><?= htmlspecialchars($config['company_name_ja']) ?> SHOP</h1>
    <p class="adm-login-sub">管理画面ログイン</p>
    <?php if ($error): ?><div class="adm-flash adm-flash-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
      <label>パスワード</label>
      <input type="password" name="password" required autofocus>
      <button type="submit" class="adm-btn adm-btn-primary"><i class="fa-solid fa-right-to-bracket"></i> ログイン</button>
    </form>
  </div>
</div>
<?php admin_foot(); ?>
