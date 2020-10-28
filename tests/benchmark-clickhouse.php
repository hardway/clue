<?php
# 加载Clue
require_once 'clue/stub.php';

# 安装phpClickHouse
if(!isset($_SERVER['PHPCLICKHOUSE'])){
    error_log("Folder of phpClickHouse need to be defined in PHPCLICKHOUSE=xxx");
    error_log("phpClickHouse could be downloaded from https://github.com/smi2/phpClickHouse");
    error_log("Set PHPCLICKHOUSE= to skip.");
    exit();
}
define('PHPCLICKHOUSE', $_SERVER['PHPCLICKHOUSE']);

# 安装SeasClick
if(!class_exists("SeasClick")){
    error_log("SeasClick should be installed via PECL");
    error_log("It could also be compiled manually from https://github.com/SeasX/SeasClick");
    exit();
}
define("SEASCLICK", true);

define("CH_HOST", '127.0.0.1');
define("SQL_CREATE_TABLE", "
    CREATE TABLE IF NOT EXISTS test.benchmark_test (
        event_date Date DEFAULT toDate(event_time),
        event_time DateTime,
        site_id Int32,
        site_key String,
        views Int32,
        v_00 Int32,
        v_55 Int32
    )
    ENGINE = SummingMergeTree(event_date, (site_id, site_key, event_time, event_date), 8192)
");

// $dataCount, $seletCount, $limit
// edit this array
$testDataSet = [
    [10000, 10, 10000],
    [100, 500, 100],
    [100, 2000, 100],
];

foreach ($testDataSet as $key => $value) {
    list($dataCount, $seletCount, $limit) = $value;
    $insertData = initData($dataCount);
    echo "\n##### dataCount: {$dataCount}, seletCount: {$seletCount}, limit: {$limit} #####\n";
    $t0 = $t = start_test();

    testClueBatch($insertData, $seletCount, $limit);
    $t = end_test($t, "Clue Batch");

    testMySQL($insertData, $seletCount, $limit);
    $t = end_test($t, "MySQL Interface");

    if(PHPCLICKHOUSE){
        require_once(PHPCLICKHOUSE.'/include.php');

        // TODO: might cuase DB::Exception: Memory limit (total) exceeded
        testPhpClickhouse($insertData, $seletCount, $limit);
        $t = end_test($t, "PhpClickhouse");
    }

    if(SEASCLICK){
        testSeasClickNonCompression($insertData, $seletCount, $limit);
        $t = end_test($t, "SeasClickNonCompression");

        testSeasClickCompression($insertData, $seletCount, $limit);
        $t = end_test($t, "SeasClickCompression");
    }

    total($t0, "Total");
}

function initData($num = 100){
    $insertData = [];
    while ($num--) {
        $insertData[] = [time()-$num, 'HASH2', random_int(1000, 10000), $num, random_int(1, 100),  random_int(1, 9)];
    }
    return $insertData;
}

function start_test(){
    return getmicrotime();
}
function getmicrotime(){
    $t = gettimeofday();
    return ($t['sec'] + $t['usec'] / 1000000);
}
function end_test($start, $name){
    global $total;
    $end = getmicrotime();
    $total += $end - $start;
    $num = number_format($end - $start, 3);
    $pad = str_repeat(" ", 60 - strlen($name) - strlen($num));
    echo $name . $pad . $num . "\n";
    return getmicrotime();
}
function total(){
    global $total;
    $pad = str_repeat("-", 32);
    echo $pad . "\n";
    $num = number_format($total, 3);
    $pad = str_repeat(" ", 32 - strlen("Total") - strlen($num));
    echo "Total" . $pad . $num . "\n";
}

function testSeasClickNonCompression($insertData, $num, $limit){
    $config = [
        "host" => CH_HOST,
        "port" => "9000",
        "compression" => false
    ];

    $db = new SeasClick($config);
    $db->execute("CREATE DATABASE IF NOT EXISTS test");
    $db->execute(SQL_CREATE_TABLE);
    $db->insert("test.benchmark_test",
        ['event_time', 'site_key', 'site_id', 'views', 'v_00', 'v_55'],
        $insertData
    );
    $a = $num;
    while ($a--) {
        $db->select('SELECT * FROM test.benchmark_test LIMIT 100');
    }
    $db->execute("DROP TABLE {table}", [
        'table' => 'test.benchmark_test'
    ]);
}

function testSeasClickCompression($insertData, $num, $limit){
    $config = [
        "host" => CH_HOST,
        "port" => "9000",
        "compression" => true
    ];

    $db = new SeasClick($config);
    $db->execute("CREATE DATABASE IF NOT EXISTS test");
    $db->execute(SQL_CREATE_TABLE);
    $db->insert("test.benchmark_test",
        ['event_time', 'site_key', 'site_id', 'views', 'v_00', 'v_55'],
        $insertData
    );
    $a = $num;
    while ($a--) {
        $db->select('SELECT * FROM test.benchmark_test LIMIT 100');
    }
    $db->execute("DROP TABLE {table}", [
        'table' => 'test.benchmark_test'
    ]);
}

function testPhpClickhouse($insertData, $num, $limit){
    $config = [
        'host' => CH_HOST,
        'port' => '8123',
        'username' => 'default',
        'password' => ''
    ];
    $db = new ClickHouseDB\Client($config);
    $db->write("CREATE DATABASE IF NOT EXISTS test");
    $db->database('test');
    $db->setTimeout(1.5);      // 1500 ms
    $db->setTimeout(10);       // 10 seconds
    $db->setConnectTimeOut(5); // 5 seconds

    $db->write(SQL_CREATE_TABLE);
    $db->insert("benchmark_test",
        $insertData,
        ['event_time', 'site_key', 'site_id', 'views', 'v_00', 'v_55']
    );
    $a = $num;
    while ($a--) {
        $json=$db->select('SELECT * FROM benchmark_test LIMIT 100')->rows();
        // $json=json_encode($json);error_log(strlen($json));
    }
    $db->write('DROP TABLE IF EXISTS benchmark_test');
}

function testClueBatch($insertData, $num, $limit){
    $config = [
        'type'=>'clickhouse',
        'host' => CH_HOST,
        'db'=>'test',
        'username' => 'default',
        'password' => ''
    ];
    $db = Clue\Database::create($config);
    $db->exec("CREATE DATABASE IF NOT EXISTS test");

    $db->exec(SQL_CREATE_TABLE);

    $db->insert("benchmark_test",
        $insertData,
        ['event_time', 'site_key', 'site_id', 'views', 'v_00', 'v_55']
    );

    $a = $num;
    while ($a--) {
        $json=$db->get_results('SELECT * FROM benchmark_test LIMIT 100');
        // $json=json_encode($json);error_log(strlen($json));
    }
    $db->exec('DROP TABLE IF EXISTS benchmark_test');
}

function testMySQL($insertData, $num, $limit){
    $config = [
        'type'=>'mysql',
        'host' => CH_HOST,
        'port'=>3307,
        'db'=>'test',
        'username' => 'default',
        'password' => ''
    ];
    $db = Clue\Database::create($config);
    $db->exec("CREATE DATABASE IF NOT EXISTS test");

    $db->exec(SQL_CREATE_TABLE);

    foreach($insertData as $r){
        $r=array_combine(['event_time', 'site_key', 'site_id', 'views', 'v_00', 'v_55'], $r);
        $db->insert("benchmark_test", $r);
    }

    $a = $num;
    while ($a--) {
        $json=$db->get_results('SELECT * FROM benchmark_test LIMIT 100');
        // $json=json_encode($json);error_log(strlen($json));
    }
    $db->exec('DROP TABLE IF EXISTS benchmark_test');
}
