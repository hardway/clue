<?php
	define('TEST_APP_ROOT', '/tmp/testapp');

	require_once dirname(__DIR__).'/stub.php';

	class Test_Router extends PHPUnit_Framework_TestCase{
		protected $backupGlobals = FALSE;
		protected $backupGlobalsBlacklist = array('mysql');

		protected function setUp(){
			// 清空环境变量，避免多个TestCase间相互干扰
			@$_GET=[];
			$this->app=new Clue\Application(['config'=>null]);

			// 创建空的测试应用
			$this->_mock_controller("index");

			Clue\add_site_path(TEST_APP_ROOT);
		}

		protected function tearDown(){
			Clue\Tool::remove_directory(TEST_APP_ROOT);
		}

		protected function _mock_controller($controller){
			$path=TEST_APP_ROOT.'/source/control/'.$controller.'.php';
			@mkdir(dirname($path), 0775, true);
			file_put_contents($path, "DUMMY");
		}

		protected function _remove_controller($controller){
			$path=TEST_APP_ROOT.'/source/control/'.$controller.'.php';
			unlink($path);
		}

		function test_alias(){
			$this->assertTrue(is_dir(TEST_APP_ROOT));

			$rt=new Clue\Router($this->app);
			$rt->alias('/.+-p-665$/', '/banner');

			$m=$rt->resolve("/colorbanner-p-665?width=24&height=12");
			$this->assertEquals('index', $m['controller']);
			$this->assertEquals('banner', $m['action']);
			$this->assertEquals('24', $m['params']['width']);
			$this->assertEquals('12', $m['params']['height']);
		}

		function test_basic(){
			$this->assertTrue(is_dir(TEST_APP_ROOT));

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
	}
?>
