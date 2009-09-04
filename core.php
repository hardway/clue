<?php  
	define('CLUE_VERSION', '0.1');
	
	// common defination
	define("DS", DIRECTORY_SEPARATOR);

	function autoload_load($path){
		if(file_exists($path))
			require_once $path;
		else if(file_exists(strtolower($path)))
			require_once strtolower($path);
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
	
	spl_autoload_register("autoload_application");
?>
