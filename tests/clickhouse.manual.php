<?php

require_once 'clue/stub.php';

$config=[
    'type'=>'clickhouse',
    'host' => 'db.xen',
    'user' => 'default',
    'pass' => '',
    'db'=>'default',
    // 'debug'=>true
];

$db=Clue\Database::create($config);

$ok=$db->exec("CREATE TABLE test (a UInt8, b String) ENGINE = Memory");
printf("Create Table : %s\n", $ok);

$ok=$db->query("desc test");
printf("DESC Table   : %s\n", json_encode($ok));
printf("Show Tables  : %s\n", implode(", ", $db->get_col("show tables")));

$db->insert('test', [[1,'a'], [2,'b']]);
printf("Get Var      : %s\n", $db->get_var("select * from test"));
printf("Get Column   : %s\n", implode(", ", $db->get_col("select a from test")));
printf("Get Row      : %s\n", json_encode($db->get_row("select * from test where a=2")));
printf("Get Results  : %s\n", json_encode($db->get_results("select * from test")));

$ok=$db->exec("drop table test");
printf("Drop Table   : %s\n", $ok);
printf("Show Tables  : %s\n", implode(", ", $db->get_col("show tables")));
