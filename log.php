<?php  
	require_once 'clue/core.php';
	
	interface IClue_Log{
		const EXCEPTION="EXCEPTION";
		const ERROR="ERROR";
		const WARNING="WARNING";
		const NOTICE="NOTICE";
		const DEBUG="DEBUG";
		
		function log($app, $message, $level=self::NOTICE, $context=array());
		function log_error($app, $message, $code=0, $context=array());
		function log_warning($app, $message, $context=array());
		function log_notice($app, $message, $context=array());
		function log_debug($app, $message, $context=array());
		function log_exception($app, Exception $exception, $context=array());
	}
	
	// Stubborn logger, dumps everything to standard output
	class Clue_Log implements IClue_Log{
		function log($app, $message, $level=self::NOTICE, $context=array()){
			echo "[$level]\t$message\n";
		}
		
		function log_notice($app, $message, $context=array()){
			$this->log($app, $message, self::NOTICE, $context);
		}
		
		function log_debug($app, $message, $context=array()){
			$this->log($app, $message, self::NOTICE, $context);
		}
		
		
		function log_warning($app, $message, $context=array()){
			$file=@$context['file'];
			$line=@$context['line'];
			echo "[".self::WARNING."]\t$message @$file:$line\n";
		}
		
		function log_error($app, $message, $code=0, $context=array()){
			$file=@$context['file'];
			$line=@$context['line'];
			echo "[".self::ERROR."]\t$message @$file:$line\n";
		}
		
		function log_exception($app, Exception $exception, $context=array()){
			printf("[%s]\t(%s)%s(%d) @%s:%d\n",
				self::EXCEPTION, get_class($exception), $exception->getMessage(), 
				$exception->getCode(), $exception->getFile(), $exception()->getLine()
			);
		}
	}
?>
