<?php
namespace Clue{
	class Controller{
		public $controller;
		public $action;
		public $view;

		protected $layout="default";

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

		// 直接显示当前视图，无需定义action
		// 用下划线分隔action，第一个前缀可以被识别为layout
		// 默认将GET和POST变量化传入
		function __catch_view(){
			if(preg_match('/^([^_]+)_/', $this->view, $m) && View::find_view("/layout/{$m[1]}")){
				$this->layout=$m[1];
			}

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
            $content=false;

            $content=$this->get_view($view?:$this->view, $data);

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
