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
			
			// set default client encoding
			if(isset($param['encoding'])){
				$encoding=$param['encoding'];
				$this->exec("set names $encoding");
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
		
		function has_table($table){
			$table=strtolower($table);
			$tables=$this->get_col("show tables");
			return in_array($table, $tables);
		}

		function map_ddl_type($type, $length, $precision){
			switch($type){
				case "char":
					return "char($length)";
				case "varchar2":
					return "varchar($length)";
				case "datetime":
					return "datetime";
				case "timestamp":
					return "timestamp";
				case "number":
					if(empty($precision))
						return "int";
					else
						return $precision > 10 ? "bigint($precision)" : "int($precision)";
				default:
					throw new Exception("Don't know how to map this ddl type: ($type, $length, $precision)");
			}
		}
		
		function DDL_CREATE($schema){
			$cols=array();
			foreach($schema['column'] as $c){
				$type=$this->map_ddl_type($c["type"], $c["length"], $c["precision"]);
				$nul=$c['nullable'] ? "" : " not null";
				$default=empty($c['default']) ? "" : " default {$c['default']}";
				$cols[]="`{$c["name"]}` ".$type.$nul.$default;
			}
			$sql="create {$schema['type']} {$schema['name']}(\n";
			$sql.=implode(", \n", $cols)."\n";
			if(count($schema['pkey'])>0){
				$sql.=", primary key(".implode(',', $schema['pkey']).")\n";
			}
			$sql.=")";
			
			return $sql;
		}
		
		private function get_schema_field_type($text){
			if(($p=strpos($text, '('))>0)
				return substr($text, 0, $p);
			else
				return $text;
		}
		
		private function get_schema_field_length($text){
			if(($p=strpos($text, '('))>0)
				return intval(substr($text, $p+1, strpos($text, ')')-$p));
			else
				return 0;			
		}
		
		function get_schema($table){	
			$schema=array(
				'type'=>'table',
				'name'=>$table,
				'column'=>array(),	// array style
				'col'=>array(),	// hash map style
				'pkey'=>array()
			);
			
			$cols=$this->get_results("desc $table");
			$idx=0;
			foreach($cols as $c){
				$column=array(
					"name"=>$c->Field,
					"type"=>$this->get_schema_field_type($c->Type),
					"default"=>$c->Default,
					"length"=>$this->get_schema_field_length($c->Type),
					"precision"=>null,
					"nullable"=> strtoupper($c->Null)=='YES',
					"idx"=>$idx
				);
				$schema['column'][]=$column;
				$schema['col'][$c->Field]=$column;
				
				if(strtoupper($c->Key)=='PRI')
					$schema['pkey'][]=$c->Field;
			}
			
			return $schema;
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
		
		function has_table($table){
			$table=strtoupper($table);
			$cnt=$this->get_var("select count(*) from user_tables where table_name='$table'");
			return $cnt==1;
		}
		
		protected function map_datatype_to_sql92($type){
			$type=strtolower($type);
			switch($type){
				case "date": 
					return "datetime";
				default: 
					return $type;
			}
		}
		
		function get_schema($table){
			$table=strtoupper($table);	// Oracle table names are always upper case.
			
			$schema=array(
				'type'=>'table',
				'name'=>$table,
				'column'=>array(),	// array style
				'col'=>array(),	// hash map style
				'pkey'=>array()
			);
			
			$cols=$this->get_results("
				select 	column_id, column_name, data_type, data_default, 
						data_length, data_precision, nullable
				from user_tab_cols where table_name='$table'
				order by column_id
			");
			
			foreach($cols as $c){
				$column=array(
					"idx"=>$c->COLUMN_ID - 1,
					"name"=>$c->COLUMN_NAME,
					"type"=>$this->map_datatype_to_sql92($c->DATA_TYPE),
					"default"=>$c->DATA_DEFAULT,
					"length"=>$c->DATA_LENGTH,
					"precision"=>$c->DATA_PRECISION,
					"nullable"=> $c->NULLABLE=='Y'
				);
				$schema['column'][]=$column;
				$schema['col'][$c->COLUMN_NAME]=$column;
			}
			
			$schema['pkey']=$this->get_col("
				select column_name from user_cons_columns c 
					join user_constraints t on c.table_name=t.table_name and c.constraint_name=t.constraint_name 
				where t.constraint_type='P' and t.table_name='$table'
				order by position
			");
			
			return $schema;
		}
	}
	
	class Clue_Database_Sqlite extends Clue_Database{
		protected $_result;
				
		function __construct(array $param){
			// Make sure mysqli extension is enabled
			if(!extension_loaded('sqlite')) 
				throw new Exception(__CLASS__.": extension sqlite is missing!");
			
			// Check Parameter, TODO: access mode
			$this->dbh=sqlite_popen($param['db'], 0666, $sqlite_open_err);
			
			if(!$this->dbh){
				$this->setError(array('error'=>$sqlite_open_err));
			}
		}
		
		function __destruct(){
			$this->free_result();
			if($this->dbh){
				sqlite_close($this->dbh);
				$this->dbh=null;
			}
		}
		
		protected function free_result(){
			if(is_object($this->_result)){
				$this->_result->close();
			}
			$this->_result=null;
		}
		
		function insertId(){
			return sqlite_last_insert_rowid($this->dbh);
		}
		
		function has_table($table){
			$cnt=$this->get_var("select count(*) from sqlite_master where type='table' and tbl_name='$table'");
			return $cnt==1;
		}
		
		function exec($sql){
			parent::exec($sql);
			
			$result_type=false;
			
			$this->free_result();
			$this->_result=sqlite_query($this->dbh, $sql, $result_type, $error);
			
			if(!$this->_result){
				$this->setError(array('error'=>$error));
				return false;
			}
			
			// NOTE: should not free result since it might be used in get_var...
			return true;
		}
		
		function get_var($sql){
			if(!$this->exec($sql)) return false;
			
			$row=sqlite_fetch_single($this->_result);
			$this->free_result();
			
			return $row;
		}
		
		function get_row($sql, $mode=OBJECT){
			if(!$this->exec($sql)) return false;
			
			$result=false;
			
			if($mode==OBJECT)
				$result=sqlite_fetch_object($this->_result);
			else if($mode==ARRAY_A)
				$result=sqlite_fetch_array($this->_result, SQLITE_ASSOC);
			else if($mode==ARRAY_N)
				$result=sqlite_fetch_array($this->_result, SQLITE_NUM);
			else
				$result=sqlite_fetch_array($this->_result);
			
			$this->free_result();
			return $result;
		}
		
		function get_col($sql){
			if(!$this->exec($sql)) return false;
			
			$result=array();
			while($r=sqlite_fetch_single($this->_result)){
				$result[]=$r;
			}
			
			$this->free_result();
			return $result;
		}
		
		function get_results($sql, $mode=OBJECT){
			if(!$this->exec($sql)) return false;
			
			$result=array();
			
			if($mode==OBJECT){
				while($r=sqlite_fetch_object($this->_result)){
					$result[]=$r;
				}
			}
			else if($mode==ARRAY_A){
				while($r=sqlite_fetch_array($this->_result, SQLITE_ASSOC)){
					$result[]=$r;
				}
			}
			else if($mode==ARRAY_N){
				while($r=sqlite_fetch_array($this->_result, SQLITE_NUM)){
					$result[]=$r;
				}
			}
			
			$this->free_result();
			return $result;
		}
	}
	
	class Clue_Database_PDO extends Clue_Database{
		protected $_result;
				
		function __construct(array $param){
			// Make sure mysqli extension is enabled
			if(!extension_loaded('pdo')) 
				throw new Exception(__CLASS__.": extension PDO is missing!");
			
			// Check Parameter, TODO: access mode
			try{
				$this->dbh=new PDO($param['dsn'], $param['username'], $param['password']);
			}
			catch(PDOException $e){
				$this->setError(array('error'=>$e->getMessage()));
			}
		}
		
		function __destruct(){
			$this->free_result();
			if($this->dbh){
				$this->dbh=null;
			}
		}
		
		protected function free_result(){
			if(is_object($this->_result)){
				$this->_result->closeCursor();
			}
			$this->_result=null;
		}
		
		function insertId(){
			return $this->dbh->lastInsertId();
		}
				
		function exec($sql){
			parent::exec($sql);

			return $this->dbh->exec($sql);
		}
		
		function query($sql){
			parent::query($sql);
			
			$this->_result=$this->dbh->query($sql);
			return true;
		}
		
		function get_var($sql){
			if(!$this->query($sql)) return false;
			
			$row=$this->_result->fetchColumn();
			$this->free_result();
			
			return $row;
		}
		
		function get_row($sql, $mode=OBJECT){
			if(!$this->query($sql)) return false;
			
			$result=false;
			
			if($mode==OBJECT)
				$result=$this->_result->fetchObject();
			else if($mode==ARRAY_A)
				$result=$this->_result->fetch(PDO::FETCH_ASSOC);
			else if($mode==ARRAY_N)
				$result=$this->_result->fetch(PDO::FETCH_NUM);
			else
				$result=$this->_result->fetch(PDO::FETCH_NUM);
			
			$this->free_result();
			return $result;
		}
		
		function get_col($sql){
			if(!$this->query($sql)) return false;
			
			$result=array();
			while($r=$this->_result->fetchColumn()){
				$result[]=$r;
			}
			
			$this->free_result();
			return $result;
		}
		
		function get_results($sql, $mode=OBJECT){
			if(!$this->query($sql)) return false;
			
			$result=array();
			
			if($mode==OBJECT){
				while($r=$this->_result->fetchObject()){
					$result[]=$r;
				}
			}
			else if($mode==ARRAY_A){
				while($r=$this->_result->fetch(PDO::FETCH_ASSOC)){
					$result[]=$r;
				}
			}
			else if($mode==ARRAY_N){
				while($r=$this->_result->fetch(PDO::FETCH_NUM)){
					$result[]=$r;
				}
			}
			
			$this->free_result();
			return $result;
		}
	}
?>
