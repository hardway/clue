<?php
    define('TEST_APP_ROOT', '/tmp/testapp');
    class Test_Router extends PHPUnit_Framework_TestCase{
        public $app;

        protected function setUp(): void{
            @$_GET=[];
            if(!defined('Clue\\APP_BASE')) define('Clue\\APP_BASE', '/');
            if(!defined('Clue\\APP_URL')) define('Clue\\APP_URL', 'http://localhost');
            $this->app=new Clue\Application(['config'=>null]);

            $this->_mock_controller("index");
            Clue\add_site_path(TEST_APP_ROOT);
        }

        protected function tearDown(): void{
            Clue\Tool::remove_directory(TEST_APP_ROOT);
        }

        protected function _mock_controller($controller){
            $path=TEST_APP_ROOT.'/source/control/'.$controller.'.php';
            @mkdir(dirname($path), 0775, true);
            file_put_contents($path, "<?php namespace Clue; class Controller_{$controller} extends \\Clue\\Controller{}");
        }

        protected function _remove_controller($controller){
            $path=TEST_APP_ROOT.'/source/control/'.$controller.'.php';
            unlink($path);
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

            $m=$rt->resolve("/foo/bar.css");
            $this->assertEquals("index", $m['controller']);
            $this->assertEquals("foo", $m['action']);
            $this->assertEquals(["bar.css"], $m['params']);

            $m=$rt->resolve("/foo/bar/all.js");
            $this->assertEquals("index", $m['controller']);
            $this->assertEquals("foo", $m['action']);
            $this->assertEquals(['bar', 'all.js'], $m['params']);

            $this->_mock_controller("manager/catalog/index");
            $this->_mock_controller("manager/catalog");
            $this->_mock_controller("manager");

            $m=$rt->resolve("/manager/catalog?cid=100");
            $this->assertEquals("manager/catalog/index", $m['controller']);
            $this->assertEquals("index", $m['action']);
            $this->assertEquals(['cid'=>100], $m['params']);

            $this->_remove_controller("manager/catalog/index");

            $m=$rt->resolve("/manager/catalog?cid=100");
            $this->assertEquals("manager/catalog", $m['controller']);
            $this->assertEquals("index", $m['action']);
            $this->assertEquals(['cid'=>100], $m['params']);

            $this->_remove_controller("manager/catalog");

            $m=$rt->resolve("/manager/catalog?cid=100");
            $this->assertEquals("manager", $m['controller']);
            $this->assertEquals("catalog", $m['action']);
            $this->assertEquals(['cid'=>100], $m['params']);

            $this->_remove_controller("manager");

            $m=$rt->resolve("/manager/catalog?cid=100");
            $this->assertEquals("index", $m['controller']);
            $this->assertEquals("manager", $m['action']);
            $this->assertEquals(['catalog', 'cid'=>100], $m['params']);
        }

        function test_connect(){
            $rt=new Clue\Router($this->app);
            $rt->connect('/user/:id', function($id){ return "user_$id"; });

            $m=$rt->resolve('/user/42');
            $this->assertNotNull($m['handler']);
            $this->assertEquals('42', $m['params']['id']);
        }

        function test_connect_with_verb(){
            $rt=new Clue\Router($this->app);

            $handler=function(){ return 'GET'; };
            $rt->connect('/data/:action', $handler, 'GET');

            $m=$rt->resolve('/data/save');
            $this->assertNotNull($m['handler']);
            $this->assertEquals('save', $m['params']['action']);
        }

        function test_reform(){
            $rt=new Clue\Router($this->app);

            $url=$rt->reform('index', 'index');
            $this->assertNotEmpty($url);

            $url=$rt->reform('user', 'profile', ['id'=>5]);
            $this->assertContains('user', $url);
            $this->assertContains('profile', $url);

            $url=$rt->reform('blog', 'view', ['hello', 'world']);
            $this->assertContains('hello', $url);
            $this->assertContains('world', $url);
        }

        function test_resolve_params(){
            $rt=new Clue\Router($this->app);

            $fn=function($name, $age = 18){ return "$name:$age"; };
            $args=$rt->resolve_params($fn, ['name'=>'Alice', 'age'=>25]);
            $this->assertEquals('Alice', $args[0]);
            $this->assertEquals(25, $args[1]);

            // 默认值
            $args=$rt->resolve_params($fn, ['name'=>'Bob']);
            $this->assertEquals('Bob', $args[0]);
            $this->assertEquals(18, $args[1]);
        }

        function test_handle(){
            $rt=new Clue\Router($this->app);

            $result=$rt->handle(function($a, $b){ return $a + $b; }, ['a'=>3, 'b'=>4]);
            $this->assertEquals(7, $result);
        }

        function test_resolve_root(){
            $rt=new Clue\Router($this->app);
            $m=$rt->resolve('/');
            $this->assertEquals('index', $m['controller']);
            $this->assertEquals('index', $m['action']);
        }

        function test_resolve_empty(){
            $rt=new Clue\Router($this->app);
            $m=$rt->resolve('');
            $this->assertEquals('index', $m['controller']);
        }
    }
