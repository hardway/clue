<?php  
	require_once 'clue/log.php';
	
	// Shortcut
	function guard(){ return Clue_Guard::getDefault(); }
	
	/**
	* Acts as Security Guard
	* 
	*  Looks around for all kinds of error and exception.
	*  Can accept arbitary log request, dump request or backtracerequest.
	* 
	*  Have different policy for guarding, such as stop on any error 
	*  or keep working but log every error or just ignore anything.
	**/
		
	class Clue_Guard{
		public static $default_guard=null;
		
		public static function init($log=null){
			$guard=self::getDefault();
			
			$guard->setLogger(empty($log) ? new Clue_Log() : $log);
			$guard->start();
		}
		
		public static function setDefault(Clue_Guard $guard){
			self::$default_guard=$guard;
		}
		
		public static function getDefault(){
			if(self::$default_guard==null){
				self::$default_guard=new Clue_Guard();
			}
			
			return self::$default_guard;
		}
		//--------------------------------------------------------
		
		protected $log=null;
		
		public function __construct(/* TODO: options */){
		}
		
		public function start(){
			set_error_handler(array($this, 'handleError'));
			set_exception_handler(array($this, 'handleException'));
		}
		
		public function stop(){
			restore_error_handler();
			restore_exception_handler();
		}
		
		public function setLogger($log){
			$this->log=$log;
		}
		
		public function log($message, $level=Clue_Guard::NOTICE){
			$this->log->log($message, $level);
		}
		
		public function dump(/*anything*/){
			var_dump(func_get_args());
		}
		
		public function backtrace(){
			debug_print_backtrace();
		}
		
		public function handleError($errno, $errstr, $errfile=null, $errline=null, array $errcontext=null){
			$message="($errno) $errstr";
			if(!empty($errfile)) $message.="@$errfile";
			if(!empty($errline)) $message.=":$errline";
			
			$this->log($message, IClue_Log::ERROR);
		}
		
		public function handleException($e){
			$message="Exception(".$e->getCode()."): ".$e->getMessage()." @".$e->getFile().":".$e->getLine();
			
			$this->log($message, IClue_Log::ERROR);
		}
	}
?>