<?php
	require_once 'clue/core.php';
	
	class Clue_Controller{
		public $controller;
		public $action;
		public $view;
		
		protected $view_data=array();
		
		function render($view=null){
			// determine view;
			if($view!=null) $this->view=$view;
			
			extract($this->view_data);
			
			require_once "view/{$this->controller}/{$this->view}.tpl";
		}
		
		function redirect_route($controller, $action='index', $param=null){			
			// TODO: use application instance
			$router=new Clue_Router();
			$uri=$router->uri_for($controller, $action, $param);
			
			$this->redirect($uri);
		}
		
		function redirect($url){
			header("Status: 200");
			header("Location: $url");
			exit();
		}
		
		function set($name, $value){
			$this->view_data[$name]=$value;
		}
	}
?>
