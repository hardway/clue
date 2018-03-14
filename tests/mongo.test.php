<?php
    require_once dirname(__DIR__).'/stub.php';

    class Test_MongoDB extends PHPUnit_Framework_TestCase{
        function setUp(){
            $this->mongo=Clue\Database::create(['type'=>'mongodb', 'host'=>'db.dev', 'db'=>'test']);
        }

        function tearDown(){
            $this->mongo->delete('foo');
            $this->mongo=null;
        }

        function test_dbinfo(){
            $r=$this->mongo->exec(['hostInfo'=>1]);
            echo json_encode($r, JSON_PRETTY_PRINT)."\n";
        }

        function test_crud(){
            $db=$this->mongo;

            // Delete
            $db->delete('foo', [/*all*/]);
            $this->assertEquals(0, $db->count('foo'));

            // Insert
            $db->insert("foo", ['id'=>1, 'val'=>'A', 'nest'=>['val'=>'a']]);
            $db->insert('foo', ['id'=>2, 'val'=>'B', 'nest'=>['val'=>'b']]);
            $db->insert('foo', ['id'=>3, 'val'=>'C', 'nest'=>['val'=>'c']]);
            $this->assertEquals(3, $db->count('foo'));
            $this->assertEquals(2, $db->count('foo', ['id'=>['$gt'=>1]]));

            // Retrieve
            $rs=$db->get_results('foo');
            $this->assertEquals(3, count($rs));

            $r=$db->get_row('foo', ['id'=>1]);
            $this->assertEquals('A', $r['val']);

            $r=$db->get_row('foo', ['val'=>'A']);
            $this->assertEquals(1, $r['id']);

            $v=$db->get_var('foo.val', ['id'=>2]);
            $this->assertEquals('B', $v);

            $v=$db->get_row('foo', ['val'=>'B'], ['nest'=>1]);
            $this->assertEquals(['val'=>'b'], $v['nest']);
            $this->assertNotContains('val', $v);

            $v=$db->get_col("foo.nest.val");
            $this->assertEquals(['a', 'b', 'c'], $v);

            // Replace
            $db->replace("foo", ['id'=>1, 'val'=>'AA', 'nest'=>['val'=>'aa']]);
            $this->assertEquals(3, $db->count('foo'));
            $r=$db->get_row('foo', ['id'=>1]);
            $this->assertEquals('AA', $r['val']);

            // Update
            $db->update('foo', ['batch'=>1], ['id'=>['$gte'=>2]]);
            $this->assertEquals(2, $db->count('foo', ['batch'=>1]));

            // Delete
            $db->delete('foo', ['id'=>3]);
            $this->assertEquals(2, $db->count('foo'));
            $r=$db->get_row('foo.val', ['id'=>2]);
            $this->assertNull($r);

        }
    }
