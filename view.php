<?php
namespace Clue{
    class View{
        protected $view;
        protected $template;
        protected $vars;

        // TODO: use template_dir

        function __construct($view=null){
            $this->view=strtolower(trim($view, '/'));

            foreach(array("htm", "php") as $ext){
                $this->template=DIR_SOURCE."/view/".strtolower($view);

                if(file_exists($this->template.".".$ext)){
                    break;
                }

                $this->template=null;
            }

            if(empty($this->template)){
                exit("View didn't exists: $view");
				//throw new \Exception("View didn't exists: $view");
            }

            $this->vars=array();
        }

        function set($name, $value){
            $this->vars[$name]=$value;
        }

        function incl($view=null, $param=array()){
            $inherit_vars=$param['inherit_vars'] ?: true;

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

            // Code Behind
            if(file_exists("$this->template.php")){
                include "$this->template.php";
            }
            // View file
            if(file_exists($this->template.".htm")){
                include $this->template.".htm";
            }
        }

        function __toString(){
            $this->render();
            return "";
        }
    }
}
?>
