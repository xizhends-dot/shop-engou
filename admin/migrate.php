<?php
/**
 * データ移行ツール：data/products.json → MySQL
 * ------------------------------------------------------------------
 * 既に JSON モードで商品を編集していて、その内容を MySQL に移したいときに使用。
 * - ブラウザ: /shop/admin/migrate.php（要ログイン）
 * - CLI:      php shop/admin/migrate.php
 * 実行すると JSON の全商品を MySQL に書き込みます（MySQL側は全置換）。
 */
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
            ? ['type' => 'ok',  'msg' => "MySQL へ取り込みました：商品 {$n} 件。"]
            : ['type' => 'err', 'msg' => 'MySQL への保存に失敗しました。'];
    } catch (Throwable $e) {
        $result = ['type' => 'err', 'msg' => 'エラー: ' . $e->getMessage()];
    }

    if ($cli) {
        echo $result['msg'] . PHP_EOL;
        exit($result['type'] === 'ok' ? 0 : 1);
    }
}

require_once __DIR__ . '/_layout.php';
$token = csrf_token();
admin_head('データ移行（JSON → MySQL）');
?>
<div class="adm-head">
  <h2>データ移行：JSON → MySQL</h2>
  <a href="index.php" class="adm-btn"><i class="fa-solid fa-arrow-left"></i> 一覧へ</a>
</div>

<?php if ($result): ?>
<div class="adm-flash adm-flash-<?= $result['type'] === 'ok' ? 'ok' : 'err' ?>"><?= htmlspecialchars($result['msg']) ?></div>
<?php endif; ?>

<div class="adm-form" style="max-width:680px;">
  <p class="adm-note">
    現在 <code>data/products.json</code> にある商品データを、MySQL（<code><?= htmlspecialchars(shop_config()['db']['name'] ?? '') ?></code>）へ取り込みます。<br>
    <strong>事前に</strong> <code>db/schema.sql</code> でテーブルを作成し、<code>config.php</code> の <code>db</code> 接続情報を設定してください。<br>
    実行すると MySQL 側の既存データは<strong>全て置き換え</strong>られます。
  </p>
  <form method="post" onsubmit="return confirm('JSONの内容でMySQLを上書きします。よろしいですか？');">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
    <button type="submit" class="adm-btn adm-btn-primary"><i class="fa-solid fa-database"></i> MySQL へ取り込む</button>
  </form>
  <p class="adm-note" style="margin-top:18px;">
    取り込み後、<code>config.php</code> の <code>'storage'</code> を <code>'mysql'</code> に変更すると、サイト全体が MySQL を参照します。
  </p>
</div>
<?php admin_foot(); ?>
