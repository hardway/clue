<?php
include dirname(__DIR__).'/Collection.php';
use Clue\Collection;

class Test_ActiveRecord extends PHPUnit_Framework_TestCase{
    function test_data_get_set(){
        $arr=['foo'=>['bar'=>1]];

        $this->assertEquals(['bar'=>1], Clue\data_get($arr, 'foo'));
        $this->assertEquals(1, Clue\data_get($arr, 'foo.bar'));

        Clue\data_set($arr, 'foo1.bar1', 1);
        Clue\data_set($arr, 'foo2', ['bar2'=>2]);

        $this->assertEquals(1, Clue\data_get($arr, 'foo1.bar1'));
        $this->assertEquals(2, Clue\data_get($arr, 'foo2.bar2'));
    }

    function test_make(){
        $arr=['a'=>1, 'b'=>2];

        $this->assertEquals($arr, Collection::make($arr)->all());
    }

    function test_avg(){
        $c=new Collection([1, 2, 3]);

        $this->assertEquals(2, $c->avg());
    }

    function test_where_in(){
        $c=new Collection([
            ['a'=>1, 'b'=>2],
            ['a'=>2, 'b'=>3],
            ['a'=>3, 'b'=>4],
        ]);

        $r=$c->where_in('a', [1,3]);

        $this->assertInstanceOf('Clue\Collection', $r);
        $this->assertEquals(2, $r->count());

        $r=$r->values();
        $this->assertEquals(['a'=>1, 'b'=>2], $r[0]);
        $this->assertEquals(['a'=>3, 'b'=>4], $r[1]);
    }

    function test_contains(){
        $c=new Collection([1,2,3,4,5]);

        $this->assertTrue($c->contains(4));
        $this->assertFalse($c->contains(6));
    }

    function test_except(){
        $c=new Collection(['a'=>1, 'b'=>2]);

        $e=$c->except("a")->all();
        $this->assertEquals(['b'=>2], $e);
    }

    function test_filter(){
        $c=new Collection([1,2,3,1,2,3]);
        $this->assertEquals([1,1], $c->filter(1)->values());
    }

    function test_reject(){
        $c=new Collection([1,2,3,1,2,3]);
        $this->assertEquals([2,3,2,3], $c->reject(1)->values());
    }

    function test_sort(){
        $c=new Collection([1,4,3,2]);
        $this->assertEquals([1,2,3,4], $c->sort(function($v){return $v*2;})->values());
    }

    function test_group(){
        $c=new Collection([
            ['group'=>'A', 'val'=>2],
            ['group'=>'B', 'val'=>3],
            ['group'=>'A', 'val'=>4],
        ]);

        $g=$c->group("group");

        $this->assertEquals(2, $g->count());
        $this->assertEquals([['group'=>'B', 'val'=>3]], $g['B']->values());
    }
}
