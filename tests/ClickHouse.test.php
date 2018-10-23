<?php
    require_once dirname(__DIR__).'/stub.php';

    @define("FATAL_ERROR_SCRIPT", '/tmp/clue-fatal-error.php');
    @define("FATAL_ERROR_LOG", '/tmp/clue-fatal-error.log');

    class Test_Clickhouse extends PHPUnit_Framework_TestCase{
        protected $backupGlobals = FALSE;
        protected $backupGlobalsBlacklist = array('mysql');

        protected function setUp(){
            $config=[
                'type'=>'clickhouse',
                'host' => 'db.dev',
                'user' => 'default',
                'pass' => '',
                'db'=>'test',

                'connection_timeout'=>1,    // 性能测试
                'timeout'=>5,
                // 'debug'=>true
            ];

            $this->db=Clue\Database::create($config);
            $this->db->exec("DROP DATABASE IF EXISTS test");
            $this->db->exec('CREATE DATABASE test');
        }

        protected function tearDown(){
        }

        function test_create_table(){
            $table='dummy';
            $this->assertFalse($this->db->has_table($table));
            $this->db->create_table($table, ['foo'=>'int', 'bar'=>'varchar'], ['engine'=>'Memory']);
            $this->assertTrue($this->db->has_table($table));
        }

        function test_insert_datetime(){
            $this->db->create_table('dummy', ['id'=>'int', 'last_update'=>'datetime'], ['engine'=>'Memory']);
            $this->db->insert("dummy", [123, date("Y-m-d H:i:s")], ['id', 'last_update']);
        }
    }


// $ok=$db->exec("CREATE TABLE test (a UInt8, b String) ENGINE = Memory");
// printf("Create Table : %s\n", $ok);

// $ok=$db->query("desc test");
// printf("DESC Table   : %s\n", json_encode($ok));
// printf("Show Tables  : %s\n", implode(", ", $db->get_col("show tables")));

// $db->insert('test', [[1,'a'], [2,'b']]);
// printf("Get Var      : %s\n", $db->get_var("select * from test"));
// printf("Get Column   : %s\n", implode(", ", $db->get_col("select a from test")));
// printf("Get Row      : %s\n", json_encode($db->get_row("select * from test where a=2")));
// printf("Get Results  : %s\n", json_encode($db->get_results("select * from test")));

// $ok=$db->exec("drop table test");
// printf("Drop Table   : %s\n", $ok);
// printf("Show Tables  : %s\n", implode(", ", $db->get_col("show tables")));
