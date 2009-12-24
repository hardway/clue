<?php  
	require_once 'clue/log.php';
	
	// Shortcut
	function guard(){ return Clue_Guard::get_default(); }
	
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
				
		public static function set_default(Clue_Guard $guard){
			self::$default_guard=$guard;
		}
		
		public static function get_default(){
			if(self::$default_guard==null){
				self::$default_guard=new Clue_Guard();
			}
			
			return self::$default_guard;
		}
		//--------------------------------------------------------
		
		protected $log=null;
		protected $app=null;
		protected $mute=true;
		
		public function __construct($app=null, $log=null){
			$this->app=$app;
			$this->log=$log;
			
			$this->start();
		}
		
		public function start(){
			set_error_handler(array($this, 'handleError'));
			set_exception_handler(array($this, 'handleException'));
		}
		
		public function stop(){
			restore_error_handler();
			restore_exception_handler();
		}
		
		public function mute(){
			$this->mute=true;
		}
		
		public function osd($message){
			// TODO: use OSD Logger, or other solution
			if(!$this->mute)
				echo $message."<br/>\n";
		}
		
		public function dump(/*anything*/){
			var_dump(func_get_args());
		}
		
		public function backtrace(){
			debug_print_backtrace();
		}
		
		public function log($message, $level=IClue_Log::NOTICE){
			if($this->log instanceof IClue_Log){
				$trace=debug_backtrace();
				if(count($trace)>0)
					$this->log->log($this->app, $message, $level, $trace[0]['file'], $trace[0]['line']);
				else
					$this->log->log($this->app, $message, $level);
			}
			$this->osd($message);
		}
		
		public function handleError($errno, $errstr, $errfile=null, $errline=null, array $errcontext=null){
			if($this->log instanceof IClue_Log){
				$tracing=debug_backtrace();
				$trace=array();
				foreach($tracing as $stack){
					$trace[]=$stack['file'].':'.$stack['line'];
				}
				$this->log->log_error($this->app, $errstr, $errno, $errfile, $errline, implode("\n", $trace));
			}
			$this->osd($errstr);
		}
		
		public function handleException($e){
			if($this->log instanceof IClue_Log){
				$this->log->log_exception($this->app, $e);
			}
			$this->osd($e->getMessage());
		}
	}
?>