<?php  
    class Clue_Tool_Constructor_Skeleton{
        public $app_root;
        
        function __construct(){
            $this->app_root=getcwd();
        }
        
        function controller_exists($controller){
            $file=$this->app_root.'/controller/'.strtolower(str_replace('_','/',$controller)).'.php';

            if(!file_exists($file)){
                echo "Controller $controller didn't exist\n";
                return false;
            }
            
            $source=file_get_contents($file);
            $class=$controller.'_Controller';
            if(!preg_match('/class\s+'.$class.'/i', $source)){
                echo "Wrong controller definition: $controller ==> $class.\n";
                exit();
            }
            
            return true;
        }
        
        function action_exists($controller, $action){
            $file=$this->app_root.'/controller/'.strtolower(str_replace('_','/',$controller)).'.php';
            $class=$controller.'_Controller';
            $source=file_get_contents($file);
            if(!preg_match('/function\s+'.$action.'/i', $source)){
                echo "Action \"$class::$action\" didn't exist\n";
                return false;
            }
            
            return true;
        }
        
        function add_controller($controller){
            if(Clue_Tool_Constructor::_confirm("Create controller for \"$controller ?")==false){
                return Clue_Tool_Constructor::_cancel();
            }

            $file=$this->app_root.'/controller/'.strtolower(str_replace('_','/',$controller)).'.php';
            $class=$controller.'_Controller';
            
            // create empty controller file
            $tmpl=<<<END
<?php
    class $class extends Clue_Controller{
        public \$layout='default';
    }
?>
END;
            file_put_contents($file, $tmpl);
            
            // create empty view folder
            mkdir($this->app_root.'/view/'.strtolower(str_replace('_','/',$controller)));
        }
        
        function add_action($controller, $action){
            $file=$this->app_root.'/controller/'.strtolower(str_replace('_','/',$controller)).'.php';
            $class=$controller.'_Controller';
            $source=file_get_contents($file);
            
            if(Clue_Tool_Constructor::_confirm("Create action of \"$class::$action\" ?")==false){
                return Clue_Tool_Constructor::_cancel();
            }

            // add function definition
            $source=$this->_inject_action($source, $action);
            file_put_contents($file, $source);
            
            // create empty view file
            $view_file=$this->app_root.'/view/'.strtolower(str_replace('_','/',$controller))."/$action.html";
            if(!file_exists($view_file)){
                $view_tmpl="Edit me at $view_file";
                file_put_contents($view_file, $view_tmpl);
            }
            
            echo "Action \"$class::$action\" created.\n";
        }
        
        private function _inject_action($text, $action){
            $tmpl=<<<END
    
    function $action(){
        \$this->render();
    } // function $action()
END;
            $lines=explode("\n", $text);
            $insert_point=null;
            
            for($i=count($lines)-1; $i>0; $i--){
                if(trim($lines[$i])=="}"){
                    $insert_point=$i;
                }
            }
            
            if(!empty($insert_point)){
                $lines=array_merge(array_slice($lines, 0, $insert_point+1), array($tmpl), array_slice($lines, $insert_point+1));
            }
            
            return implode("\n", $lines);
        }
    }
?>
