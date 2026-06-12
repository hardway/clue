<?php
// Guard: only attempt DB connection when MySQL extension is available
// and this file is loaded in test context
$db = null;
if (extension_loaded('mysqli')) {
    try {
        $db = Clue\Database::create([
            'type' => 'mysql',
            'host' => 'localhost',
            'username' => 'root',
            'password' => '',
            'db' => 'mysql'
        ]);
    } catch (\Throwable $e) {
        // MySQL not available, tests will skip
        $db = null;
    }
}

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
    function test_crud(){
        global $db;

        if (!$db) {
            $this->markTestSkipped('MySQL not available');
            return;
        }

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
