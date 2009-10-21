<?php  
	require_once 'clue/core.php';
	require_once 'clue/config.php';
	require_once 'clue/database.php';
	
	class Clue_Application{
		public $base;
		public $config;
		public $db;
		public $router;
		public $options=array(
			"url_rewrite"=>true
		);
		
		function __construct($appbase='.', $options=null){
			$this->base=$appbase;
			$this->config=new Clue_Config("$appbase/config/config.ini");
			
			$param=array(
				'host'=>$this->config->database->host, 
				'db'=>$this->config->database->db, 
				'username'=>$this->config->database->username, 
				'password'=>$this->config->database->password
			);
			if(isset($this->config->database->encoding))
				$param['encoding']=$this->config->database->encoding;

			$this->db=Clue_Database::create($this->config->database->type, $param);
			
			// throw exception if database is not connectable
			if($this->db->errors){
				throw new Exception($this->db->lasterror['error']);
			}
			
			// Extend options
			if(is_array($options)) foreach($options as $o=>$v){
				$this->options[$o]=$v;
			}
			
			$this->router=new Clue_Router($this->options["url_rewrite"]);
		}
		
		static protected $instance;
		static function init($appbase='.', $options=null){
			self::$instance=new Clue_Application($appbase, $options);
			
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
	// global short cut
	function url_for($controller, $action='index', $params=null){
		return Clue_Application::router()->uri_for($controller, $action, $params);
	}
	
	function app(){
		return Clue_Application::getInstance();
	}
	function appbase(){
		return Clue_Application::getInstance()->router->base();
	}
?>
