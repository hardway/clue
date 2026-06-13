<?php
    class Test_Mongo extends PHPUnit_Framework_TestCase{
        protected $mongo;

        static function setUpBeforeClass(): void{
            if(!extension_loaded('mongodb')){
                throw new \Exception('extension mongodb is required for MongoDB tests');
            }
        }

        protected function setUp(): void{
            // 连接本机 Docker MongoDB 4.4
            $this->mongo = \Clue\Database::create([
                'type' => 'mongodb',
                'host' => '127.0.0.1',
                'db'   => 'test'
            ]);

            // 清空测试用集合
            $this->mongo->delete('foo');
        }

        protected function tearDown(): void{
            $this->mongo = null;
        }

        function test_connection(){
            $r = $this->mongo->exec(['ping' => 1]);
            $this->assertNotNull($r);
            $this->assertEquals(1.0, $r->ok);
        }

        function test_crud(){
            $db = $this->mongo;

            // Insert
            $id1 = $db->insert('foo', ['id' => 1, 'val' => 'A', 'nest' => ['val' => 'a']]);
            $id2 = $db->insert('foo', ['id' => 2, 'val' => 'B', 'nest' => ['val' => 'b']]);
            $id3 = $db->insert('foo', ['id' => 3, 'val' => 'C', 'nest' => ['val' => 'c']]);
            $this->assertNotNull($id1);
            $this->assertEquals(3, $db->count('foo'));
            $this->assertEquals(2, $db->count('foo', ['id' => ['$gt' => 1]]));

            // get_results
            $rs = $db->get_results('foo');
            $this->assertEquals(3, count($rs));
            $this->assertEquals('A', $rs[0]['val']);  // 默认无排序，只要查到即可

            // get_row
            $r = $db->get_row('foo', ['id' => 1]);
            $this->assertEquals('A', $r['val']);
            $this->assertEquals(1, $r['id']);

            $r = $db->get_row('foo', ['val' => 'B'], ['nest' => 1]);
            $this->assertEquals(['val' => 'b'], $r['nest']);

            // get_var
            $v = $db->get_var('foo.val', ['id' => 2]);
            $this->assertEquals('B', $v);

            // get_col
            $v = $db->get_col('foo.nest.val');
            $this->assertEquals(3, count($v));

            // Replace
            $db->replace('foo', ['id' => 1, 'val' => 'AA', 'nest' => ['val' => 'aa']]);
            $this->assertEquals(3, $db->count('foo'));
            $r = $db->get_row('foo', ['id' => 1]);
            $this->assertEquals('AA', $r['val']);

            // Update
            $db->update('foo', ['batch' => 1], ['id' => ['$gte' => 2]]);
            $this->assertEquals(2, $db->count('foo', ['batch' => 1]));

            // Delete
            $db->delete('foo', ['id' => 3]);
            $this->assertEquals(2, $db->count('foo'));
            $r = $db->get_row('foo', ['id' => 2]);
            $this->assertNotNull($r);
        }

        function test_distinct(){
            $db = $this->mongo;
            $db->insert('foo', ['id' => 1, 'val' => 'A']);
            $db->insert('foo', ['id' => 2, 'val' => 'B']);
            $db->insert('foo', ['id' => 3, 'val' => 'A']);

            $vals = $db->distinct('foo', 'val');
            $this->assertEquals(2, count($vals));
            $this->assertContains('A', $vals);
            $this->assertContains('B', $vals);
        }

        function test_group_count(){
            $db = $this->mongo;
            $db->insert('foo', ['id' => 1, 'type' => 'x']);
            $db->insert('foo', ['id' => 2, 'type' => 'y']);
            $db->insert('foo', ['id' => 3, 'type' => 'x']);

            $cnt = $db->group_count('foo', 'type');
            $this->assertEquals(2, $cnt['x']);
            $this->assertEquals(1, $cnt['y']);
        }

        function test_get_var_no_dot(){
            // 不带字段路径，直接返回整行
            $db = $this->mongo;
            $db->insert('foo', ['id' => 1, 'val' => 'A']);
            $r = $db->get_var('foo', ['id' => 1]);
            $this->assertNotNull($r);
            $this->assertEquals('A', $r['val']);
        }

        function test_iterate_results(){
            $db = $this->mongo;
            $db->insert('foo', ['id' => 1, 'val' => 'A']);
            $db->insert('foo', ['id' => 2, 'val' => 'B']);

            $rows = [];
            foreach($db->iterate_results('foo', [], ['val' => 1, '_id' => 0]) as $r){
                $rows[] = $r;
            }
            $this->assertEquals(2, count($rows));
            $this->assertEquals('A', $rows[0]['val']);
        }
    }
