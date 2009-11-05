<?php  
	require_once 'clue/core.php';
	
	interface IClue_Log{
		const ERROR="ERROR";
		const WARNING="WARNING";
		const NOTICE="NOTICE";
		const DEBUG="DEBUG";
		
		function log($message, $level=self::NOTICE);
	}
	
	// Stubborn logger, dumps everything to standard output
	class Clue_Log implements IClue_Log{
		function log($message, $level=self::NOTICE){
			echo "[$level]\t$message\n";
		}
	}
	
	class Clue_Log_File implements IClue_Log{
		protected $logfile;
		
		function __construct($filename){
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
			fprintf($this->logfile, "%s\t[%s]\t%s\n", date('c', time()), $level, $message);
		}
	}
?>
