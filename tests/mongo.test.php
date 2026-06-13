<?php
    // MongoRecord 测试用子类
    class TestMongoFoo extends \Clue\MongoRecord{
        protected static $_model = [
            'table' => 'foo',
            'pkey' => 'id',
            'columns' => [
                'id' => ['name' => 'id', 'type' => 'number'],
                'name' => ['name' => 'name', 'type' => 'string'],
                'profile' => ['name' => 'profile', 'type' => 'string'],
                'tags' => ['name' => 'tags', 'type' => 'string'],
            ]
        ];
    }

    class Test_Mongo extends PHPUnit_Framework_TestCase{
        protected $mongo;

        static function setUpBeforeClass(): void{
            if(!extension_loaded('mongodb')){
                throw new \Exception('extension mongodb is required for MongoDB tests');
            }
            \Clue\MongoRecord::use_database(null);  // 重置，让测试用 $this->mongo
        }

        protected function setUp(): void{
            $this->mongo = \Clue\Database::create([
                'type' => 'mongodb',
                'host' => '127.0.0.1',
                'db'   => 'test'
            ]);

            // 设置动态数据库实例（替换 $GLOBALS['db']）
            $GLOBALS['db'] = $this->mongo;

            $this->mongo->delete('foo');
        }

        protected function tearDown(): void{
            $this->mongo = null;
            unset($GLOBALS['db']);
        }

        // ==================== 数据库层测试 ====================

        function test_connection(){
            $r = $this->mongo->exec(['ping' => 1]);
            $this->assertNotNull($r);
            $this->assertEquals(1.0, $r->ok);
        }

        function test_crud(){
            $db = $this->mongo;

            $id1 = $db->insert('foo', ['id' => 1, 'val' => 'A', 'nest' => ['val' => 'a']]);
            $id2 = $db->insert('foo', ['id' => 2, 'val' => 'B', 'nest' => ['val' => 'b']]);
            $id3 = $db->insert('foo', ['id' => 3, 'val' => 'C', 'nest' => ['val' => 'c']]);
            $this->assertNotNull($id1);
            $this->assertEquals(3, $db->count('foo'));
            $this->assertEquals(2, $db->count('foo', ['id' => ['$gt' => 1]]));

            $rs = $db->get_results('foo');
            $this->assertEquals(3, count($rs));

            $r = $db->get_row('foo', ['id' => 1]);
            $this->assertEquals('A', $r['val']);

            $r = $db->get_row('foo', ['val' => 'B'], ['nest' => 1]);
            $this->assertEquals(['val' => 'b'], $r['nest']);

            $v = $db->get_var('foo.val', ['id' => 2]);
            $this->assertEquals('B', $v);

            $db->replace('foo', ['id' => 1, 'val' => 'AA', 'nest' => ['val' => 'aa']]);
            $this->assertEquals(3, $db->count('foo'));

            $db->update('foo', ['batch' => 1], ['id' => ['$gte' => 2]]);
            $this->assertEquals(2, $db->count('foo', ['batch' => 1]));

            $db->delete('foo', ['id' => 3]);
            $this->assertEquals(2, $db->count('foo'));
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

        function test_list_collections(){
            $cols = $this->mongo->list_collections();
            $this->assertContains('foo', $cols);
        }

        function test_stat_collection(){
            $this->mongo->insert('foo', ['id' => 1]);
            $stat = $this->mongo->stat_collection('foo');
            $this->assertNotNull($stat);
            $this->assertTrue($stat->count > 0);
        }

        // ==================== MongoRecord 测试 ====================

        function test_mongo_record_crud(){
            \Clue\MongoRecord::use_database($this->mongo);

            // save
            $r = new TestMongoFoo(['id' => 10, 'name' => 'hello', 'profile' => ['bar' => 1, 'x' => 'd'], 'tags' => ['a', 'b']]);
            $ok = $r->save();
            $this->assertTrue($ok);
            $this->assertEquals(10, $r->id);

            // get
            $r2 = TestMongoFoo::get(10);
            $this->assertNotNull($r2);
            $this->assertEquals('hello', $r2->name);
            $this->assertEquals(1, $r2->profile['bar']);
            $this->assertEquals('d', $r2->profile['x']);
            $this->assertContains('a', $r2->tags);

            // find
            $all = TestMongoFoo::find(['id' => 10]);
            $this->assertEquals(1, count($all));
            $this->assertEquals('hello', $all[0]->name);

            // iterate
            $cnt = 0;
            foreach(TestMongoFoo::iterate(['id' => 10]) as $obj){
                $cnt++;
                $this->assertEquals('hello', $obj->name);
            }
            $this->assertEquals(1, $cnt);

            // count
            $this->assertEquals(1, TestMongoFoo::count(['id' => 10]));
            $this->assertEquals(0, TestMongoFoo::count(['id' => 999]));
        }

        function test_mongo_record_nested_object(){
            \Clue\MongoRecord::use_database($this->mongo);

            // 复杂 JSON 结构：{foo: {bar:1, x:"d"}, bar:2}
            $r = new TestMongoFoo([
                'id' => 20,
                'name' => 'nested',
                'profile' => ['bar' => 1, 'x' => 'd'],
                'tags' => ['a', 'b', 'c']
            ]);
            $r->save();

            // 取出验证
            $r2 = TestMongoFoo::get(20);
            $this->assertEquals('nested', $r2->name);
            $this->assertEquals(1, $r2->profile['bar']);
            $this->assertEquals('d', $r2->profile['x']);
            $this->assertEquals(3, count($r2->tags));
            $this->assertContains('a', $r2->tags);
            $this->assertContains('b', $r2->tags);
            $this->assertContains('c', $r2->tags);

            // 验证脏数据跟踪
            $r2->name = 'updated';
            $r2->save();

            $r3 = TestMongoFoo::get(20);
            $this->assertEquals('updated', $r3->name);
        }

        function test_mongo_record_find_by(){
            \Clue\MongoRecord::use_database($this->mongo);

            $r = new TestMongoFoo(['id' => 30, 'name' => 'found_me', 'profile' => ['val' => 42]]);
            $r->save();

            $results = TestMongoFoo::find_by_name('found_me');
            $this->assertEquals(1, count($results));
            $this->assertEquals(42, $results[0]->profile['val']);
        }
    }
