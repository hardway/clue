<?php  
	/**
	 * Clue/Database/Microsoft SQL Server
	 * Need to trap sql server error
	 *
	 * TODO: not tested
	 */
	class Clue_Database_MSSql extends Clue_Database{
		protected $_stmt;
		
		function __construct(array $param){
			// Make sure oci extension is enabled
			if(!extension_loaded('mssql')) throw new Exception(__CLASS__.": extension MSSQL is missing!");
			
			// Check Parameter, TODO
			
			$this->dbh=mssql_pconnect($param['host'], $param['username'], $param['password']);
			if(!$this->dbh){
				$this->setError("Can't connect to sql server.");
			}
			mssql_select_db($param['db']);
		}
		
		function __destruct(){
			$this->free_result();
						
			if($this->dbh){
				mssql_close($this->dbh);
				$this->dbh=null;
			}
		}
		
		function free_result(){
			if($this->_stmt){
				mssql_free_result($this->_stmt);
				$this->_stmt=null;
			}	
		}
		
		function exec($sql){
			parent::exec($sql);
			
			$this->free_result();
			$this->_stmt=mssql_query($sql, $this->dbh);
			if(!$this->_stmt){
				$this->setError("Error in sql");
				return false;
			}
			
			return true;
		}
		
		function get_var($sql){
			if(!$this->exec($sql)) return false;
			
			$row=mssql_fetch_row($this->_stmt);
			return $row[0];
		}
		
		function get_row($sql, $mode=OBJECT){
			if(!$this->exec($sql)) return false;
			
			if($mode==OBJECT)
				return mssql_fetch_object($this->_stmt);
			else if($mode==ARRAY_A)
				return mssql_fetch_assoc($this->_stmt);
			else if($mode==ARRAY_N)
				return mssql_fetch_row($this->_stmt);
			else
				return mssql_fetch_row($this->_stmt);	
		}
		
		function get_col($sql){
			if(!$this->exec($sql)) return false;
			
			$result=array();
			while($a=mssql_fetch_row($this->_stmt)){
				$result[]=$a[0];
			}
			
			return $result;
		}
		
		function get_results($sql, $mode=OBJECT){
			if(!$this->exec($sql)) return false;
			
			$result=array();
			
			if($mode==OBJECT){
				while($o=mssql_fetch_object($this->_stmt)){
					$result[]=$o;
				}
			}
			else if($mode==ARRAY_A)
				$result=mssql_fetch_array($this->_stmt, MSSQL_ASSOC);
			else if($mode==ARRAY_N)
				$result=mssql_fetch_array($this->_stmt, MSSQL_NUM);
			else
				$result=mssql_fetch_array($this->_stmt);
			
			return $result;
		}
		
		function has_table($table){
			$table=strtoupper($table);
			$cnt=$this->get_var("select count(*) from user_tables where table_name='$table'");
			return $cnt==1;
		}
	}
?>