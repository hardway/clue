<?php 
namespace Clue{
    class View{
        protected $view;
        protected $template;
        protected $vars;
        
        // TODO: use template_dir

        function __construct($view=null){
            $this->view=strtolower(trim($view, '/'));

            foreach(array("mustache", "html") as $ext){
                $this->template=APP_ROOT."/view/".strtolower($view).".$ext";
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

        function subview($view, $inherit_vars=true){
            if($view[0]!='/' && !preg_match('/:/', $view)){
                // Convert to absolute path based on VIEW_ROOT
                $view=dirname($this->view).'/'.$view;
            }

            $sv=new View($view);
            if($inherit_vars) $sv->vars=$this->vars;

            return $sv;
        }
        
        function render($vars=array()){
            if(is_array($vars))
                $this->vars=array_merge($this->vars, $vars);
            else
                $this->vars=$vars;

            // View Logic
            extract($this->vars);
            if(file_exists("$this->view.php")){
                include "$this->view.php";
            }

            // View Template
            // TODO: rewrite mustache template engine
            if(preg_match('/\.mustache$/i', $this->template)){
                require_once 'Mustache/Autoloader.php';
                \Mustache_Autoloader::register();

                $mustache_base=dirname(APP_ROOT."/view/".$this->view);
                $m = new \Mustache_Engine(array(
                    'loader'=>new \Mustache_Loader_FilesystemLoader($mustache_base),
                    'partials_loader'=>new \Mustache_Loader_FilesystemLoader($mustache_base)
                ));
                echo $m->render(basename($this->view), $this->vars);
            }
            else{                
                include $this->template;
            }
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