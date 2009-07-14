<?php  
	require_once 'clue/core.php';
	
	class Clue_Config{
		protected $config;
		
		function __construct($file=null){
			$this->load($file);
		}
		
		function __get($name){
			if(isset($this->$name)) 
				return $this->$name;
			else
				return $this->config->$name;
		}
		
		// Convert array to object recursively
		function array_to_obj(array &$a){
			$o=(object)$a;
			foreach($a as $n=>$v){
				if(is_array($v))
					$o->$n=$this->array_to_obj($v);
			}
			return $o;
		}
		
		function load($file=null){
			if($file==null || !file_exists($file)) throw new Exception("Can't find config file: $file !");
			
			$this->config=$this->array_to_obj(parse_ini_file($file, true));
		}
	}
?>
