<?php  
	require_once 'clue/core.php';
	
	interface IClue_Log{
		const EXCEPTION="EXCEPTION";
		const ERROR="ERROR";
		const WARNING="WARNING";
		const NOTICE="NOTICE";
		const DEBUG="DEBUG";
		
		function log($app, $message, $level=self::NOTICE, $file=null, $line=null, $url=null);
		function log_error($app, $message, $code=0, $file=null, $line=null, $url=null);
		function log_exception($app, $exception, $url=null);
	}
	
	// Stubborn logger, dumps everything to standard output
	class Clue_Log implements IClue_Log{
		function log($app, $message, $level=self::NOTICE, $file=null, $line=null, $url=null){
			echo "[$level]\t$message\n";
		}
		
		function log_error($app, $message, $code=0, $file=null, $line=null, $url=null){
			echo "[".self::ERROR."]\t$message($code) @$file:$line\n";
		}
		
		function log_exception($app, $exception, $url=null){
			printf("[%s]\t(%s)%s(%d) @%s:%d\n",
				self::EXCEPTION, get_class($exception), $exception->getMessage(), 
				$exception->getCode(), $exception->getFile(), $exception()->getLine()
			);
		}
	}
?>
