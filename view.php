<?php
namespace Clue{
    class View{
        protected $view;
        protected $template;
        protected $vars;

        /**
         * 定位view所在路径
         */
        static function find_view($view, $parent=null){
            // 相对路径的定位
            // Example:
            //  find_view('view', '/folder')    ==> /folder/view
            //  find_view('/view', '/folder')   ==> /view
            if($view[0]!='/' && !preg_match('/:/', $view) && $parent){
                $view=dirname($parent->view).'/'.$view;
            }

            $view_dirs=array(APP_ROOT.'/source/view/');
            if(defined("SITE")) array_unshift($view_dirs, APP_ROOT.'/'.SITE.'/view/');

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
                throw new \Exception("View does not exists: $view");
            }

            $this->view=strtolower(trim($view, '/'));
            $this->vars=array();
        }

        function set($name, $value){
            $this->vars[$name]=$value;
        }

        function incl($view=null, $vars=array(), $default_view=null){
            // 未指定view则默认显示content内容
            if($view==null){
                $this->vars['content']->render();
                return;
            }

            $vars=$vars?:array();

            try{
                $sv=new View($view, $this);
            }
            catch(\Exception $e){
                if($default_view){
                    $sv=new View($default_view, $this);
                    $vars['missing_view']=$view;
                }
                else{
                    throw $e;
                }
            }

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
