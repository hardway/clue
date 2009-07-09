<?php  
	define('CLUE_VERSION', '0.1');
	
	// register default auto load path
	function autoload_application($class){
		$cp="model/{$class}.php";
		if(file_exists($cp)) require_once $cp;
	}
	
	spl_autoload_register("autoload_application");
?>
