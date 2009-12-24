<?php  
	class Clue_Database_Exception extends Exception{
		protected $sql;
		
		function __construct($sql, $code, $message){
			$this->sql=$sql;
			$this->code=$code;
			$this->message=$message;
			
			// detect file line
			$trace=$this->getTrace();
			$this->file=$trace[1]['file'];
			$this->line=$trace[1]['line'];
		}
		
		function getQuery(){
			return $this->sql;
		}
	}
?>
