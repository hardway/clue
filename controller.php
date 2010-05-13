<?php
	require_once __DIR__.'/core.php';
	
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
			$content=false;
			ob_start();
			$this->render_raw($view);
			$content = ob_get_contents();
			ob_end_clean();
			
			if(!function_exists('skin') || skin()==null){
				echo $content;
			}
			else{
				skin()->setBody($content);
				skin()->render();
			}
		}
		
		function render_raw($view=null){
			// determine view;
			if($view!=null) $this->view=$view;
			$view=strtolower("view/".str_replace('_','/',$this->controller)."/{$this->view}.tpl");
			
			if(file_exists($view)){
				extract($this->view_data);
				require_once $view;
			}
			else
				throw new Exception("View didn't exists: $view");
		}
		
		function render_html_options($options, $selected){
		    foreach($options as $op){
		        $name=$op;
		        $value=$op;
		        
		        echo "<option value='$value' ".(in_array($value, $selected) ? "selected='1'":"").">$name</option>";
		    }
		}
		
		function redirect_route($controller, $action='index', $param=null){			
			Clue_Application::router()->redirect_route($controller, $action, $param);
		}
		
		function redirect($url){
			Clue_Application::router()->redirect($url);
		}
		
		function goback(){
			$this->redirect($_SERVER['HTTP_REFERER']);
		}
		
		function set($name, $value){
			$this->view_data[$name]=$value;
		}
		
		protected function make_empty_array($keys){
		    if(!is_array($keys)) $keys=explode('|', $keys);
		    $ary=array();
		    foreach($keys as $k){
		        if(preg_match('/([^\[\]]+)\[\]$/', $k, $m)){
		            $ary[trim($m[1])]=array();
		        }
		        else
		            $ary[trim($k)]=null;
		    }
		    return $ary;
		}
	}
?>
