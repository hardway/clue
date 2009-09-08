<?php
	require_once 'clue/core.php';
	
	class Clue_Controller{
		public $controller;
		public $action;
		public $view;
		
		protected $view_data=array();
		
		function __construct($controller=null, $action=null){
			$this->controller=$controller;
			$this->action=$action;
		}
		
		function render($view=null){
			// determine view;
			if($view!=null) $this->view=$view;
			
			extract($this->view_data);
			
			require_once "view/{$this->controller}/{$this->view}.tpl";
		}
		
		function redirect_route($controller, $action='index', $param=null){			
			Clue_Application::router()->redirect_route($controller, $action, $param);
		}
		
		function redirect($url){
			Clue_Application::router()->redirect($url);
		}
		
		function set($name, $value){
			$this->view_data[$name]=$value;
		}
	}
?>
