<?php  
	if(!defined('OBJECT')) define('OBJECT', 'OBJECT');
	if(!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
	if(!defined('ARRAY_N')) define('ARRAY_N', 'ARRAY_N');
	
	abstract class Clue_Database{
		protected static $_cons=array();
		protected $setting;
		
		public $dbh;
		
		public $lastquery;
		public $lasterror;
		public $errors;
		
		static function create($dbms, $param){
			$factory='Clue_Database_'.$dbms;
			if(!class_exists($factory)) throw new Exception("Database: $dbms is not implemented!");
			
			return new $factory($param);
		}
		
		static function open($profile='default'){
			if(!isset(self::$_cons[$profile]))
				self::$_cons[$profile]=new Database($profile);
			return self::$_cons[$profile];
		}
		
		function setError($err){
			// TODO: log error when error report is disabled.
			$this->lasterror=$err;
			$this->errors[]=$err;
		}
		
		function clearError(){
			$this->lasterror=null;
			$this->errors=null;
		}
		
		function quote($str){
			return "'".$this->escape($str)."'";
		}
		
		function escape($str){
			return addslashes($str);
		}
		
		function insertId(){
			return 0;
		}
		
		function exec($sql){
			$this->lastquery=$sql;
			// TODO: log sql query statement and results
		}
		
		function query($sql){
			$this->exec($sql);
		}
		
		function get_var($sql){}		
		function get_col($sql){}		
		function get_row($sql, $mode=OBJECT){}		
		function get_results($sql, $mode=OBJECT){}
		
		function has_table($table){return false;}
	}
?>
