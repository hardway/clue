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
		
		public $lastquery=null;
		protected $queryLog=null;
		
		public $dbh=null;
		public $lasterror=null;
		public $errors=null;

		public function enable_query_log(Clue_Database_Log $log){
			$this->queryLog=$log;
		}
		public function disable_query_log(){
			$this->queryLog=null;
		}
	
		protected function setError($err){
			$this->lasterror=$err;
			$this->errors[]=$err;
			
			throw new Clue_Database_Exception($this->lastquery, $err['code'], $err['error']);
		}
		
		protected function clearError(){
			$this->lasterror=null;
			$this->errors=null;
		}
		
		// Basic implementation
		function quote($data){
			if(is_null($data))
				return 'null';
			elseif(is_string($data)){
				return "'".addslashes($data)."'";
			}
			else
				return $data;
		}
		
		// Basic implementation
		function escape($data){
			if(is_null($data))
				return 'null';
			elseif(is_string($data)){
				return addslashes($data);
			}
			else
				return $data;
		}
		
		function insertId(){
			return 0;
		}
		
		function exec($sql){
			$this->lastquery=$sql;
			
			if($this->queryLog){
				$this->queryLog->log_query($sql);
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
