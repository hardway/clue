<?php 
	class ErrorController extends Clue_Controller{
		function render($view=null){
			// determine view;
			if($view!=null) $this->view=$view;
			
			extract($this->view_data);
			
			require "view/error/{$this->view}.tpl";
		}
		
		function noController(){
			$this->set('error', 'Controller Missing');
			$this->render('404');
		}
		
		function noAction(){
			$this->set('error', 'Action Missing');
			$this->render('404');
		}
	}
?>
