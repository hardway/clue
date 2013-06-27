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

			$this->layout=new Layout(empty($this->layout) ? 'default' : $this->layout);

			$this->__init();
		}

		function __call($name, $args){
			if(preg_match('/render_(.+)/', $name, $m)){
				$this->layout=new Layout($m[1]);
				return call_user_func_array(array($this, "render"), $args);
			}
		}

		// 可以重载
		function __init(){}

		function get_view($view, $data=array()){
			$view=new View(str_replace('_','/',$this->controller)."/{$view}");
			if(is_array($data)) foreach($data as $k=>$v){
				$view->set($k, $v);
			}

			return $view;
		}

        function render($view=null, $data=array()){
            $content=false;

            $content=$this->get_view($view?:$this->view, $data);

            if($this->layout)
                $this->layout->render(array('content'=>$content));
            else
                echo $content;
        }

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
