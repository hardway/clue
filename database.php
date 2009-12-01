<?php	
	require_once 'clue/core.php';
	
	// Constants to indicate how to retrieve data by get_row and get_results
	if(!defined('OBJECT')) define('OBJECT', 'OBJECT');
	if(!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
	if(!defined('ARRAY_N')) define('ARRAY_N', 'ARRAY_N');
	
	abstract class Clue_Database{
		protected static $_cons=array();
		
		static function create($dbms, $param){
			$factory='Clue_Database_'.$dbms;
			if(!class_exists($factory)) throw new Exception("Database: $dbms is not implemented!");
			
			return new $factory($param);
		}
		
		// TODO: refactor profile, use config inject
		static function open($profile='default'){
			if(!isset(self::$_cons[$profile]))
				self::$_cons[$profile]=new Database($profile);
			return self::$_cons[$profile];
		}
		
		//===========================================================
		
		protected $setting;
		
		protected $errorLog=null;
		protected $queryLog=null;
		
		public $dbh=null;
		public $lastquery=null;
		public $lasterror=null;
		public $errors=null;

		public function enable_error_log(IClue_Log $log){
			$this->errorLog=$log;
		}
		public function disable_error_log(){
			$this->errorLog=null;
		}
		public function enable_query_log(IClue_Log $log){
			$this->queryLog=$log;
		}
		public function disable_query_log(){
			$this->queryLog=null;
		}
	
		protected function setError($err){
			if($this->errorLog){
				$this->errorLog->log("({$err['code']}) {$err['error']}", 'ERROR');
				$this->errorLog->log($this->lastquery, 'QUERY');
			}
			
			$this->lasterror=$err;
			$this->errors[]=$err;
			// TODO: Throw error, let guard handle that.
			// PREREQ: make sure I can switch back to any version of clue library easily.
		}
		
		protected function clearError(){
			$this->lasterror=null;
			$this->errors=null;
		}
		
		// Basic implementation
		function quote($str){
			return "'".$this->escape($str)."'";
		}
		
		// Basic implementation
		function escape($str){
			return addslashes($str);
		}
		
		function insertId(){
			return 0;
		}
		
		function exec($sql){
			$this->lastquery=$sql;
			
			if($this->queryLog){
				$this->queryLog->log($sql, 'QUERY');
			}
		}
		
		function query($sql){
			$this->exec($sql);
		}
		
		abstract function get_var($sql);
		abstract function get_col($sql);
		abstract function get_row($sql, $mode=OBJECT);	
		abstract function get_results($sql, $mode=OBJECT);
		
		function has_table($table){return false;}
	}
?>
