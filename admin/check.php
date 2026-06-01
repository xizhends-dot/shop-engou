<?php
require_once __DIR__ . '/auth.php';
admin_require_login();

$report = store_health_report();
$allOk  = !in_array(false, array_column($report, 'ok'), true);

require_once __DIR__ . '/_layout.php';
admin_head(__('page.check'));
?>
<div class="adm-head">
  <h2><?= htmlspecialchars(__('page.check')) ?></h2>
  <a href="index.php" class="adm-btn"><i class="fa-solid fa-arrow-left"></i> <?= htmlspecialchars(__('btn.back_console')) ?></a>
</div>

<div class="adm-flash adm-flash-<?= $allOk ? 'ok' : 'err' ?>">
  <?= $allOk ? htmlspecialchars(__('check.ok_msg')) : htmlspecialchars(__('check.ng_msg')) ?>
</div>

<table class="adm-table" style="max-width:720px;">
  <thead>
    <tr><th><?= htmlspecialchars(__('col.item')) ?></th><th><?= htmlspecialchars(__('col.status')) ?></th><th><?= htmlspecialchars(__('col.detail')) ?></th></tr>
  </thead>
  <tbody>
    <?php foreach ($report as $row):
      $row = admin_health_translate($row);
    ?>
    <tr>
      <td><?= htmlspecialchars($row['label']) ?></td>
      <td><?= $row['ok'] ? '<span style="color:#1f7a44;">' . htmlspecialchars(__('check.status_ok')) . '</span>' : '<span style="color:#b3322f;">' . htmlspecialchars(__('check.status_ng')) . '</span>' ?></td>
      <td style="font-size:13px;"><?= htmlspecialchars($row['detail']) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<div class="adm-form" style="max-width:720px;margin-top:24px;">
  <h3 style="margin-bottom:10px;"><?= htmlspecialchars(__('check.faq')) ?></h3>
  <?php if (admin_lang() === 'zh'): ?>
  <p class="adm-note">
    <strong>storage 为 mysql 时</strong><br>
    1. 在服务器（宝塔等）创建数据库与用户<br>
    2. 在 phpMyAdmin 导入 <code>shop/db/schema.sql</code>（乱码或 1366 错误时再执行 <code>db/fix_charset.sql</code>）<br>
    3. 将 <code>shop/config.php</code> 的 <code>db</code>（host / name / user / pass）改为生产环境值<br>
    4. 若有旧 JSON 数据，执行 <a href="migrate.php">JSON → MySQL 迁移</a>
  </p>
  <p class="adm-note" style="margin-top:14px;">
    <strong>storage 为 json 时</strong><br>
    SSH 执行 <code>chmod -R 775 shop/data shop/images</code>，并 <code>chown -R www:www shop/data shop/images</code>（用户名视环境而定 nginx / apache 等）
  </p>
  <p class="adm-note" style="margin-top:14px;">
    若不用 MySQL，可将 <code>config.php</code> 的 <code>'storage' => 'json'</code>，仅用 <code>data/products.json</code> 即可（无需数据库）。
  </p>
  <?php else: ?>
  <p class="adm-note">
    <strong>storage が mysql の場合</strong><br>
    1. サーバー（宝塔等）でデータベースとユーザーを作成<br>
    2. <code>shop/db/schema.sql</code> を phpMyAdmin でインポート（文字化け・1366 エラー時は <code>db/fix_charset.sql</code> も実行）<br>
    3. <code>shop/config.php</code> の <code>db</code>（host / name / user / pass）を本番の値に合わせる<br>
    4. 既存の JSON データがある場合は <a href="migrate.php">JSON → MySQL 移行</a> を実行
  </p>
  <p class="adm-note" style="margin-top:14px;">
    <strong>storage が json の場合</strong><br>
    SSH で <code>chmod -R 775 shop/data shop/images</code>、Web サーバーが書き込めるよう <code>chown -R www:www shop/data shop/images</code>（ユーザー名は環境により nginx / apache 等）
  </p>
  <p class="adm-note" style="margin-top:14px;">
  MySQL を使わない場合は <code>config.php</code> の <code>'storage' => 'json'</code> に変更すると、<code>data/products.json</code> のみで動作します（DB 不要）。
  </p>
  <?php endif; ?>
</div>
<?php admin_foot(); ?>
