<?php
// 结果集 mode（OBJECT / ARRAY_A / ARRAY_N）行为测试

class Test_Database_Modes extends PHPUnit_Framework_TestCase{
    protected static $db;

    static function setUpBeforeClass(): void{
        self::$db=new \Clue\Database\Sqlite(['db'=>':memory:']);
        self::$db->exec("CREATE TABLE modes_t (id INTEGER PRIMARY KEY, name TEXT, num INTEGER)");
        self::$db->exec("INSERT INTO modes_t (id, name, num) VALUES (1, 'one', 10), (2, 'two', 20)");
    }

    static function tearDownAfterClass(): void{
        if(self::$db) self::$db->exec("DROP TABLE IF EXISTS modes_t");
    }

    function test_get_row_object_mode(){
        $r=self::$db->get_row("SELECT id, name FROM modes_t WHERE id=1", OBJECT);
        $this->assertInstanceOf('stdClass', $r);
        $this->assertEquals('one', $r->name);
    }

    function test_get_row_assoc_mode(){
        $r=self::$db->get_row("SELECT id, name FROM modes_t WHERE id=1", ARRAY_A);
        $this->assertEquals(['id'=>1, 'name'=>'one'], $r);
    }

    function test_get_row_numeric_mode(){
        $r=self::$db->get_row("SELECT id, name FROM modes_t WHERE id=2", ARRAY_N);
        $this->assertEquals([2, 'two'], $r);
    }

    function test_get_row_missing_returns_null(){
        $this->assertNull(self::$db->get_row("SELECT id FROM modes_t WHERE id=999", ARRAY_A));
    }

    function test_get_results_assoc_mode(){
        $rs=self::$db->get_results("SELECT id, name FROM modes_t ORDER BY id", ARRAY_A);
        $this->assertEquals([['id'=>1, 'name'=>'one'], ['id'=>2, 'name'=>'two']], $rs);
    }

    function test_get_results_object_mode(){
        $rs=self::$db->get_results("SELECT id, name FROM modes_t ORDER BY id", OBJECT);
        $this->assertEquals(2, count($rs));
        $this->assertInstanceOf('stdClass', $rs[0]);
        $this->assertEquals('two', $rs[1]->name);
    }

    function test_get_results_numeric_mode(){
        $rs=self::$db->get_results("SELECT id, name FROM modes_t ORDER BY id", ARRAY_N);
        $this->assertEquals([[1, 'one'], [2, 'two']], $rs);
    }

    function test_get_results_empty_returns_empty_array(){
        $this->assertEquals([], self::$db->get_results("SELECT id FROM modes_t WHERE id=999", ARRAY_A));
    }

    // 新增的公共归一化函数：非法 mode 回落到默认值
    // 注意：(OBJECT) 会被当作类型转换，因此先赋值给变量
    function test_normalize_mode_keeps_valid_modes(){
        $obj=OBJECT; $assoc=ARRAY_A; $num=ARRAY_N;
        $this->assertEquals($obj, \Clue\Database::normalize_mode($obj));
        $this->assertEquals($assoc, \Clue\Database::normalize_mode($assoc));
        $this->assertEquals($num, \Clue\Database::normalize_mode($num));
    }

    function test_normalize_mode_falls_back_to_default(){
        $obj=OBJECT; $assoc=ARRAY_A;
        $this->assertEquals($obj, \Clue\Database::normalize_mode('bogus'));
        $this->assertEquals($assoc, \Clue\Database::normalize_mode('bogus', $assoc));
        $this->assertEquals($assoc, \Clue\Database::normalize_mode(null, $assoc));
    }
}
