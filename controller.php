<?php
	require_once 'clue/core.php';
	
	class Clue_Controller{
		public $controller;
		public $action;
		public $view;
		
		protected $data=array();
		
		function render($view=null){
			// determine view;
			if($view==null) 
				$this->view=$this->action;
			else
				$this->view=$view;
			
			extract($this->data);
			
			require_once "view/{$this->controller}/{$this->view}.tpl";
		}
		
		function set($name, $value){
			$this->data[$name]=$value;
		}
	}
?>
