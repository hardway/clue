<?php  
	require_once __DIR__.'/core.php';
	require_once __DIR__.'/config.php';
	require_once __DIR__.'/database.php';
	
	class Clue_Application{
		public $base;
		public $config;
		public $db;
		public $router;
		public $options=array(
			"url_rewrite"=>true
		);
		
		public $controller;
		public $action;
		public $params;
		
		protected $beforeDispatchHandler=null;
		
		function __construct($appbase='.', $options=null){
			$this->base=$appbase;
			
			// Extend options
			if(is_array($options)) foreach($options as $o=>$v){
				$this->options[$o]=$v;
			}
			
			if(isset($this->options['config'])){
				$this->config=$this->options['config'];
			}
			else{
			    // Make sure the config exists
			    $cfgFile="$appbase/config/config.ini";
			    if(file_exists($cfgFile))
				    $this->config=new Clue_Config($cfgFile);
				else
				    $this->config=new Clue_Config();
			}
			
			if($this->config->database){
				$this->set_default_database((array)$this->config->database);
			}
			
			$this->router=new Clue_Router(array(
				'url_rewrite'=>$this->options["url_rewrite"]
			));
			
			if(isset($this->config->debug) && $this->config->debug==true){
			    Clue::enable_debug();
			}
			else if(!isset($this->config->log) || $this->config->log!==false){
			    if(!isset($this->config->log) || !is_string($this->config->log))
			        $this->config->log=$appbase . DIRECTORY_SEPARATOR . 'log';
			    Clue::enable_log($this->config->log);
			}
		}
		
		function set_default_database($param){
			$this->db=Clue_Database::create($param['type'], $param);
			
			// throw exception if database is not connectable
			if($this->db->errors){
				throw new Exception($this->db->lasterror['error']);
			}
		}
		
		static protected $instance;
		static function init($appbase='.', $options=null){
			self::$instance=new Clue_Application($appbase, $options);
			
			session_start();
		}
		
		function before_dispatch($callback=null){
		    $this->beforeDispatchHandler=$callback;
		}
		
        function prepare(){
            $map=$this->router->resolve($_SERVER['REQUEST_URI']);
                        
            $this->controller=$map['controller'];
            $this->action=$map['action'];
            $this->params=$map['params'];
        }
		
		function dispatch(){
			$this->router->route($this->controller, $this->action, $this->params);
		}
		
		function run(){
			$this->prepare();
			
            // call plugin
            if(is_callable($this->beforeDispatchHandler)){
                call_user_func($this->beforeDispatchHandler, $this->controller, $this->action, $this->params);
            }
            
			$this->dispatch();
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
	function url_for($controller, $action='index', $params=array()){
		return Clue_Application::router()->url_for($controller, $action, $params);
	}
	
	function app(){
		return Clue_Application::getInstance();
	}
	function appbase(){
		return Clue_Application::getInstance()->router->base();
	}
	function assets(){
	    return appbase()=='/' ? '/assets' : appbase()."/assets";
	}
?>
