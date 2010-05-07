<?php  
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
		
		function insert_id(){
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
?>