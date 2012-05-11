<?php      
namespace Clue{
    /**
     * Bootstrap code, facility to load subsystem here.
     */
     
    define('CLUE_VERSION', '5.3.001');
    
    // common defination
    if(!defined('DS'))          define("DS", DIRECTORY_SEPARATOR);
    
    if(!defined('APP_ROOT'))    define('APP_ROOT', dirname($_SERVER['SCRIPT_FILENAME']));
    if(!defined('APP_BASE')){
        $dir=dirname($_SERVER['PHP_SELF']);
        $dir= $dir=="\\" ? "/" : "$dir/";
        
        define('APP_BASE', $dir);
    }

    function autoload_load($class){
        if(substr($class, 0, 5)=="Clue\\"){
            $class=str_replace("\\", '/', substr($class, 5));
            $path=__DIR__ . DS . str_replace("_", DS, strtolower($class)). ".php";
        }
        else{
            $path=APP_ROOT.'/class/'.$class.'.php';
        }
        
        if(!file_exists($path)) $path=strtolower($path);
        if(!file_exists($path)) return false;
        
        require_once $path;
    }
        
    spl_autoload_register("Clue\autoload_load");
    
    class Clue{
        // TODO: remove this $classpath out of class 'clue'
        public static $classPath=array();
        
        static function add_class_path($path){
            self::$classPath[]=$path;
        }
    }
}
?>
