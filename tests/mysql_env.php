<?php
/**
 * MySQL 测试连接配置（供 tests/*.test.php 复用）
 *
 * 可用环境变量覆盖：
 *   CLUE_TEST_MYSQL_HOST  默认 127.0.0.1
 *   CLUE_TEST_MYSQL_PORT  默认 3306
 *   CLUE_TEST_MYSQL_USER  默认 root
 *   CLUE_TEST_MYSQL_PASS  默认 root（显式空字符串表示空密码）
 *   CLUE_TEST_MYSQL_DB    默认 test
 */
function clue_test_mysql_config(array $overrides=[]){
    $env=function($name, $default){
        $v=getenv($name);
        return ($v===false || $v==='') ? $default : $v;
    };

    $pass=getenv('CLUE_TEST_MYSQL_PASS');

    return $overrides + [
        'type'=>'mysql',
        'host'=>$env('CLUE_TEST_MYSQL_HOST', '127.0.0.1'),
        'port'=>(int)$env('CLUE_TEST_MYSQL_PORT', 3306),
        'username'=>$env('CLUE_TEST_MYSQL_USER', 'root'),
        'password'=>$pass===false ? 'root' : $pass,
        'db'=>$env('CLUE_TEST_MYSQL_DB', 'test'),
    ];
}
