<?php
include_once dirname(__DIR__).'/stub.php';

$db=Clue\Database::create([
    'type'=>'mysql',
    'host'=>'localhost',
    'username'=>'root',
    'password'=>'',
    'db'=>'mysql'
]);

class AR extends Clue\ActiveRecord{
    protected static $_model=[
        'table'=>'ar_test',
        'pkey'=>'id',
        'columns'=>[
            'id'=>['name'=>'pid'],
            'name'=>['name'=>'test_name'],
            'value'=>['name'=>'test_value'],
        ]
    ];
}

class Test_ActiveRecord extends PHPUnit_Framework_TestCase{
    protected $backupGlobals = FALSE;
    protected $backupGlobalsBlacklist = array('db');

    static function setUpBeforeClass(){
        global $db;

        // 创建测试数据库
        $db->exec("drop database if exists clue_test");
        $db->exec("create database clue_test");
        $db->exec("use clue_test");
        $db->exec("
            create table ar_test(
                pid int not null primary key auto_increment,
                test_name varchar(64),
                test_value varchar(64)
            );
        ");
    }

    static function tearDownAfterClass(){
        global $db;

        // 删除测试数据库
        $db->exec("drop database clue_test");
    }

    function test_crud(){
        global $db;
        AR::use_database($db);

        // 正确添加
        $a=new AR(['name'=>'a', 'value'=>'new a']);
        $a->save();
        $this->assertEquals(1, AR::count());
        $this->assertEquals(1, AR::count_by_name('a'));

        // 各种查询方式
        $a=AR::find("select * from ar_test", 'one');
        $a1=AR::find_one("select * from ar_test");
        $a2=AR::find_one(['name'=>'a']);
        $aa=AR::find("select * from ar_test");
        $this->assertEquals($a->id, $a1->id);
        $this->assertEquals($a->id, $a2->id);
        $this->assertEquals($a->id, $aa[0]->id);

        // 修改
        $a=AR::find_one_by_name('a');
        $a->value='changed';
        $a->save();

        $this->assertEquals(1, AR::count_by_value('changed'));

        // 删除
        $a->destroy();
        $this->assertEquals(0, AR::count());
    }
}
