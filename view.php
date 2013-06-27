<?php
namespace Clue{
    class View{
        protected $view;
        protected $template;
        protected $vars;

        // TODO: use template_dir

        function __construct($view=null){
            $this->view=strtolower(trim($view, '/'));

            $view_dirs=array(DIR_SOURCE.'/view/');
            if(defined("THEME")) array_unshift($view_dirs, APP_ROOT.'/'.THEME.'/view/');

            $this->template=null;
            foreach($view_dirs as $dir){
                foreach(array("htm", "php") as $ext){
                    if(file_exists($dir.strtolower($view).".".$ext)){
                        $this->template=$dir.strtolower($view);
                        break;
                    }
                }
                if($this->template!=null) break;
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

        function incl($view=null, $vars=array()){
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
            $sv->vars=array_merge($this->vars, $vars);

            return $sv->render();
        }

        function render($vars=array()){
            if(is_array($vars))
                $this->vars=array_merge($this->vars, $vars);

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
