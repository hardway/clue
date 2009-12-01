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
?>
