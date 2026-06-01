<?php
/**
 * 管理画面多言語（中文 / 日本語）
 */

function admin_lang_init() {
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['zh', 'ja'], true)) {
        $_SESSION['admin_lang'] = $_GET['lang'];
    }
    $lang = $_SESSION['admin_lang'] ?? 'zh';
    $file = __DIR__ . '/lang/' . $lang . '.php';
    if (!is_file($file)) {
        $lang = 'zh';
        $file = __DIR__ . '/lang/zh.php';
    }
    $GLOBALS['_admin_lang']     = $lang;
    $GLOBALS['_admin_strings']  = require $file;
}

function admin_lang() {
    return $GLOBALS['_admin_lang'] ?? 'zh';
}

/** @param array<string, string|int> $replace */
function __($key, array $replace = []) {
    $s = $GLOBALS['_admin_strings'][$key] ?? $key;
    foreach ($replace as $k => $v) {
        $s = str_replace('{' . $k . '}', (string)$v, $s);
    }
    return $s;
}

function admin_title($pageKey) {
    return __($pageKey) . ' | ' . __('meta.admin_suffix');
}

function admin_lang_url($lang) {
    $q = $_GET;
    $q['lang'] = $lang;
    $path = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '';
    return $path . '?' . http_build_query($q);
}

function admin_lang_switcher_html() {
    $cur = admin_lang();
    $zh = admin_lang_url('zh');
    $ja = admin_lang_url('ja');
    $zhc = $cur === 'zh' ? ' active' : '';
    $jac = $cur === 'ja' ? ' active' : '';
    return '<div class="adm-lang-switch" role="group" aria-label="' . htmlspecialchars(__('nav.lang')) . '">'
        . '<a href="' . htmlspecialchars($zh) . '" class="adm-lang-btn' . $zhc . '">中文</a>'
        . '<a href="' . htmlspecialchars($ja) . '" class="adm-lang-btn' . $jac . '">日本語</a>'
        . '</div>';
}

/** 診断レポートのラベル・詳細を管理画面言語に */
function admin_health_translate(array $row) {
    if (admin_lang() === 'ja') {
        return $row;
    }
    $labels  = $GLOBALS['_admin_strings']['_health_labels'] ?? [];
    $details = $GLOBALS['_admin_strings']['_health_details'] ?? [];
    $label   = $labels[$row['label']] ?? $row['label'];
    $detail  = $details[$row['detail']] ?? $row['detail'];
    if ($detail === $row['detail'] && preg_match('/^テーブル `(.+)`$/u', $row['label'], $m)) {
        $label = __('health.table', ['name' => $m[1]]);
    }
    if ($detail === $row['detail'] && strpos($row['detail'], 'host=') === 0) {
        $detail = $row['detail'];
    }
    return ['label' => $label, 'ok' => $row['ok'], 'detail' => $detail];
}
