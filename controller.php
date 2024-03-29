<?php
namespace Clue{
    class Controller{
        public $controller;
        public $action;
        public $view;
        protected $app;

        public $layout="default";
        public $catch_exception=false;  // 设置为true将exception转到->error()并且->redirect_return()

        static function find_controller($controller){
            $controller=strtolower($controller);

            return site_file("source/control/$controller.php");
        }

        function __construct($controller=null, $action=null){
            global $app;

            $this->app=$app;
            $this->controller=$controller;
            $this->action=$action;

            $this->__init();
        }

        function __call($name, $args){
            if(preg_match('/render_(.+)/', $name, $m)){
                $this->layout=$m[1];
                return call_user_func_array(array($this, "render"), $args);
            }
        }

        // 可以重载
        function __init(){}

        // 没有找到定义action，但是存在view
        // 直接显示当前视图
        // 默认将GET和POST变量化传入
        // TODO: view可以使用view.php进行code前置加载
        function __catch_view(){
            $this->render($this->view, array_merge($_GET, $_POST));
        }

        // TODO: 以下方法不应该允许外部调用

        /**
         * 需要在子类中特别定义，对于/a/b/c的url，允许转换为：controller=a, action=__catch_params, params=array(b,c)
         */
        // function __catch_params(){

        // }

        function get_view($view, $data=array()){
            $view=new View((strpos($view, '/')===0) ? $view : $this->controller."/{$view}");
            if(is_array($data)) foreach($data as $k=>$v){
                $view->set($k, $v);
            }

            return $view;
        }

        /**
         * 输出JSON数据
         */
        function render_json($obj){
            header("Content-Type: text/json");
            echo json_encode($obj);
            exit();
        }

        // TODO: 支持render_html
        // @internal

        /**
         * 绝对路径  /product/options    => {VIEWS}/product/options
         * 相对路进  options                => {VIEWS}/{CONTROLLER}/options
         */
        function render($view=null, $data=array()){
            $view=$view ?: $this->view;
            $content=$this->get_view($view, $data);

            // TODO 尝试支持slot，而不是.meta文件（代码分散不利于调试维护）
            // <slot name='header' type='php'>...</slot>
            // 以后也可以支持.vue模板文件

            // 允许前置执行 {$view}.meta 作为修改META定义或者执行一些特殊修改
            // TODO: 将支持phps作为后缀名
            if($meta=View::find_view($this->controller.'/'.$view, null, 'meta')){
                include $meta.".meta";
            }

            // 如果Layout找不到，使用默认的
            if(!View::find_view("/layout/$this->layout")){
                error_log("[CLUE] Layout '$this->layout' missing, using default");
                $this->layout="default";
            }

            $layout=new View("/layout/$this->layout");

            if($layout){
                $layout->bind(@$this->layout_vars ?: []);
                $layout->render(array('content'=>$content));
            }
            else
                echo $content;
        }

        function http_error($code, $message){
            global $app;
            $app->http_error($code, $message);
        }

        function error($message, $context='website'){ global $app; $app->alert($message, $context, 'error'); }
        function success($message, $context='website'){ global $app; $app->alert($message, $context, 'success'); }

        protected function redirect($url){
            global $app;
            $app->redirect($url);
        }

        protected function redirect_action($action, $param=array()){
            global $app;
            $app->redirect(url_for($this->controller, $action, $param));
        }

        protected function redirect_return($default_url=null){
            global $app;
            $app->redirect_return($default_url);
        }

        protected function redirect_referer($default_url=null){
            global $app;
            $app->redirect_referer($default_url);
        }

        protected function is_ajax(){return $this->app->is_ajax();}
    }
}
?>
