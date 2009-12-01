<?php  
	define('CLUE_VERSION', '0.1');
	
	// common defination
	define("DS", DIRECTORY_SEPARATOR);

	function autoload_load($path){
		$path=strtolower($path);	// Always try lowercase
		
		if(file_exists($path))
			require_once $path;
		else
			throw new Exception("Can't load class: $path");	
	}
	
	// register default auto load path
	function autoload_application($class){
		// Detect "Helper"
		if(strtolower(substr($class, -6))=='helper'){
			autoload_load("helper/{$class}.php");
		}
		else{
			require_once 'clue/activerecord.php';
			autoload_load("model/{$class}.php");
		}
	}
	
	// register Clue library class auto loader
	function autoload_clue($class){
		if(substr($class, 0, 4)=="Clue"){
			$clue_root=dirname(dirname(__FILE__));
			autoload_load($clue_root . DS . str_replace("_", DS, strtolower($class)). ".php");
		}
	}
	
	spl_autoload_register("autoload_clue");
	spl_autoload_register("autoload_application");
?>
