<?php  
    require_once 'simpletest/autorun.php';
    require_once dirname(__DIR__).'/config.php';
    
    class Test_Clue_Config extends UnitTestCase{
        function setUp(){
            file_put_contents("data/test.config.php", '
<?php
$cfg=array(
    "name"=>"a sample config",
    "admin"=>array(
        "name"=>"Administrator",
        "email"=>"admin@localhost"
    )
);
return $cfg;
?>
            ');
        }
        
        function tearDown(){
            unlink('data/test.config.php');
        }
        
        function test_load_nonexist_config_file_will_throw_error(){
            $this->expectException();
            $c=new Clue_Config('non-existed-config.php');
        }
        
        function test_load_config_file(){
            $c=new Clue_Config('data/test.config.php');
            $this->assertEqual('a sample config', $c->get('name'));
            $this->assertEqual('Administrator', $c->get('admin.name'));
        }
        
        function test_merge_config(){
            $c=new Clue_Config('data/test.config.php');
            $co=new Clue_Config(array('name'=>'new config', 'debug'=>false));
            $c->merge($co);
            
            $this->assertEqual('new config', $c->get('name'));
            $this->assertEqual('Administrator', $c->get('admin.name'));
            $this->assertEqual('admin@localhost', $c->get('admin.email'));
            $this->assertEqual(false, $c->get('debug'));
        }
    }
?>