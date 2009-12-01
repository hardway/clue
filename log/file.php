<?php 
	require_once 'clue/log.php';
	
	class Clue_Log_File implements IClue_Log{
		protected $logfile;
		
		function __construct($filename, $mode='a'){
			// force delete if mode is 'w'
			if($mode=='w' && file_exists($filename)){
				unlink($filename);
			}
			
			// make sure file is created.
			if(!file_exists($filename)){
				// Make folder exists
				$dir=dirname($filename);
				$dir_missing=array();
				
				while(strlen($dir)>0 && !is_dir($dir)){
					$dir_missing[]=$dir;
					$dir=dirname($dir);
				}
				
				foreach(array_reverse($dir_missing) as $dir){
					mkdir($dir);
				}
			}
			
			$this->logfile=fopen($filename, "a");
		}
		
		function __destruct(){
			fclose($this->logfile);
			$this->logfile=null;
		}
		
		function log($message, $level=self::NOTICE){
			fprintf($this->logfile, "%s [%s] %s\r\n", date('c', time()), $level, $message);
		}
	}
?>