<?php
namespace Clue{
	class Controller{
		public $controller;
		public $action;
		public $view;

		public $layout="default";

		static function find_controller($controller){
			$controller=strtolower($controller);

            return site_file("source/control/$controller.php");
		}

		function __construct($controller=null, $action=null){
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
		 * 绝对路径  /product/options    => {VIEWS}/product/options
		 * 相对路进  options 				=> {VIEWS}/{CONTROLLER}/options
		 */
        function render($view=null, $data=array()){
            $content=$this->get_view($view?:$this->view, $data);

            // 允许前置执行 {$view}.meta 作为修改META定义或者执行一些特殊修改
            if($meta=View::find_view($this->controller.'/'.$view?:$this->view, null, 'meta')){
            	include $meta.".meta";
            }

            $layout=new View("/layout/$this->layout");
            if($layout)
                $layout->render(array('content'=>$content));
            else
                echo $content;
        }

        function http_error($code, $message){
        	global $app;
        	$app->http_error($code, $message);
        }

        function error($message, $context='website'){ global $app; $app->alert($message, $context, 'error'); }
        function success($message, $context='website'){ global $app; $app->alert($message, $context, 'success'); }

		function redirect($url){
		    global $app;
			$app->redirect($url);
		}

		function redirect_action($action, $param=array()){
		    global $app;
			$app->redirect(url_for($this->controller, $action, $param));
		}

		function redirect_return($default_url=null){
			global $app;
			$app->redirect_return($default_url);
		}

		function redirect_referer($default_url=null){
			global $app;
			$app->redirect_referer($default_url);
		}
	}
}
?>
