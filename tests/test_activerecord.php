<?php
include_once dirname(__DIR__).'/stub.php';

$db=Clue\Database::create([
    'type'=>'mysql',
    'host'=>'localhost',
    'username'=>'root',
    'password'=>'',
    'db'=>'mysql'
]);

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
AR::use_database($db);

// 正确添加
$a=new AR(['name'=>'a', 'value'=>'new a']);
$a->save();
assert(1==AR::count());
assert(1==AR::count_by_name('a'));

// 各种查询方式
$a=AR::find("select * from ar_test", 'one');
$a1=AR::find_one("select * from ar_test");
$a2=AR::find_one(['name'=>'a']);
$aa=AR::find("select * from ar_test");
assert($a->id==$a1->id);
assert($a->id==$a2->id);
assert($a->id==$aa[0]->id);

// 修改
$a=AR::find_one_by_name('a');
$a->value='changed';
$a->save();

assert(1==AR::count_by_value('changed'));

// 删除
$a->destroy();
assert(0==AR::count());

// 删除测试数据库
$db->exec("drop database clue_test");
