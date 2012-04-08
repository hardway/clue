<?php  
namespace Clue{
	class Clue_Log{
	    static $logDir;
	    
	    static function set_log_dir($dir){
	        if(!is_dir($dir)) mkdir($dir);
	        
	        $dir=$dir.DIRECTORY_SEPARATOR.date("Ymd");
	        if(!is_dir($dir)) mkdir($dir);      
	        
	        self::$logDir=$dir;
	    }
	    
	    static function write_log($err){	        
	        $path=self::$logDir . DIRECTORY_SEPARATOR. date("His") . '.log';

	        $context=array(
	            'ERROR'=>$err,
	            'GET'=>$_GET,
	            'POST'=>$_POST,
	            'SERVER'=>$_SERVER,
	            'COOKIE'=>$_COOKIE,
	            'SESSION'=>$_SESSION
	        );
	        
	        $log=fopen($path, 'a');
	        fputs($log, serialize($context));
	        fputs($log, "\r\n");
	        
	        fclose($log);
	    }
	    
	    static function display_error(){
	        header('HTTP/1.0 500 SERVER ERROR');
	        echo "<div style='padding: 10px; text-align: center; background: #A00; color: #FFF; font: bold 14px/1 consolas, sans-serif'>ERROR.</div>";	        
	    }
	    
	    static function log_error($errno, $errstr, $errfile=null, $errline=null){
	        self::write_log(array(
	            'code'=>$errno,
	            'error'=>$errstr,
	            'file'=>$errfile,
	            'line'=>$errline,
	            'trace'=>debug_backtrace(),
	        ));
	    }
	    
	    static function log_exception($e){
	        self::write_log($e);
	        self::display_error();
	    }
	}
}
?>
