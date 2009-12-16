<?php  
	require_once 'clue/core.php';
	
	class Clue_Config{
		static function get_default_config_path(){
			if(Clue_Tool::os()=='windows'){
				return "C:/config";
			}
			throw new Exception("Can't determine operating system.");
		}
		
		public $config;
		
		function __construct($file=null){			
			$this->load($file);
		}
		
		function __get($name){
			if(isset($this->$name)) 
				return $this->$name;
			else if(isset($this->config->$name))
				return $this->config->$name;
			else
				return false;
		}
		
		// Convert array to object recursively
		function array_to_obj(array $a){
			$o=(object)$a;
			foreach($a as $n=>$v){
				if(is_array($v))
					$o->$n=$this->array_to_obj($v);
			}
			return $o;
		}
		
		/**
		 * Load configuration file with ini format.
		 * 
		 * If no file is given, the default file (default.ini) will be used
		 * If the file didn't exist in relative path, try it in default config path
		 *
		 * @param string $file 
		 */
		function load($file=null){
			if($file==null) $file='default.ini';
			
			if(!file_exists($file)){
				$file=self::get_default_config_path . '/' . $file;
			}
			
			if(!file_exists($file)) throw new Exception("Can't find config file: $file !");
			
			$this->config=$this->array_to_obj(parse_ini_file($file, true));
		}
	}
?>
