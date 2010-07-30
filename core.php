<?php  
	define('CLUE_VERSION', '5.3.001');
	
	// common defination
	define("DS", DIRECTORY_SEPARATOR);
	if(!defined('APP_ROOT')) define('APP_ROOT' , '.');
	if(!defined('CLUE_ROOT')) define('CLUE_ROOT', dirname(__DIR__));

	function autoload_load($path){
		$path=strtolower($path);	// Always try lowercase

		if(file_exists($path)){
			require_once $path;
			return true;
		}
		
		return false;
	}
	
	// register default auto load path
	function autoload_application($class){
		// Detect "Helper"
		if(strtolower(substr($class, -6))=='helper'){
			autoload_load(APP_ROOT . "/helper/{$class}.php");
		}
		else{
			require_once __DIR__.'/activerecord.php';
			$ok=autoload_load(APP_ROOT . "/model/{$class}.php");
			
			if(!$ok){
			    foreach(Clue::$classPath as $path){
			        $ok=autoload_load($path . "/{$class}.php");
			        if($ok) break;
			    }
			}
			
			if(!$ok)
			    throw new Exception("Can't load class $class.");
		}
	}
	
	// register Clue library class auto loader
	function autoload_clue($class){
		if(substr($class, 0, 4)=="Clue"){
			autoload_load(CLUE_ROOT . DS . str_replace("_", DS, strtolower($class)). ".php");
		}
	}
	
	spl_autoload_register("autoload_clue");
	spl_autoload_register("autoload_application");
	
	class Clue{
	    public static $classPath=array();
	    
	    static function add_class_path($path){
	        self::$classPath[]=$path;
	    }
	    
    	static function enable_debug(){
    	    require_once __DIR__."/debug.php";
    	    set_exception_handler(array("Clue_Debug","view_exception"));
    	    set_error_handler(array("Clue_Debug", "view_error"));
        }
        
        static function enable_log($logDirectory){
    	    require_once __DIR__."/log.php";
    	    Clue_Log::set_log_dir($logDirectory);
    	    set_exception_handler(array("Clue_Log","log_exception"));
    	    set_error_handler(array("Clue_Log", "log_error"));
        }
    }
?>
