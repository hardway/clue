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
			set_error_handler(array($this, 'handleError'));	// Catch all
			
			set_exception_handler(array($this, 'handleException'));
		}
		
		public function stop(){
			restore_error_handler();
			restore_exception_handler();
		}
		
		public function mute(){ $this->mute=true; }
		public function unmute(){ $this->mute=false; }
		
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
				
				$context=array();
				$context['trace']=$this->_flatten_trace($trace);
				$context['url']=$_SERVER['REQUEST_URI'];
				if(count($trace)>0){
					$context['file']=$trace[0]['file'];
					$context['line']=$trace[0]['line'];
				}
				
				$this->log->log($this->app, $message, $level, $context);					
			}
			$this->osd($message);
		}
				
		public function handleError($errno, $errstr, $errfile=null, $errline=null, array $errcontext=null){
			if($this->log instanceof IClue_Log){
				$tracing=debug_backtrace();
				$trace=array();
				
				foreach($tracing as $stack){
					$file=isset($stack['file']) ? $stack['file'] : "";
					$line=isset($stack['line']) ? $stack['line'] : "";
					$trace[]="$file:$line";
				}
				
				$context=array(
					'code'=>$errno,
					'file'=>$errfile,
					'line'=>$errline,
					'trace'=>implode("\n", $trace),
					'url'=>$_SERVER['REQUEST_URI']
				);
				
				switch($errno){
					case E_NOTICE:
					case E_USER_NOTICE:
					case E_STRICT:
					case 8192 /* E_DEPRECATED - PHP5.3 */:
					case 16384 /* E_USER_DEPRECATED - PHP5.3 */:
						$this->log->log_notice($this->app, $errstr, $context);
						break;
						
					case E_WARNING:
					case E_CORE_WARNING:
					case E_COMPILE_WARNING:
					case E_USER_WARNING:
						$this->log->log_warning($this->app, $errstr, $context);
						break;
						
					case E_ERROR:
					case E_PARSE:
					case E_CORE_ERROR:
					case E_COMPILE_ERROR:
					case E_USER_ERROR:
					default:
						$this->log->log_error($this->app, $errstr, $errno, $context);
				}
			}
			$this->osd($errstr);
		}
			
		public function handleException($e){
			if($this->log instanceof IClue_Log){
				$context=array(
					'url'=>$_SERVER['REQUEST_URI']
				);				
				$this->log->log_exception($this->app, $e, $context);
			}
			$this->osd($e->getMessage());
		}
		
		//===============================================================
		private function _flatten_trace($trace){
			$t=array();
			foreach($trace as $stack){
				$file=isset($stack['file']) ? $stack['file'] : "";
				$line=isset($stack['line']) ? $stack['line'] : "";
				$t[]="$file:$line";
			}
			
			return implode('\n', $t);
		}
	}
?>