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
		}
		
		function render_raw($view=null, $data=array()){            
            $view=empty($view) ? $this->view : $view;
            $view=new View('page/'.str_replace('_','/',$this->controller)."/{$view}");
            
            $view->render($data);
		}

        function render($view=null, $data=array()){
            $content=false;
            
            ob_start();
        	$content=$this->render_raw($view, $data);
            $content = ob_get_contents(); 
            ob_end_clean();

            if($this->layout)
                $this->layout->render(array('content'=>$content));
            else
                echo $content;
        }
        
		function redirect_route($controller=null, $action='index', $param=array()){
		    global $app;
		    
		    $controller=empty($controller) ? $this->controller : $controller;
		    
			$app->redirect(url_for($controller, $action, $param));
		}
		
		function redirect($url){
		    global $app;
			$app->redirect($url);
		}
		
		function go_back(){
			$this->redirect(back_url());
		}
	}
}
?>
