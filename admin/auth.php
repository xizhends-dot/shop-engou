<?php
/**
 * 管理画面の共通ブートストラップ
 * 各管理ページの冒頭で require する。$config と各種ヘルパーを利用可能にする。
 */
require_once __DIR__ . '/../lib/store.php';
$config = require __DIR__ . '/../config.php';
admin_session_start();

/** フラッシュメッセージ */
function set_flash($msg, $type = 'ok') { $_SESSION['flash'] = ['msg' => $msg, 'type' => $type]; }
function take_flash() {
    if (empty($_SESSION['flash'])) { return null; }
    $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f;
}
