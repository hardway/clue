<?php  
    require_once 'simpletest/autorun.php';
    require_once 'clue/registry.php';

    class Test_Clue_Registry extends UnitTestCase{
        function setUp(){
        }
        function tearDown(){
        }
        
        function test_get_with_path(){
            $r=new Clue_Registry(array('foo'=>array('bar'=>1)));
            $this->assertEqual(1, $r->get('foo.bar'));
        }
        
        function test_set_with_path(){
            $r=new Clue_Registry(array('foo'=>array('bar'=>1)));
            $r->set('foo.bar', 2);
            $this->assertEqual(2, $r->get('foo.bar'));
        }
        
        function test_get_array(){
            $r=new Clue_Registry(array('foo'=>array('bar'=>1)));
            $foo=$r->get('foo');
            $this->assertEqual($foo, array('bar'=>1));
        }
        
        function test_set_unexisted_path(){
            $r=new Clue_Registry();
            $r->set('foo.bar', 1);
            $this->assertEqual($r->get('foo.bar'), 1);
        }
        
        function test_set_array(){
            $r=new Clue_Registry();
            $r->set('foo', array('bar'=>1));
            $this->assertEqual($r->get('foo.bar'), 1);
        }
        
        function test_get_whole_store(){
            $r=new Clue_Registry(array('foo'=>array('bar'=>1)));
            $this->assertEqual($r->get(""), array('foo'=>array('bar'=>1)));
        }

        function test_magic_get(){
            $r=new Clue_Registry(array('foo'=>array('bar'=>1)));
            
            $foo=$r->foo;
            $this->assertEqual(1, $foo->bar);
            $this->assertEqual(1, $r->foo->bar);
        }
    }
?>
