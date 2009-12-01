<?php  
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
?>