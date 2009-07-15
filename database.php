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
	}
	
	class Clue_Database_Mysql extends Clue_Database{
		protected $_result;
				
		function __construct(array $param){
			// Make sure mysqli extension is enabled
			if(!extension_loaded('mysqli')) 
				throw new Exception(__CLASS__.": extension mysqli is missing!");
			
			// Check Parameter, TODO
			// echo "Creating MySQL Connection.\n";
			$this->dbh=mysqli_connect($param['host'], $param['username'], $param['password'], $param['db']);
			
			if(!$this->dbh){
				$this->setError(array('code'=>mysqli_connect_errno(), 'error'=>mysqli_connect_error()));
			}
		}
		
		function __destruct(){
			// echo "Closing MySQL Connection.\n";
			$this->free_result();
			
			if($this->dbh){
				mysqli_close($this->dbh);
				$this->dbh=null;
			}
		}
		
		protected function free_result(){
			if(is_object($this->_result)){
				$this->_result->close();
				$this->_result=null;
			}
		}
		
		function insertId(){
			return mysqli_insert_id($this->dbh);
		}
		
		function exec($sql){
			parent::exec($sql);
			
			$this->free_result();
			$this->_result=mysqli_query($this->dbh, $sql);
			
			if(!$this->_result){
				$this->setError(array('code'=>mysqli_errno($this->dbh), 'error'=>mysqli_error($this->dbh)));
				return false;
			}
			
			// NOTE: should not free result since it might be used in get_var...
			return true;
		}
		
		function get_var($sql){
			if(!$this->exec($sql)) return false;
			
			$row=$this->_result->fetch_row();
			$this->free_result();
			
			return $row[0];
		}
		
		function get_row($sql, $mode=OBJECT){
			if(!$this->exec($sql)) return false;
			
			$result=false;
			
			if($mode==OBJECT)
				$result=$this->_result->fetch_object();
			else if($mode==ARRAY_A)
				$result=$this->_result->fetch_assoc();
			else if($mode==ARRAY_N)
				$result=$this->_result->fetch_row();
			else
				$result=$this->_result->fetch_array();
			
			$this->free_result();
			return $result;
		}
		
		function get_col($sql){
			if(!$this->exec($sql)) return false;
			
			$result=array();
			while($r=$this->_result->fetch_row()){
				$result[]=$r[0];
			}
			
			$this->free_result();
			return $result;
		}
		
		function get_results($sql, $mode=OBJECT){
			if(!$this->exec($sql)) return false;
			
			$result=array();
			
			if($mode==OBJECT){
				while($r=$this->_result->fetch_object()){
					$result[]=$r;
				}
			}
			else if($mode==ARRAY_A){
				while($r=$this->_result->fetch_assoc()){
					$result[]=$r;
				}
			}
			else if($mode==ARRAY_N){
				while($r=$this->_result->fetch_row()){
					$result[]=$r;
				}
			}
			
			$this->free_result();
			return $result;
		}
	}
	
	/**
	 * Clue/Database/Oracle
	 * For oracle server that uses RAC/HA, consider enable oci8.event in php.ini
	 */
	class Clue_Database_Oracle extends Clue_Database{
		protected $_stmt;
		
		function __construct(array $param){
			// Make sure oci extension is enabled
			if(!extension_loaded('oci8')) throw new Exception(__CLASS__.": extension OCI8 is missing!");
			
			// Check Parameter, TODO
			
			// echo "Creating Oracle Connection.\n";
			$this->dbh=oci_pconnect($param['username'], $param['password'], $param['db']);
			if(!$this->dbh){
				$this->setError(oci_error());
			}
		}
		
		function __destruct(){
			// echo "Closing Oracle Connection.\n";
			if($this->dbh){
				oci_close($this->dbh);
				$this->dbh=null;
			}
		}
		
		function exec($sql){
			parent::exec($sql);
			
			$this->_stmt=oci_parse($this->dbh, $sql);
			if(!$this->_stmt){
				$this->setError(oci_error($this->dbh));
				return false;
			}
			
			if(!oci_execute($this->_stmt)){
				$this->setError(oci_error($this->dbh));
				return false;
			}
			
			return true;
		}
		
		function get_var($sql){
			if(!$this->exec($sql)) return false;
			
			if(!oci_fetch($this->_stmt)){
				$this->setError(oci_error($this->dbh));
				return false;
			}
			
			return oci_result($this->_stmt, 1);
		}
		
		function get_row($sql, $mode=OBJECT){
			if(!$this->exec($sql)) return false;
			
			if($mode==OBJECT)
				return oci_fetch_object($this->_stmt);
			else if($mode==ARRAY_A)
				return oci_fetch_assoc($this->_stmt);
			else if($mode==ARRAY_N)
				return oci_fetch_row($this->_stmt);
			else
				return oci_fetch_array($this->_stmt);	
		}
		
		function get_col($sql){
			if(!$this->exec($sql)) return false;
			
			oci_fetch_all($this->_stmt, $result, 0, -1, OCI_NUM);
			return $result[0];
		}
		
		function get_results($sql, $mode=OBJECT){
			if(!$this->exec($sql)) return false;
			
			$result=array();
			
			if($mode==OBJECT){
				while($o=oci_fetch_object($this->_stmt)){
					$result[]=$o;
				}
			}
			else if($mode==ARRAY_A)
				oci_fetch_all($this->_stmt, $result, 0, -1, OCI_ASSOC + OCI_FETCHSTATEMENT_BY_ROW);
			else if($mode==ARRAY_N)
				oci_fetch_all($this->_stmt, $result, 0, -1, OCI_NUM + OCI_FETCHSTATEMENT_BY_ROW);
			else
				oci_fetch_all($this->_stmt, $result);
			
			return $result;
		}
	}
?>
