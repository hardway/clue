<?php  
    class Clue_Tool_Constructor_Minifier{
        protected $root;
        
        function __construct($root){
            $this->root=$root;
        }
        
        function build($dest){
            if(!Phar::canWrite()) {
                throw new Exception('Unable to create PHAR archive, must be phar.readonly=Off option in php.ini');
            }

            if(file_exists($dest)) unlink($dest);

            $phar = new Phar($dest);
            $phar->convertToExecutable(Phar::PHAR);
            $phar->startBuffering();
            $phar->buildFromDirectory($this->root);
            $phar->setStub('
            <?php 
                Phar::mapPhar("Clue"); 
                        
                require_once "phar://Clue/core.php";
                spl_autoload_register("Clue\autoload_load");
                require_once "phar://Clue/application.php";
                require_once "phar://Clue/tool.php";
                
                if(php_sapi_name()=="cli" && preg_match("/clue/i", $argv[0])){
                    require_once "phar://Clue/tool/constructor.php";
                    
                    $ctor=new Clue_Tool_Constructor();
                    $command=isset($argv[1]) ? $argv[1] : "help";
                    
                    if(method_exists($ctor, $command)){
                        call_user_func_array(array($ctor, $command), array_slice($argv, 2));
                    }
                    else{
                        echo "Unknown command: $command\n";
                    }        
                }
                __HALT_COMPILER(); 
            ?>');
            $phar->stopBuffering();
            echo "Phar build at: $dest";
        }
        
        function __toString(){
            return $this->code;
        }
    }
    
    class Clue_Tool_Constructor{
        function help(){
            echo <<<END
Usage: clue [command] {arguments...}
    init    Initialize application skeleton
    add     Add controller::action skeleton
            eg: clue add controller {action}
    help    Display this help screen
    
END;
        }
        
        function build($dest=null){
            if(empty($dest)) $dest=getcwd().'/clue.phar';
            $minifier=new Clue_Tool_Constructor_Minifier(dirname(__DIR__));
            $minifier->build($dest);
            //file_put_contents($dest, $minifier);
        }
                
        function init($path=null){
            $skeleton=__DIR__ . DIRECTORY_SEPARATOR . 'skeleton';
            $site=empty($path) ? getcwd() : $path;
            
            if(false==$this->_confirm("New application code skeleton will be copied into: \"$site\", continue?")){
                return $this->_cancel();
            }
            
            if(!is_dir($site)) mkdir($site, 0755, true);
            $this->_deepcopy($skeleton, $site);
        }
        
        function db($target){
            // Determine app root
            // Detect current database
            // Execute Migration
        }
        
        function add($controller, $action='index'){
            $skel=new Clue_Tool_Constructor_Skeleton();
            if(!$skel->controller_exists($controller)){
                $skel->add_controller($controller);
            }
            else{
                echo "Controller $controller already exists.\n";
            }
            
            if($skel->controller_exists($controller)){  // In case user cancelled controller creation
                if(!$skel->action_exists($controller, $action)){
                    $skel->add_action($controller, $action);
                }
                else{
                    echo "Action $controller::$action already exists.\n";
                }
            }
        }
        
        function remove($controller, $action=null){
            // TODO
        }
        
        static function _confirm($question){
            printf("%s (Y/n) ", $question);
            $response=fgetc(STDIN);
            
            return $response=='Y';
        }
        
        static function _cancel(){
            echo "Operation Canceled\n";
        }
        
        private function _deepcopy($src, $dest){
            echo "Copying $src --> $dest \n";
            
            if(is_file($src)){	// File Mode
                copy($src, $dest);
                touch($dest);
            }
            else if(is_dir($src)){	// Directory Mode
                // Always make sure the destination folder exists
                if(!is_dir($dest)) mkdir($dest, 755, true);
                
                $dh=opendir($src);
                while(($file=readdir($dh))!==false){
                    if($file=='.' || $file=='..') continue;
                    $this->_deepcopy($src.DIRECTORY_SEPARATOR.$file, $dest.DIRECTORY_SEPARATOR.$file);
                }
                closedir($dh);
            }
        }
    }
?>
