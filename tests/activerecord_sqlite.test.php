<?php
// ActiveRecord 行为测试（使用 sqlite 内存库，不依赖 MySQL）
// 用于约束 _snap_shot / after_retrieve / 实例化 等行为，供重构时回归

class Test_AR_Sqlite_Item extends \Clue\ActiveRecord{
    protected static $_model=[
        'table'=>'ar_sqlite_item',
        'pkey'=>'id',
        'columns'=>[
            'id'=>['name'=>'id'],
            'name'=>['name'=>'name'],
            'num'=>['name'=>'num'],
        ]
    ];

    // 显式声明列属性，避免 PHP 8.2 动态属性弃用警告
    public $id;
    public $name;
    public $num;

    static $retrieved=[];

    function after_retrieve(){
        self::$retrieved[]=$this->name;
    }
}

class Test_ActiveRecord_Sqlite extends PHPUnit_Framework_TestCase{
    protected static $db;

    static function setUpBeforeClass(): void{
        self::$db=new \Clue\Database\Sqlite(['db'=>':memory:']);
        self::$db->exec("
            CREATE TABLE ar_sqlite_item (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                num INTEGER
            )
        ");
        Test_AR_Sqlite_Item::use_database(self::$db);
    }

    static function tearDownAfterClass(): void{
        if(self::$db) self::$db->exec("DROP TABLE IF EXISTS ar_sqlite_item");
    }

    protected function setUp(): void{
        self::$db->exec("DELETE FROM ar_sqlite_item");
        Test_AR_Sqlite_Item::$retrieved=[];
    }

    protected function make($name, $num){
        $o=new Test_AR_Sqlite_Item(['name'=>$name, 'num'=>$num]);
        $o->save();
        return $o;
    }

    function test_save_insert_assigns_id(){
        $o=$this->make('alpha', 1);
        $this->assertNotNull($o->id);
        $this->assertTrue((int)$o->id > 0);
    }

    function test_get_hydrates_fields(){
        $o=$this->make('beta', 2);
        $r=Test_AR_Sqlite_Item::get($o->id);
        $this->assertInstanceOf('Test_AR_Sqlite_Item', $r);
        $this->assertEquals('beta', $r->name);
        $this->assertEquals(2, (int)$r->num);
    }

    function test_get_missing_returns_false(){
        $this->assertFalse(Test_AR_Sqlite_Item::get(999999));
    }

    function test_get_calls_after_retrieve_once(){
        $o=$this->make('gamma', 3);
        Test_AR_Sqlite_Item::get($o->id);
        $this->assertEquals(['gamma'], Test_AR_Sqlite_Item::$retrieved);
    }

    function test_find_all_hydrates_every_row(){
        $this->make('a', 1);
        $this->make('b', 2);
        $this->make('c', 2);

        $rows=Test_AR_Sqlite_Item::find_all(['num'=>2]);
        $this->assertEquals(2, count($rows));
        foreach($rows as $r) $this->assertInstanceOf('Test_AR_Sqlite_Item', $r);
        $this->assertEquals(['b', 'c'], Test_AR_Sqlite_Item::$retrieved);
    }

    function test_find_one_returns_single_object(){
        $this->make('solo', 7);
        $r=Test_AR_Sqlite_Item::find_one(['num'=>7]);
        $this->assertInstanceOf('Test_AR_Sqlite_Item', $r);
        $this->assertEquals('solo', $r->name);
        $this->assertEquals(['solo'], Test_AR_Sqlite_Item::$retrieved);
    }

    function test_find_one_missing_returns_false(){
        $this->assertFalse(Test_AR_Sqlite_Item::find_one(['num'=>-1]));
    }

    function test_iterate_yields_hydrated_objects(){
        $this->make('x', 5);
        $this->make('y', 5);

        $names=[];
        foreach(Test_AR_Sqlite_Item::iterate(['num'=>5]) as $r){
            $this->assertInstanceOf('Test_AR_Sqlite_Item', $r);
            $names[]=$r->name;
        }
        $this->assertEquals(['x', 'y'], $names);
        $this->assertEquals(['x', 'y'], Test_AR_Sqlite_Item::$retrieved);
    }

    function test_update_persists_change(){
        $o=$this->make('before', 1);
        $o->name='after';
        $o->save();

        $r=Test_AR_Sqlite_Item::get($o->id);
        $this->assertEquals('after', $r->name);
    }

    function test_destroy_removes_row(){
        $o=$this->make('gone', 1);
        $id=$o->id;
        $o->destroy();

        $this->assertFalse(Test_AR_Sqlite_Item::get($id));
    }

    function test_delete_ids_counts_only_existing(){
        $a=$this->make('d1', 1);
        $b=$this->make('d2', 1);

        $cnt=Test_AR_Sqlite_Item::delete([$a->id, $b->id, 999999]);
        $this->assertEquals(2, $cnt);
        $this->assertFalse(Test_AR_Sqlite_Item::get($a->id));
        $this->assertFalse(Test_AR_Sqlite_Item::get($b->id));
    }

    function test_delete_single_id(){
        $a=$this->make('single', 1);
        $this->assertEquals(1, Test_AR_Sqlite_Item::delete($a->id));
    }
}
