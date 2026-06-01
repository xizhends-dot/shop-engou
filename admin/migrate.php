<?php
require_once __DIR__ . '/auth.php';

$cli = (PHP_SAPI === 'cli');
if (!$cli) { admin_require_login(); }

$result = null;

if ($cli || ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? ''))) {
    try {
        $json = store_load_json();
        $ok = store_save_mysql($json);
        $n = count($json['products']);
        $result = $ok
            ? ['type' => 'ok',  'msg' => $cli ? "OK: {$n}" : __('migrate.ok', ['n' => $n])]
            : ['type' => 'err', 'msg' => $cli ? 'FAIL' : __('migrate.fail')];
    } catch (Throwable $e) {
        $result = ['type' => 'err', 'msg' => ($cli ? 'ERR: ' : '') . $e->getMessage()];
    }

    if ($cli) {
        echo $result['msg'] . PHP_EOL;
        exit($result['type'] === 'ok' ? 0 : 1);
    }
}

require_once __DIR__ . '/_layout.php';
$token = csrf_token();
$dbName = htmlspecialchars(shop_config()['db']['name'] ?? '');
admin_head(__('page.migrate'));
?>
<div class="adm-head">
  <h2><?= htmlspecialchars(__('migrate.title')) ?></h2>
  <a href="index.php" class="adm-btn"><i class="fa-solid fa-arrow-left"></i> <?= htmlspecialchars(__('btn.back_console')) ?></a>
</div>

<?php if ($result): ?>
<div class="adm-flash adm-flash-<?= $result['type'] === 'ok' ? 'ok' : 'err' ?>"><?= htmlspecialchars($result['msg']) ?></div>
<?php endif; ?>

<div class="adm-form" style="max-width:680px;">
  <p class="adm-note">
    <?= __('migrate.note', ['db' => $dbName]) ?>
  </p>
  <form method="post" onsubmit="return confirm(<?= json_encode(__('migrate.confirm'), JSON_UNESCAPED_UNICODE) ?>);">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
    <button type="submit" class="adm-btn adm-btn-primary"><i class="fa-solid fa-database"></i> <?= htmlspecialchars(__('btn.import_mysql')) ?></button>
  </form>
  <p class="adm-note" style="margin-top:18px;">
    <?= __('migrate.after') ?>
  </p>
</div>
<?php admin_foot(); ?>
