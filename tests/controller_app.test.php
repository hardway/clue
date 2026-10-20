<?php
// Controller 对 $app 的转发行为测试（redirect / error / success / http_error）

class Test_Fake_Router{
    function reform($controller, $action, $params){
        return "/$controller/$action?".http_build_query($params);
    }
}

class Test_Fake_App implements ArrayAccess{
    public $calls=[];

    // url_for 需要 $app['router']
    function offsetExists($k): bool { return $k === 'router'; }
    function offsetGet($k): mixed { return $k === 'router' ? new Test_Fake_Router() : null; }
    function offsetSet($k, $v): void {}
    function offsetUnset($k): void {}

    function __call($name, $args){
        $this->calls[]=[$name, $args];
    }

    function is_ajax(){ return false; }

    function last(){
        return end($this->calls);
    }
}

class Test_Ctrl_App extends \Clue\Controller{
    function call_redirect($url){ return $this->redirect($url); }
    function call_redirect_action($action, $param=[]){ return $this->redirect_action($action, $param); }
    function call_redirect_return($default=null){ return $this->redirect_return($default); }
    function call_redirect_referer($default=null){ return $this->redirect_referer($default); }
    function call_error($message, $context='website'){ return $this->error($message, $context); }
    function call_success($message, $context='website'){ return $this->success($message, $context); }
    function call_http_error($code, $message){ return $this->http_error($code, $message); }
}

class Test_Controller_App extends PHPUnit_Framework_TestCase{
    protected $origApp;
    protected $fake;

    protected function setUp(): void{
        if(!defined('Clue\\APP_BASE')) define('Clue\\APP_BASE', '/');
        if(!defined('Clue\\APP_URL')) define('Clue\\APP_URL', 'http://localhost');

        $this->origApp=$GLOBALS['app'] ?? null;
        $this->fake=new Test_Fake_App();
        $GLOBALS['app']=$this->fake;
    }

    protected function tearDown(): void{
        $GLOBALS['app']=$this->origApp;
    }

    protected function ctrl(){
        return new Test_Ctrl_App('test_app');
    }

    function test_redirect_forwards_url(){
        $this->ctrl()->call_redirect('/somewhere');
        $this->assertEquals(['redirect', ['/somewhere']], $this->fake->last());
    }

    function test_redirect_action_builds_url(){
        $expected=\Clue\url_for('test_app', 'show', ['id'=>1]);
        $this->ctrl()->call_redirect_action('show', ['id'=>1]);
        $this->assertEquals(['redirect', [$expected]], $this->fake->last());
    }

    function test_redirect_return_forwards_default(){
        $this->ctrl()->call_redirect_return('/fallback');
        $this->assertEquals(['redirect_return', ['/fallback']], $this->fake->last());
    }

    function test_redirect_referer_forwards_default(){
        $this->ctrl()->call_redirect_referer('/fallback');
        $this->assertEquals(['redirect_referer', ['/fallback']], $this->fake->last());
    }

    function test_error_uses_error_level(){
        $this->ctrl()->call_error('boom', 'form');
        $this->assertEquals(['alert', ['boom', 'form', 'error']], $this->fake->last());
    }

    function test_success_uses_success_level(){
        $this->ctrl()->call_success('ok');
        $this->assertEquals(['alert', ['ok', 'website', 'success']], $this->fake->last());
    }

    function test_http_error_forwards_code_and_message(){
        $this->ctrl()->call_http_error(403, 'nope');
        $this->assertEquals(['http_error', [403, 'nope']], $this->fake->last());
    }
}
