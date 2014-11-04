<?php
    require_once dirname(__DIR__).'/stub.php';

    class Test_Router extends PHPUnit_Framework_TestCase{
        protected $backupGlobals = FALSE;
        protected $backupGlobalsBlacklist = array('mysql');

        protected function setUp(){
            $this->app=new Clue\Application(['config'=>null]);
        }

        function test_alias(){
            $rt=new Clue\Router($this->app);
            $rt->alias('/.+-p-665$/', '/banner');

            $m=$rt->resolve("/colorbanner-p-665?width=24&height=12");
            $this->assertEquals('index', $m['controller']);
            $this->assertEquals('banner', $m['action']);
            $this->assertEquals('24', $m['params']['width']);
            $this->assertEquals('12', $m['params']['height']);
        }

        function test_basic(){
            $rt=new Clue\Router($this->app);
            $m=$rt->resolve("/asset/stylesheet.css");
            $this->assertEquals("index", $m['controller']);
            $this->assertEquals("asset", $m['action']);
        }
    }
?>
