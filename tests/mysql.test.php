<?php
    require_once dirname(__DIR__).'/stub.php';

    class Test_Mysql extends PHPUnit_Framework_TestCase{
        function setUp(){
            $this->mysql=Clue\Database::create(['type'=>'mysql', 'host'=>'127.0.0.1', 'username'=>'root', 'password'=>'', 'db'=>'test']);
            $this->mysql->exec("truncate table foo");

            $this->watch=Clue\Database::create(['type'=>'mysql', 'host'=>'127.0.0.1', 'username'=>'root', 'password'=>'', 'db'=>'test']);
        }

        function tearDown(){
            $this->mysql->close();
        }

        function cnt($table){
            return intval($this->mysql->get_var("select count(*) from `$table`"));
        }

        function watch($table){
            return intval($this->watch->get_var("select count(*) from `$table`"));
        }

        function test_cascade_transactions(){
            $this->mysql->begin();
            $this->mysql->insert('foo', ['id'=>1, 'val'=>'A']);
            $this->mysql->begin();
            $this->mysql->insert('foo', ['id'=>2, 'val'=>'B']);

            $this->assertEquals(2, $this->cnt('foo'));
            $this->assertEquals(0, $this->watch('foo'));

            $this->mysql->rollback();
            $this->assertEquals(1, $this->cnt('foo'));

            $this->mysql->rollback_all();
            $this->assertEquals(0, $this->cnt('foo'));
            $this->assertEquals(0, $this->watch('foo'));

            $this->mysql->begin();
            $this->mysql->insert('foo', ['id'=>1, 'val'=>'A']);
            $this->mysql->begin();
            $this->mysql->insert('foo', ['id'=>2, 'val'=>'B']);

            $this->mysql->commit_all();
            $this->assertEquals(2, $this->cnt('foo'));
            $this->assertEquals(2, $this->watch('foo'));
        }

        function test_cascade_transactions_with_name(){
            $this->mysql->begin();
            $this->mysql->insert('foo', ['id'=>1, 'val'=>'A']);
            $this->mysql->begin('a');
            $this->mysql->insert('foo', ['id'=>2, 'val'=>'B']);
            $this->mysql->begin('b');
            $this->mysql->insert('foo', ['id'=>3, 'val'=>'C']);

            $this->assertEquals(3, $this->cnt('foo'));

            $this->mysql->rollback('a');
            $this->assertEquals(1, $this->cnt('foo'));

            $this->mysql->rollback_all();
            $this->assertEquals(0, $this->cnt('foo'));

            $this->mysql->begin();
            $this->mysql->insert('foo', ['id'=>1, 'val'=>'A']);
            $this->mysql->begin('a');
            $this->mysql->insert('foo', ['id'=>2, 'val'=>'B']);
            $this->mysql->begin('b');
            $this->mysql->insert('foo', ['id'=>3, 'val'=>'C']);

            $this->assertEquals(3, $this->cnt('foo'));

            $this->mysql->rollback('b');
            $this->assertEquals(2, $this->cnt('foo'));
            $this->mysql->rollback();
            $this->assertEquals(1, $this->cnt('foo'));

            $this->mysql->commit();
            $this->assertEquals(1, $this->cnt('foo'));
        }
    }
?>
