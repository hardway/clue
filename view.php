<?php
namespace Clue{
    class View{
        protected $view;
        protected $template;
        protected $vars;

        /**
         * 定位view所在路径
         */
        static function find_view($view, $parent=null, $extension="htm|php"){
            // 相对路径的定位
            // Example:
            //  find_view('view', '/folder')    ==> /folder/view
            //  find_view('/view', '/folder')   ==> /view

            if($view[0]!='/' && !preg_match('/:/', $view) && $parent){
                $view=dirname($parent->view).'/'.$view;
            }

            $candidates=[];

            // 先找VIEW_PATH
            foreach(\Clue\get_view_path() as $dir){
                foreach(explode("|", $extension) as $ext){
                	$candidates[]=$dir.'/'.strtolower($view).".".$ext;
                }
            }
            // 然后是SITE_PATH下面的/source/view/
            foreach(\Clue\get_site_path() as $dir){
                foreach(explode("|", $extension) as $ext){
                	$candidates[]=$dir.strtolower("/source/view/$view").".".$ext;
                }
            }

            if(defined("CLUE_DEBUG") && CLUE_DEBUG>1){
                error_log("[CLUE_DEBUG] looking for view file: $view");
                array_map(function($f){error_log("[CLUE_DEBUG]     candidate: $f");}, $candidates);
            }

            foreach($candidates as $path){
            	if(file_exists($path)){
            		return substr($path, 0, strrpos($path, '.'));
            	}
            }

            return null;
        }

        function __construct($view=null, $parent=null){
            $this->template=self::find_view($view, $parent);

            if(empty($this->template)){
                throw new \Exception("View does not exists: $view");
            }

            $this->view=strtolower(trim($view, '/'));
            $this->vars=array();
        }

        function bind(array $data){
        	$this->vars=array_merge($this->vars, $data);

        	return $this;
        }

        function set($name, $value){
            $this->vars[$name]=$value;
        }

        /**
         * @param $view 如果是数组则按照顺序依次尝试
         * DEPRECATE: $default_view可以在$view数组中，不用作为单独参数
         */
        function incl($view=null, $vars=array(), $default_view=null){
            // 未指定view则默认显示content内容
            if($view==null){
                $this->vars['content']->render();
                return;
            }

            $view_candidates=is_string($view) ? [$view] : $view;
            if($default_view) $view_candidates[]=$default_view; // TODO: deprecate $default_view

            $sv=null;
            foreach($view_candidates as $view){
                if(self::find_view($view, $this)){
                    $sv=new View($view, $this);
                    break;
                }
            }

            if(!$sv) throw new \Exception("View does not found: ". implode(", ", $view_candidates));

            $sv->vars=array_merge($this->vars, $vars ?: []);
            return $sv->render();
        }

        function render($vars=array()){
            if(is_array($vars))
                $this->vars=array_merge($this->vars, $vars);

            // View Logic
            extract(array_merge($GLOBALS ?: [], $this->vars));

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
