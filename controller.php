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

			$this->layout=new View("/layout/$this->layout");

			$this->__init();
		}

		function __call($name, $args){
			if(preg_match('/render_(.+)/', $name, $m)){
				$this->layout=new View("/layout/{$m[1]}");
				return call_user_func_array(array($this, "render"), $args);
			}
		}

		// 可以重载
		function __init(){}

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

            if($this->layout)
                $this->layout->render(array('content'=>$content));
            else
                echo $content;
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
