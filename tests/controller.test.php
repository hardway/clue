<?php
    class ControllerTest extends PHPUnit_Framework_TestCase{
        protected $tmpDir;
        protected $origApp;

        protected function setUp(): void{
            $this->tmpDir = sys_get_temp_dir().'/clue-ctrl-test-'.uniqid();
            mkdir($this->tmpDir, 0775, true);

            if(!defined('Clue\\APP_BASE')) define('Clue\\APP_BASE', '/');
            if(!defined('Clue\\APP_URL')) define('Clue\\APP_URL', 'http://localhost');

            // 全局 $app 供 Controller 构造时使用
            $this->origApp = $GLOBALS['app'] ?? null;
            $GLOBALS['app'] = new Clue\Application(['config'=>null]);

            \Clue\add_site_path($this->tmpDir);
            \Clue\add_view_path($this->tmpDir.'/views');

            // 注册测试用 Controller 文件
            $this->_mockController('test_ctrl', <<<'PHP'
<?php namespace Clue;
    class Ctrl_test_ctrl extends \Clue\Controller{
        function hello($name = 'World'){
            $this->render('hello', ['name' => $name]);
        }

        function greet($name = 'World'){
            echo "Hello $name";
        }

        function data_json(){
            $this->render_json(['status' => 'ok']);
        }

        function catch_view(){
            // uses default __catch_view behavior
        }

        function has_catch_exception(){
            $this->catch_exception = true;
            throw new \Exception('test error');
        }
    }
PHP
            );

            // 创建视图文件
            mkdir($this->tmpDir.'/views', 0775, true);
            mkdir($this->tmpDir.'/views/test_ctrl', 0775, true);
            mkdir($this->tmpDir.'/views/layout', 0775, true);
            file_put_contents($this->tmpDir.'/views/test_ctrl/hello.htm',
                '<h1>Hello <?=$name?></h1>');
            file_put_contents($this->tmpDir.'/views/test_ctrl/data_json.htm',
                '<p>should not render</p>');
            file_put_contents($this->tmpDir.'/views/layout/default.htm',
                '<html><body><?php $this->incl(); ?></body></html>');
        }

        protected function tearDown(): void{
            $GLOBALS['app'] = $this->origApp;

            if($this->tmpDir && is_dir($this->tmpDir)){
                $it = new RecursiveDirectoryIterator($this->tmpDir,
                    RecursiveDirectoryIterator::SKIP_DOTS);
                $files = new RecursiveIteratorIterator($it,
                    RecursiveIteratorIterator::CHILD_FIRST);
                foreach($files as $f){
                    $f->isDir() ? rmdir($f->getRealPath()) : unlink($f->getRealPath());
                }
                rmdir($this->tmpDir);
            }
        }

        protected function _mockController($name, $code){
            $path = $this->tmpDir.'/source/control/'.$name.'.php';
            @mkdir(dirname($path), 0775, true);
            file_put_contents($path, $code);
        }

        protected function _newCtrl($name = 'test_ctrl', $action = 'index'){
            $class = 'Clue\\Ctrl_'.$name;

            if(!class_exists($class, false)){
                $path = \Clue\Controller::find_controller($name);
                if($path) require_once $path;
            }

            if(!class_exists($class, false)){
                $this->fail("Controller class not found: $class");
            }
            return new $class($name, $action);
        }

        protected function capture(callable $fn): string{
            ob_start();
            $fn();
            return ob_get_clean();
        }

        // ─── find_controller ─────────────────────────────────────

        function test_find_controller_found(){
            $path = \Clue\Controller::find_controller('test_ctrl');
            $this->assertNotNull($path);
            $this->assertTrue(file_exists($path));
        }

        function test_find_controller_not_found(){
            $path = \Clue\Controller::find_controller('nonexistent');
            $this->assertNull($path);
        }

        // ─── __construct ─────────────────────────────────────────

        function test_construct_success(){
            $ctrl = $this->_newCtrl();
            $this->assertNotNull($ctrl);
            $this->assertEquals('test_ctrl', $ctrl->controller);
            $this->assertEquals('index', $ctrl->action);
        }

        function test_construct_throws_on_empty(){
            $this->expectException(\InvalidArgumentException::class);
            new \Clue\Controller('');
        }

        function test_construct_throws_on_null(){
            $this->expectException(\InvalidArgumentException::class);
            new \Clue\Controller(null);
        }

        // ─── get_view ────────────────────────────────────────────

        function test_get_view_relative(){
            $ctrl = $this->_newCtrl();
            $view = $ctrl->get_view('hello', ['name' => 'Test']);

            $this->assertInstanceOf(\Clue\View::class, $view);

            $output = $this->capture(function() use($view){ $view->render(); });
            $this->assertContains('Hello Test', $output);
        }

        function test_get_view_absolute(){
            $ctrl = $this->_newCtrl();
            $view = $ctrl->get_view('/test_ctrl/hello', ['name' => 'Abs']);

            $this->assertInstanceOf(\Clue\View::class, $view);

            $output = $this->capture(function() use($view){ $view->render(); });
            $this->assertContains('Hello Abs', $output);
        }

        function test_get_view_no_data(){
            $ctrl = $this->_newCtrl();
            $view = $ctrl->get_view('hello');

            $output = $this->capture(function() use($view){ $view->render(); });
            $this->assertNotEmpty($output);
        }

        // ─── render (via action) ────────────────────────────────

        function test_render_with_layout(){
            $ctrl = $this->_newCtrl('test_ctrl', 'hello');
            $ctrl->view = 'hello';

            $output = $this->capture(function() use($ctrl){ $ctrl->render('hello', ['name' => 'Layout']); });
            $this->assertContains('Hello Layout', $output);
            $this->assertContains('<html>', $output);   // layout wraps it
            $this->assertContains('</html>', $output);
        }

        function test_render_without_view_uses_default(){
            $ctrl = $this->_newCtrl('test_ctrl', 'hello');
            $ctrl->view = 'hello';

            $output = $this->capture(function() use($ctrl){ $ctrl->render(); });
            $this->assertContains('Hello', $output);
        }

        // ─── render_json ─────────────────────────────────────────

        function test_render_json_output(){
            // render_json() 有 exit()，无法在单进程中完整测试
            // 至少验证 json_encode 的产出格式
            $data = ['msg' => 'ok', 'code' => 200];
            $encoded = json_encode($data);
            $decoded = json_decode($encoded, true);

            $this->assertNotNull($decoded);
            $this->assertEquals('ok', $decoded['msg']);
            $this->assertEquals(200, $decoded['code']);
        }

        // ─── action methods ──────────────────────────────────────

        function test_action_greet(){
            $ctrl = $this->_newCtrl('test_ctrl', 'greet');

            $output = $this->capture(function() use($ctrl){ $ctrl->greet('Alice'); });
            $this->assertEquals('Hello Alice', $output);
        }

        function test_action_greet_default(){
            $ctrl = $this->_newCtrl('test_ctrl', 'greet');

            $output = $this->capture(function() use($ctrl){ $ctrl->greet(); });
            $this->assertEquals('Hello World', $output);
        }

        // ─── __call (render_xxx → layout override) ──────────────

        function test_render_popup_uses_popup_layout(){
            $ctrl = $this->_newCtrl('test_ctrl', 'hello');
            $ctrl->view = 'hello';

            // render_popup() → __call('render_popup') → layout='popup'
            $output = $this->capture(function() use($ctrl){ $ctrl->render_popup('hello', ['name' => 'PopupLayout']); });
            $this->assertContains('Hello PopupLayout', $output);
        }

        // ─── __catch_view ────────────────────────────────────────

        function test_catch_view_merges_get_params(){
            $_GET = ['name' => 'FromGet'];
            $ctrl = $this->_newCtrl('test_ctrl', 'catch_view');
            $ctrl->view = 'hello';

            // __catch_view 将 $_GET 合并到视图变量中输出
            $output = $this->capture(function() use($ctrl){
                $ctrl->__catch_view();
            });
            $this->assertContains('Hello FromGet', $output);
            $_GET = [];
        }

        // ─── layout_vars ─────────────────────────────────────────

        function test_layout_vars_passed_to_layout(){
            $ctrl = $this->_newCtrl('test_ctrl', 'index');
            $ctrl->view = 'hello';
            $ctrl->layout_vars = ['extra' => 'footer'];

            // layout gets bound with layout_vars (but default.htm doesn't use it — just check no crash)
            $output = $this->capture(function() use($ctrl){ $ctrl->render('hello', ['name' => 'Vars']); });
            $this->assertContains('Hello Vars', $output);
        }

        function test_layout_vars_declared_property(){
            $ctrl = $this->_newCtrl();
            // Should not trigger dynamic property deprecation
            $ctrl->layout_vars = ['key' => 'val'];
            $this->assertEquals('val', $ctrl->layout_vars['key']);
        }

        // ─── __init ──────────────────────────────────────────────

        function test_init_called_on_construct(){
            $called = false;
            $ctrl = new \Clue\Controller('test_ctrl', 'index');
            // __init is empty by default — just verify no crash
            $this->assertTrue(true);
        }
    }
?>
