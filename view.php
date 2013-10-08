<?php
namespace Clue{
    class View{
        protected $view;
        protected $template;
        protected $vars;

        static function find_view($view, $parent=null){
            $view_dirs=array(DIR_SOURCE.'/view/');
            if(defined("THEME")) array_unshift($view_dirs, APP_ROOT.'/'.THEME.'/view/');

            // Convert to absolute path based on VIEW_ROOT
            if($view[0]!='/' && !preg_match('/:/', $view) && $parent){
                $view=dirname($parent->view).'/'.$view;
            }

            $template=null;
            foreach($view_dirs as $dir){
                foreach(array("htm", "php") as $ext){
                    if(file_exists($dir.strtolower($view).".".$ext)){
                        $template=$dir.strtolower($view);
                        break;
                    }
                }
                if($template!=null) break;
            }

            return $template;
        }

        function __construct($view=null, $parent=null){
            $this->template=self::find_view($view, $parent);

            if(empty($this->template)){
                // TODO: is this error tolarable?
                exit("View didn't exists: $view");
                //throw new \Exception("View didn't exists: $view");
            }

            $this->view=strtolower(trim($view, '/'));
            $this->vars=array();
        }

        function set($name, $value){
            $this->vars[$name]=$value;
        }

        function incl($view=null, $vars=array()){
            // 未指定view则默认显示content内容
            if($view==null){
                $this->vars['content']->render();
                return;
            }

            $sv=new View($view, $this);
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
    }
}
?>
