<?php
// SQL応答を固定し、実際のMySQL行変換・保存・DDL順序をDB接続なしで検証する。
require_once dirname(__DIR__) . '/lib/store.php';
class SkuReadFixture {
    public $rows;
    public function query($sql) { return strpos($sql, 'FROM categories') !== false ? [['slug'=>'test','name'=>'Test','icon'=>'fa-box']] : $this->rows; }
    public function prepare($sql) { return $this; }
    public function execute($params) {}
    public function fetchAll() { return []; }
}
function sku_fixture_function($name, $fixtureName, $global) {
    $function = new ReflectionFunction($name);
    $lines = file($function->getFileName());
    $source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
    $source = str_replace('function ' . $name . '(', 'function ' . $fixtureName . '(', $source);
    $source = str_replace('$pdo = db_connect();', '$pdo = $GLOBALS[\'' . $global . '\'];', $source, $count);
    if ($count !== 1) { throw new RuntimeException('Update DB fixture seam'); }
    eval($source);
}
function verify_mysql_sku($ok, $message) {
    if (!$ok) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
}

$fixtureDb = new SkuReadFixture();
$base = ['id'=>'TEST','category'=>'test','icon'=>'fa-box','accent'=>'#DEF13F','name'=>'Test','tag'=>'','price'=>9000,'badge'=>'','description'=>''];
$groups = [['name'=>'カラー','options'=>['RED','BLUE']], ['name'=>'サイズ','options'=>['S','M']]];
$skus = [['id'=>'A','price'=>1000,'options'=>['RED','S']], ['id'=>'B','price'=>1500,'options'=>['BLUE','M']]];
$groupJson = json_encode($groups, JSON_UNESCAPED_UNICODE);
$skuJson = json_encode($skus);
$fixtureDb->rows = [
    $base,
    array_merge($base, ['skus'=>null, 'sku_groups'=>null]),
    array_merge($base, ['skus'=>'invalid']),
    array_merge($base, ['skus'=>'{"0":{"id":"A","name":"A","price":1}}']),
    array_merge($base, ['skus'=>'[{"id":"A","name":"A","price":1000},{"id":"B","name":"B","price":1500}]']),
    array_merge($base, ['skus'=>$skuJson, 'sku_groups'=>$groupJson]),
    array_merge($base, ['skus'=>$skuJson, 'sku_groups'=>'invalid']),
    array_merge($base, ['skus'=>$skuJson, 'sku_groups'=>'[{"name":"カラー","options":{"0":"RED","1":"BLUE"}}]']),
    array_merge($base, ['skus'=>'[{"id":"A","price":1000,"options":{"0":"RED","1":"S"}}]', 'sku_groups'=>$groupJson]),
];
set_error_handler(function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
try {
    sku_fixture_function('store_load_mysql', 'sku_fixture_load_mysql', 'fixtureDb');
    $result = sku_fixture_load_mysql();
    foreach (array_slice($result['products'], 0, 4) as $product) {
        verify_mysql_sku($product['skus'] === [] && $product['sku_groups'] === [] && $product['price'] === 9000 && $product['price_max'] === null, 'missing/null/invalid SQL SKU columns remain usable');
    }
    $product = $result['products'][4];
    verify_mysql_sku(count($product['skus']) === 2 && $product['price'] === 1000 && $product['price_max'] === 1500 && array_keys($product['skus'][0]) === ['id','name','price'], 'legacy SQL SKU range and output structure unchanged');
    $product = $result['products'][5];
    verify_mysql_sku($product['sku_groups'] === $groups && $product['skus'][0]['options'] === ['RED','S'] && $product['skus'][0]['name'] === 'RED / S' && $product['price'] === 1000 && $product['price_max'] === 1500, 'SQL group/options normalization and derived price range');
    foreach (array_slice($result['products'], 6) as $product) {
        verify_mysql_sku($product['skus'] === [] && $product['price'] === 9000, 'invalid SQL group/options are not displayed');
    }
} finally { restore_error_handler(); }

class SkuWriteStatement {
    private $db;
    private $sql;
    public function __construct($db, $sql) { $this->db = $db; $this->sql = $sql; }
    public function fetch() {
        preg_match("/LIKE '([^']+)'/", $this->sql, $match);
        return isset($this->db->columns[$match[1]]) ? ['Field'=>$match[1]] : false;
    }
    public function execute($params) { $this->db->executed[] = [$this->sql, $params]; }
}
class SkuWriteFixture {
    public $columns = [];
    public $ddl = [];
    public $executed = [];
    public $transaction = false;
    public $committed = false;
    public function query($sql) { return new SkuWriteStatement($this, $sql); }
    public function prepare($sql) { return new SkuWriteStatement($this, $sql); }
    public function exec($sql) {
        if (strpos($sql, 'ALTER TABLE') === 0) {
            if ($this->transaction) { throw new RuntimeException('DDL ran inside transaction'); }
            preg_match('/ADD COLUMN ([a-z_]+)/', $sql, $match);
            $this->columns[$match[1]] = true;
            $this->ddl[] = $sql;
        }
    }
    public function beginTransaction() {
        if (!isset($this->columns['price_max'], $this->columns['skus'], $this->columns['sku_groups'])) {
            throw new RuntimeException('Required columns missing at transaction start');
        }
        $this->transaction = true;
    }
    public function commit() { $this->committed = true; $this->transaction = false; }
}
sku_fixture_function('store_save_mysql', 'sku_fixture_save_mysql', 'fixtureWriteDb');
$data = ['categories'=>['test'=>['name'=>'Test']], 'products'=>[array_merge($base, ['sku_groups'=>$groups,'skus'=>$skus,'images'=>[]])]];
foreach ([[], ['price_max'=>true,'skus'=>true], ['price_max'=>true,'skus'=>true,'sku_groups'=>true]] as $columns) {
    $fixtureWriteDb = new SkuWriteFixture();
    $fixtureWriteDb->columns = $columns;
    verify_mysql_sku(sku_fixture_save_mysql($data) && $fixtureWriteDb->committed, 'SQL fixture saves grouped product with missing columns repaired before transaction');
    verify_mysql_sku(count($fixtureWriteDb->ddl) === 3 - count($columns), 'SQL migration only adds missing columns and preserves existing skus column');
    $inserts = array_values(array_filter($fixtureWriteDb->executed, function ($entry) { return strpos($entry[0], 'INSERT INTO products') === 0; }));
    $params = $inserts[0][1];
    verify_mysql_sku(count($params) === 16 && substr_count($inserts[0][0], '?') === 16 && $params[6] === 1000 && $params[7] === 1500 && json_decode($params[9], true) === $groups && json_decode($params[8], true)[0]['options'] === ['RED','S'], 'SQL upsert binds both group/SKU JSON with matching derived prices');
}
echo "PASS: MySQL fixtures completed without a real database\n";
