<?php  
	require_once 'clue/core.php';
	require_once 'clue/config.php';
	require_once 'clue/database.php';
	
	class Clue_Application{
		public $base;
		public $config;
		public $db;
		public $router;
		
		function __construct($appbase='.'){
			$this->base=$appbase;
			$this->config=new Clue_Config("$appbase/config/config.ini");
			$this->db=Clue_Database::create($this->config->database->type, array(
				'host'=>$this->config->database->host, 
				'db'=>$this->config->database->db, 
				'username'=>$this->config->database->username, 
				'password'=>$this->config->database->password
			));
			
			$this->router=new Clue_Router();
		}
		
		static protected $instance;
		static function init($appbase='.'){
			self::$instance=new Clue_Application($appbase);
			
			session_start();			
		}
		static function run(){
			self::router()->dispatch();
		}
		static function initialized(){ return is_object(self::$instance); }
		
		static function getInstance(){
			if(empty(self::$instance)) throw new Exception("Application not initialized!");
			return self::$instance;
		}
		
		static function base(){return self::getInstance()->base;}
		static function config(){ return self::getInstance()->config; }
		static function db(){ return self::getInstance()->db; }
		static function router(){ return self::getInstance()->router; }
	}
?>
