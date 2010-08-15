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
		
		function render($view=null, $html=false){
			$content=false;
			
			ob_start();
			
			if($html)
			    echo $view;
			else
			    $this->render_raw($view);
			    
			$content = ob_get_contents(); ob_end_clean();
			
			if(!function_exists('skin') || skin()==null){
				echo $content;
			}
			else{
				skin()->set_body($content);
				skin()->render();
			}
		}
		
		function render_raw($view=null){
			// determine view;
			if($view!=null) $this->view=$view;
			$view=strtolower(APP_ROOT . "/view/".str_replace('_','/',$this->controller)."/{$this->view}.tpl");
			
			if(file_exists($view)){
				extract($this->view_data);
				include $view;
			}
			else
				throw new Exception("View didn't exists: $view");
		}
		
        function render_snippet($snippet, $data=array()){
            // determine view;
            $view=strtolower(APP_ROOT . "/view/".str_replace('_','/',$this->controller)."/snippet/{$snippet}.tpl");
            
            if(file_exists($view)){
                extract($this->view_data);
                extract($data);
                include $view;
            }
            else
                throw new Exception("Snippet didn't exists: $snippet");
        }
        
        function load_snippet($snippet, $data=array()){
			ob_start();
			$this->render_snippet($snippet, $data);			    
			$content = ob_get_contents(); ob_end_clean();
			
            return $content;
        }
        
		function redirect_route($controller, $action='index', $param=array()){			
			Clue_Application::router()->redirect_route($controller, $action, $param);
		}
		
		function redirect($url){
			Clue_Application::router()->redirect($url);
		}
		
		function go_back(){
			$this->redirect($_SERVER['HTTP_REFERER']);
		}
		
		function set($name, $value=null){
		    if(is_array($name) && is_null($value)){
		        $this->view_data=array_merge($this->view_data, $name);
		    }
		    else
			    $this->view_data[$name]=$value;
		}
	}
?>
