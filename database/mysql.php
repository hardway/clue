<?php  
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
				case "varbinary":
					return "varbinary($length)";
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
?>