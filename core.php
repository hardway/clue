<?php      
namespace Clue{
    /**
     * Bootstrap code, facility to load subsystem here.
     */
    if(!defined("CLUE_VERSION")){
        $version=exec("hg parent --template {latesttag}.{latesttagdistance} 2>&1", $_, $err);
        if($err==0)
            define('CLUE_VERSION', $version);
        else
            define("CLUE_VERSION", "DEVELOPMENT");
    }
    
    // common defination
    if(!defined('DS'))  define("DS", DIRECTORY_SEPARATOR);
    if(!defined('NS'))  define('NS', "\\");
    
    if(!defined('APP_ROOT'))    define('APP_ROOT', dirname($_SERVER['SCRIPT_FILENAME']));
    if(!defined('APP_BASE')){
        $dir=dirname($_SERVER['PHP_SELF']);
        $dir= $dir=="\\" ? "/" : "$dir/";
        
        define('APP_BASE', $dir);
    }

    $_CLASS_PATH=array();

    function add_class_path($path){
        global $_CLASS_PATH;

        // Normalize path
        $path=realpath($path);

        if($path!==false && !in_array($path, $_CLASS_PATH)){
            $_CLASS_PATH[]=$path;
        }
    }

    function get_class_path(){
        global $_CLASS_PATH;
        return $_CLASS_PATH;
    }

    function autoload_load($class){
        global $_CLASS_PATH;

        $class=str_replace(NS, '/', $class);
        $class=str_replace('_', '/', $class);
        $class=strtolower($class);

        if(substr_compare($class, 'clue/', 0, 5)==0){
            // Special treat for Clue\ classes. For they might reside in a phar file.
            $class=substr($class, 5);
            if(file_exists(__DIR__.'/'.$class.".php")){
                require_once __DIR__.'/'.$class.".php";
                return;
            }
        }
        else{
            foreach($_CLASS_PATH as $path){
                if(file_exists($path.'/'.$class.".php")){
                    require_once $path.'/'.$class.".php";
                    return;
                }
            }
        }
    }
        
    add_class_path(APP_ROOT."/class");
    spl_autoload_register("Clue\autoload_load");
}
?>
