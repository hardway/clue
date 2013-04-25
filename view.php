<?php 
namespace Clue{
    class View{
        protected $view;
        protected $template;
        protected $vars;
        
        // TODO: use template_dir

        function __construct($view=null){
            $this->view=strtolower(trim($view, '/'));

            foreach(array("php", "html") as $ext){
                $this->template=DIR_SOURCE."/view/".strtolower($view).".$ext";
                if(file_exists($this->template)){
                    break;
                }
            }

            if(!file_exists($this->template)){
				throw new \Exception("View didn't exists: $view");
            }
            
            $this->vars=array();
        }
        
        function set($name, $value){
            $this->vars[$name]=$value;
        }

        function view($view=null, $inherit_vars=true){
            // Special treat
            if($view==null){
                echo $this->vars['content'];
                return;
            }

            if($view[0]!='/' && !preg_match('/:/', $view)){
                // Convert to absolute path based on VIEW_ROOT
                $view=dirname($this->view).'/'.$view;
            }

            $sv=new View($view);
            if($inherit_vars) $sv->vars=$this->vars;

            return $sv->render();
        }
        
        function render($vars=array()){
            if(is_array($vars))
                $this->vars=array_merge($this->vars, $vars);
            else
                $this->vars=$vars;

            // View Logic
            extract(array_merge($GLOBALS, $this->vars));
            if(file_exists("$this->view.php")){
                include "$this->view.php";
            }

            // View Template
            include $this->template;
        }
        
        function parse_var($str){
            if(preg_match('/\$([a-z0-9_]+)\.([a-z0-9_]+)/i', $str, $m)){
                return "\${$m[1]}['{$m[2]}']";
            }
            else return $str;
        }
    }
}
?>