<?php
/**
 * データ保存の診断（MySQL / JSON / フォルダ権限）
 */
require_once __DIR__ . '/auth.php';
admin_require_login();

$report = store_health_report();
$allOk  = !in_array(false, array_column($report, 'ok'), true);

require_once __DIR__ . '/_layout.php';
admin_head('保存チェック');
?>
<div class="adm-head">
  <h2>保存チェック</h2>
  <a href="index.php" class="adm-btn"><i class="fa-solid fa-arrow-left"></i> 控制台へ</a>
</div>

<div class="adm-flash adm-flash-<?= $allOk ? 'ok' : 'err' ?>">
  <?= $allOk
    ? '保存環境は問題なさそうです。それでも失敗する場合は、画面に表示された具体的なエラー文を確認してください。'
    : '以下の項目に問題があります。修正後、再度保存をお試しください。' ?>
</div>

<table class="adm-table" style="max-width:720px;">
  <thead>
    <tr><th>項目</th><th>状態</th><th>詳細</th></tr>
  </thead>
  <tbody>
    <?php foreach ($report as $row): ?>
    <tr>
      <td><?= htmlspecialchars($row['label']) ?></td>
      <td><?= $row['ok'] ? '<span style="color:#6ee7a0;">OK</span>' : '<span style="color:#fca5a5;">要対応</span>' ?></td>
      <td style="font-size:13px;color:rgba(255,255,255,0.75);"><?= htmlspecialchars($row['detail']) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<div class="adm-form" style="max-width:720px;margin-top:24px;">
  <h3 style="margin-bottom:10px;">よくある対処</h3>
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
</div>
<?php admin_foot(); ?>
