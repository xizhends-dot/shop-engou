<?php
// SQL応答を固定し、MySQLドライバの実際の行変換をDB接続なしで検証する。
require_once dirname(__DIR__) . '/lib/store.php';
class SkuReadFixture {
    public $rows;
    public function query($sql) { return strpos($sql, 'FROM categories') !== false ? [['slug'=>'test','name'=>'Test','icon'=>'fa-box']] : $this->rows; }
    public function prepare($sql) { return $this; }
    public function execute($params) {}
    public function fetchAll() { return []; }
}
$fixtureDb = new SkuReadFixture();
$base = ['id'=>'TEST','category'=>'test','icon'=>'fa-box','accent'=>'#DEF13F','name'=>'Test','tag'=>'','price'=>9000,'badge'=>'','description'=>''];
$fixtureDb->rows = [
    $base,
    array_merge($base, ['skus'=>null]),
    array_merge($base, ['skus'=>'invalid']),
    array_merge($base, ['skus'=>'{"0":{"id":"A","name":"A","price":1}}']),
    array_merge($base, ['skus'=>'[{"id":"A","name":"A","price":1000},{"id":"B","name":"B","price":1500}]']),
];
$function = new ReflectionFunction('store_load_mysql');
$lines = file($function->getFileName());
$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
$source = str_replace('function store_load_mysql()', 'function sku_fixture_load_mysql()', $source);
$source = str_replace('$pdo = db_connect();', '$pdo = $GLOBALS[\'fixtureDb\'];', $source, $count);
if ($count !== 1) { throw new RuntimeException('Update DB fixture seam'); }
set_error_handler(function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
try {
    eval($source);
    $result = sku_fixture_load_mysql();
    foreach (array_slice($result['products'], 0, 4) as $product) {
        if ($product['skus'] !== [] || $product['price'] !== 9000 || $product['price_max'] !== null) { throw new RuntimeException('Legacy SQL row must remain usable'); }
    }
    $skuProduct = $result['products'][4];
    if (count($skuProduct['skus']) !== 2 || $skuProduct['price'] !== 1000 || $skuProduct['price_max'] !== 1500) { throw new RuntimeException('SQL SKU range must derive from SKUs'); }
} finally { restore_error_handler(); }
echo "PASS: MySQL row conversion for missing/null/invalid SKU column and valid SKU range (fixture, no database)\n";
