<?php
/**
 * shop.engou.jp — データストア & 管理ヘルパー
 * ------------------------------------------------------------------
 * 商品データは data/products.json に保存（ファイルベース・DB不要）。
 * フロント（index.php / product.php）と管理画面（admin/）が共通で利用する。
 */

if (!defined('SHOP_BASE')) {
    define('SHOP_BASE', dirname(__DIR__));               // .../shop
}
define('STORE_FILE', SHOP_BASE . '/data/products.json');
define('UPLOAD_DIR', SHOP_BASE . '/images/products');     // 物理パス
define('UPLOAD_REL', 'images/products');                  // 表示用相対パス（shop/ 起点）
define('BANNERS_FILE', SHOP_BASE . '/data/banners.json'); // バナー画像リスト
define('BANNER_DIR', SHOP_BASE . '/images/banners');      // バナー物理パス
define('BANNER_REL', 'images/banners');                   // バナー相対パス
define('BANNER_SETTINGS_FILE', SHOP_BASE . '/data/banner_settings.json'); // バナー共通設定
define('FEATURED_FILE', SHOP_BASE . '/data/featured.json'); // おすすめ商品ID（順序つき）
define('FEATURED_MAX', 12);                                 // おすすめ最大件数

/** 価格表示ヘルパー（全ページ共通） */
function yen($n) {
    return '<span class="yen">¥</span>' . number_format((int)$n) . '<span class="tax">（税込）</span>';
}

/** 5段階の星を HTML で出力（小数は半星） */
function render_stars($rating) {
    $rating = max(0, min(5, (float)$rating));
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i)            { $cls = 'fa-solid fa-star'; }
        elseif ($rating >= $i - 0.5)  { $cls = 'fa-solid fa-star-half-stroke'; }
        else                          { $cls = 'fa-regular fa-star'; }
        $html .= '<i class="' . $cls . '"></i>';
    }
    return $html;
}

/* ---------------- 初期データ（products.json が無い場合のシード） ---------------- */
function store_seed() {
    return [
        'categories' => [
            'beauty'  => ['name' => '美容家電', 'icon' => 'fa-wand-magic-sparkles'],
            'home'    => ['name' => '生活家電', 'icon' => 'fa-house-chimney'],
            'kitchen' => ['name' => 'キッチン', 'icon' => 'fa-utensils'],
        ],
        'products' => [],
    ];
}

/* ---------------- 設定の取得（1回だけ読み込み） ---------------- */
function shop_config() {
    static $cfg = null;
    if ($cfg === null) { $cfg = require SHOP_BASE . '/config.php'; }
    return $cfg;
}

function store_driver() {
    $cfg = shop_config();
    return (isset($cfg['storage']) && $cfg['storage'] === 'mysql') ? 'mysql' : 'json';
}

/* ================================================================
   読み込み / 保存（ドライバで分岐）
   どちらも ['categories' => [...], 'products' => [...]] を扱う。
   ================================================================ */
function store_load() {
    return store_driver() === 'mysql' ? store_load_mysql() : store_load_json();
}
function store_save($data) {
    return store_driver() === 'mysql' ? store_save_mysql($data) : store_save_json($data);
}

/* ---------------- JSON ドライバ ---------------- */
function store_load_json() {
    if (!is_file(STORE_FILE)) {
        return store_seed();
    }
    $raw = file_get_contents(STORE_FILE);
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['categories']) || !isset($data['products'])) {
        return store_seed();
    }
    // 後方互換: 旧 'image'(単数) を 'images'(配列) に正規化 + attributes 既定値
    foreach ($data['products'] as &$p) {
        if (!isset($p['images']) || !is_array($p['images'])) {
            $p['images'] = (isset($p['image']) && $p['image'] !== '') ? [$p['image']] : [];
        }
        unset($p['image']);
        if (!isset($p['attributes']) || !is_array($p['attributes'])) { $p['attributes'] = []; }
        $p['rating']  = isset($p['rating']) ? (float)$p['rating'] : 0;
        $p['reviews'] = isset($p['reviews']) ? (int)$p['reviews'] : 0;
    }
    unset($p);
    return $data;
}

function store_save_json($data) {
    $dir = dirname(STORE_FILE);
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) { return false; }
    return file_put_contents(STORE_FILE, $json, LOCK_EX) !== false;
}

/* ---------------- MySQL ドライバ ---------------- */
function db_connect() {
    static $pdo = null;
    if ($pdo instanceof PDO) { return $pdo; }
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('PHP拡張 pdo_mysql が有効ではありません。サーバーで有効化してください。');
    }
    $cfg = shop_config();
    $db  = $cfg['db'] ?? [];
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $db['host'] ?? '127.0.0.1', $db['port'] ?? 3306, $db['name'] ?? '', $db['charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, $db['user'] ?? '', $db['pass'] ?? '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

function store_load_mysql() {
    $pdo = db_connect();
    $cats = [];
    foreach ($pdo->query('SELECT slug, name, icon FROM categories ORDER BY sort, slug') as $r) {
        $cats[$r['slug']] = ['name' => $r['name'], 'icon' => $r['icon']];
    }
    $products = [];
    $imgStmt = $pdo->prepare('SELECT path FROM product_images WHERE product_id = ? ORDER BY sort, id');
    foreach ($pdo->query('SELECT * FROM products ORDER BY sort, id') as $r) {
        $imgStmt->execute([$r['id']]);
        $images = array_map(function ($x) { return $x['path']; }, $imgStmt->fetchAll());
        $attrs = isset($r['attributes']) ? json_decode((string)$r['attributes'], true) : [];
        $products[] = [
            'id'         => $r['id'],
            'category'   => $r['category'],
            'icon'       => $r['icon'],
            'accent'     => $r['accent'],
            'images'     => $images,
            'name'       => $r['name'],
            'tag'        => $r['tag'],
            'price'      => (int)$r['price'],
            'badge'      => $r['badge'],
            'desc'       => $r['description'],
            'attributes' => is_array($attrs) ? $attrs : [],
            'rating'     => isset($r['rating']) ? (float)$r['rating'] : 0,
            'reviews'    => isset($r['reviews']) ? (int)$r['reviews'] : 0,
        ];
    }
    return ['categories' => $cats, 'products' => $products];
}

/** 全データをトランザクションで置き換え（呼び出し側は配列全体を渡す） */
function store_save_mysql($data) {
    $pdo = db_connect();
    try {
        $pdo->beginTransaction();
        $pdo->exec('DELETE FROM product_images');
        $pdo->exec('DELETE FROM products');
        $pdo->exec('DELETE FROM categories');

        $cs = $pdo->prepare('INSERT INTO categories (slug, name, icon, sort) VALUES (?, ?, ?, ?)');
        $ci = 0;
        foreach ($data['categories'] as $slug => $c) {
            $cs->execute([$slug, $c['name'] ?? $slug, $c['icon'] ?? 'fa-tag', $ci++]);
        }

        $ps = $pdo->prepare('INSERT INTO products (id, category, icon, accent, name, tag, price, badge, description, attributes, rating, reviews, sort) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $is = $pdo->prepare('INSERT INTO product_images (product_id, path, sort) VALUES (?, ?, ?)');
        $pi = 0;
        foreach ($data['products'] as $p) {
            $attrsJson = json_encode($p['attributes'] ?? [], JSON_UNESCAPED_UNICODE);
            $ps->execute([
                $p['id'], $p['category'], $p['icon'] ?? 'fa-box', $p['accent'] ?? '#DEF13F',
                $p['name'], $p['tag'] ?? '', (int)$p['price'], $p['badge'] ?? '', $p['desc'] ?? '', $attrsJson,
                (float)($p['rating'] ?? 0), (int)($p['reviews'] ?? 0), $pi++,
            ]);
            $ii = 0;
            foreach (($p['images'] ?? []) as $img) {
                $is->execute([$p['id'], $img, $ii++]);
            }
        }
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        return false;
    }
}

/* ---------------- 商品検索 ---------------- */
function store_find_index($products, $id) {
    foreach ($products as $i => $p) {
        if ((string)$p['id'] === (string)$id) { return $i; }
    }
    return -1;
}

/* ---------------- ID バリデーション（半角英数・ハイフン・アンダースコア） ---------------- */
function store_valid_id($id) {
    return (bool) preg_match('/^[A-Za-z0-9_-]{1,64}$/', (string)$id);
}

/* ================================================================
   認証 / セッション
   ================================================================ */
function admin_session_start() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('engou_shop_admin');
        session_start();
    }
}

/** 入力パスワードを config の値（平文 or bcryptハッシュ）と照合 */
function admin_verify_password($input, $config) {
    $stored = isset($config['admin_password']) ? (string)$config['admin_password'] : '';
    if ($stored === '') { return false; }
    if (strpos($stored, '$2y$') === 0 || strpos($stored, '$argon2') === 0) {
        return password_verify($input, $stored);
    }
    return hash_equals($stored, (string)$input);
}

function admin_is_logged_in() {
    admin_session_start();
    return !empty($_SESSION['engou_admin_ok']);
}

function admin_require_login() {
    if (!admin_is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function admin_login() {
    admin_session_start();
    session_regenerate_id(true);
    $_SESSION['engou_admin_ok'] = true;
}

function admin_logout() {
    admin_session_start();
    $_SESSION = [];
    session_destroy();
}

/* ================================================================
   CSRF トークン
   ================================================================ */
function csrf_token() {
    admin_session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check($token) {
    admin_session_start();
    return !empty($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}

/* ================================================================
   画像アップロード
   $file = $_FILES['xxx'] の1要素
   戻り値: 成功なら相対パス（images/products/xxx.jpg）, 失敗なら null
   ================================================================ */
function store_handle_upload($file, $absDir = UPLOAD_DIR, $relDir = UPLOAD_REL) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) { return null; }
    if (!is_uploaded_file($file['tmp_name'])) { return null; }
    if ($file['size'] > 8 * 1024 * 1024) { return null; } // 8MB上限

    $mimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    $ext = null;

    // 1) getimagesize による判定（fileinfo拡張に依存しない）
    $info = @getimagesize($file['tmp_name']);
    if ($info !== false && isset($info['mime']) && isset($mimeToExt[$info['mime']])) {
        $ext = $mimeToExt[$info['mime']];
    }
    // 2) fileinfo が使える環境なら併用
    if ($ext === null && function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (isset($mimeToExt[$mime])) { $ext = $mimeToExt[$mime]; }
    }
    // 3) 最後の手段として元ファイルの拡張子をホワイトリスト照合
    if ($ext === null) {
        $extMap = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'webp' => 'webp', 'gif' => 'gif'];
        $oe = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (isset($extMap[$oe])) { $ext = $extMap[$oe]; }
    }
    if ($ext === null) { return null; }

    if (!is_dir($absDir)) { @mkdir($absDir, 0775, true); }
    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $absDir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) { return null; }
    return $relDir . '/' . $name;
}

/* ---------------- バナー（トップのスライド画像） ---------------- */
function banners_load() {
    if (!is_file(BANNERS_FILE)) { return []; }
    $d = json_decode(file_get_contents(BANNERS_FILE), true);
    if (!is_array($d)) { return []; }
    $out = [];
    foreach ($d as $b) {
        if (is_string($b) && $b !== '') {
            $out[] = ['image' => $b, 'link' => '', 'title' => '', 'subtitle' => ''];
        } elseif (is_array($b) && !empty($b['image'])) {
            $out[] = [
                'image'    => $b['image'],
                'link'     => $b['link'] ?? '',
                'title'    => $b['title'] ?? '',
                'subtitle' => $b['subtitle'] ?? '',
            ];
        }
    }
    return $out;
}

function banners_save($arr) {
    $dir = dirname(BANNERS_FILE);
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $json = json_encode(array_values($arr), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) { return false; }
    return file_put_contents(BANNERS_FILE, $json, LOCK_EX) !== false;
}

/* ---------------- バナー共通設定（小見出し・ボタン・既定文案） ---------------- */
function banner_settings_defaults() {
    return [
        'eyebrow'   => 'ENGOU ONLINE SHOP',
        'title'     => '厳選した<span class="accent">こだわりの逸品</span>を、<br>あなたの毎日へ',
        'subtitle'  => '美容家電 · 生活家電 · キッチン家電<br>品質にこだわった商品を、リーズナブルにお届けします。',
        'btn1_text' => '商品を見る',
        'btn1_link' => '#featured',
        'btn2_text' => 'お問い合わせ',
        'btn2_link' => 'contact.php',
    ];
}

function banner_settings_load() {
    $d = is_file(BANNER_SETTINGS_FILE) ? json_decode(file_get_contents(BANNER_SETTINGS_FILE), true) : [];
    if (!is_array($d)) { $d = []; }
    return array_merge(banner_settings_defaults(), $d);
}

function banner_settings_save($arr) {
    $keys = array_keys(banner_settings_defaults());
    $out = [];
    foreach ($keys as $k) { $out[$k] = isset($arr[$k]) ? (string)$arr[$k] : ''; }
    $dir = dirname(BANNER_SETTINGS_FILE);
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $json = json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) { return false; }
    return file_put_contents(BANNER_SETTINGS_FILE, $json, LOCK_EX) !== false;
}

/* ---------------- おすすめ商品（ID の順序つきリスト） ---------------- */
function featured_load() {
    if (!is_file(FEATURED_FILE)) { return []; }
    $d = json_decode(file_get_contents(FEATURED_FILE), true);
    if (!is_array($d)) { return []; }
    return array_values(array_filter($d, 'is_string'));
}

function featured_save($ids) {
    $ids = array_values(array_unique(array_filter((array)$ids, 'is_string')));
    $ids = array_slice($ids, 0, FEATURED_MAX);
    $dir = dirname(FEATURED_FILE);
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $json = json_encode($ids, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) { return false; }
    return file_put_contents(FEATURED_FILE, $json, LOCK_EX) !== false;
}

/** おすすめ設定から実際の商品配列を取得（存在しないIDは除外、順序維持） */
function featured_products($data) {
    $ids = featured_load();
    $out = [];
    foreach ($ids as $id) {
        $i = store_find_index($data['products'], $id);
        if ($i >= 0) { $out[] = $data['products'][$i]; }
    }
    return $out;
}

/** 画像拡張子か */
function store_is_image_ext($f) {
    return in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
}

/** アップロード済み画像の一覧（サブフォルダも再帰的に・相対パス、新しい順） */
function store_list_images() {
    $out = [];
    if (!is_dir(UPLOAD_DIR)) { return $out; }
    $baseLen = strlen(str_replace('\\', '/', UPLOAD_DIR));
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(UPLOAD_DIR, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if ($file->isFile() && store_is_image_ext($file->getFilename())) {
            $abs = str_replace('\\', '/', $file->getPathname());
            $out[] = UPLOAD_REL . substr($abs, $baseLen);
        }
    }
    rsort($out);
    return $out;
}

/* ---------------- 画像フォルダ管理 ---------------- */
/** サブディレクトリ（images/products 起点）を安全に正規化。不正なら '' */
function media_safe_dir($dir) {
    $dir = str_replace('\\', '/', (string)$dir);
    $parts = array_filter(explode('/', $dir), function ($p) { return $p !== '' && $p !== '.' && $p !== '..'; });
    foreach ($parts as $p) {
        if (!preg_match('/^[A-Za-z0-9_\-]+$/', $p)) { return ''; }
    }
    return implode('/', $parts);
}

/** フォルダ/ファイル名として安全か */
function media_safe_name($name) {
    return preg_match('/^[A-Za-z0-9_\-]{1,64}$/', (string)$name) ? (string)$name : '';
}

/** 指定サブフォルダの中身（folders, images の相対パス）を返す */
function media_list($dir) {
    $dir = media_safe_dir($dir);
    $rel = $dir === '' ? UPLOAD_REL : UPLOAD_REL . '/' . $dir;
    $abs = SHOP_BASE . '/' . $rel;
    $folders = [];
    $images  = [];
    if (is_dir($abs)) {
        foreach (scandir($abs) as $f) {
            if ($f === '.' || $f === '..' || $f[0] === '.') { continue; }
            $p = $abs . '/' . $f;
            if (is_dir($p)) { $folders[] = $f; }
            elseif (store_is_image_ext($f)) { $images[] = $rel . '/' . $f; }
        }
    }
    sort($folders);
    rsort($images);
    return ['folders' => $folders, 'images' => $images];
}

/** フォルダのツリー構造を再帰取得：[{name, path, children:[...]}, ...] */
function media_tree($rel = '') {
    $rel = media_safe_dir($rel);
    $abs = SHOP_BASE . '/' . UPLOAD_REL . ($rel === '' ? '' : '/' . $rel);
    $out = [];
    if (is_dir($abs)) {
        $entries = scandir($abs);
        sort($entries);
        foreach ($entries as $f) {
            if ($f === '.' || $f === '..' || $f[0] === '.') { continue; }
            if (is_dir($abs . '/' . $f)) {
                $childRel = $rel === '' ? $f : $rel . '/' . $f;
                $out[] = ['name' => $f, 'path' => $childRel, 'children' => media_tree($childRel)];
            }
        }
    }
    return $out;
}

/** サブフォルダを作成 */
function media_create_folder($dir, $name) {
    $dir  = media_safe_dir($dir);
    $name = media_safe_name($name);
    if ($name === '') { return false; }
    $abs = SHOP_BASE . '/' . ($dir === '' ? UPLOAD_REL : UPLOAD_REL . '/' . $dir) . '/' . $name;
    if (is_dir($abs)) { return true; }
    return @mkdir($abs, 0775, true);
}

/** 空のサブフォルダを削除 */
function media_delete_folder($dir) {
    $dir = media_safe_dir($dir);
    if ($dir === '') { return false; }
    $abs = SHOP_BASE . '/' . UPLOAD_REL . '/' . $dir;
    if (!is_dir($abs)) { return false; }
    return @rmdir($abs); // 空でなければ失敗する（安全）
}

/** カテゴリキー => そのカテゴリの商品数 */
function category_usage($data) {
    $u = [];
    foreach ($data['products'] as $p) {
        $c = $p['category'];
        $u[$c] = ($u[$c] ?? 0) + 1;
    }
    return $u;
}

/** 画像パス => 利用している商品ID配列 */
function store_image_usage($data) {
    $u = [];
    foreach ($data['products'] as $p) {
        foreach (($p['images'] ?? []) as $img) {
            $u[$img][] = $p['id'];
        }
    }
    return $u;
}

/** 全商品から指定画像パスの参照を取り除く（戻り値: 解除した件数） */
function store_unlink_image_refs(&$data, $relPath) {
    $cnt = 0;
    foreach ($data['products'] as &$p) {
        if (!empty($p['images']) && in_array($relPath, $p['images'], true)) {
            $p['images'] = array_values(array_filter($p['images'], function ($x) use ($relPath) { return $x !== $relPath; }));
            $cnt++;
        }
    }
    unset($p);
    return $cnt;
}

/** 相対パスの画像ファイルを物理削除（images/ 配下のみ・安全確認付き） */
function store_delete_image($relPath) {
    $relPath = (string)$relPath;
    if (strpos($relPath, 'images/') !== 0) { return; }             // images/ 配下のみ許可
    if (strpos($relPath, '..') !== false) { return; }
    $abs = SHOP_BASE . '/' . $relPath;
    if (is_file($abs)) { @unlink($abs); }
}
