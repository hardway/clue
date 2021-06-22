<?php
    require_once dirname(__DIR__).'/stub.php';

    @define("FATAL_ERROR_SCRIPT", '/tmp/clue-fatal-error.php');
    @define("FATAL_ERROR_LOG", '/tmp/clue-fatal-error.log');

    class clickhouse_test extends PHPUnit\Framework\TestCase{
        protected $backupGlobals = FALSE;
        protected $backupGlobalsBlacklist = array('mysql');

        protected function setUp():void {
            $config=[
                'type'=>'clickhouse',
                'host' => 'db.xen',
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

        protected function tearDown():void {
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
            $this->assertNotNull($this->db->get_var("select last_update from dummy where id=%d", 123));
            $this->assertEquals(1, $this->db->get_var("select count(*) from dummy"));
            $this->db->insert("dummy", ['id'=>234, 'last_update'=>date("Y-m-d H:i:s")]);
            $this->assertEquals(2, $this->db->get_var("select count(*) from dummy"));
        }
    }
