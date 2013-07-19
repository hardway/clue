<?php
namespace Clue{
	// Constants to indicate how to retrieve data by get_row and get_results
	if(!defined('OBJECT')) define('OBJECT', 'OBJECT');
	if(!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
	if(!defined('ARRAY_N')) define('ARRAY_N', 'ARRAY_N');

	abstract class Database{
		protected static $_cons=array();

		static function create($dbms, $param=null){
            if(is_object($dbms) && isset($dbms->type) && empty($param)){
                $param=$dbms;
                $dbms=$param->type;
            }

			$factory="Clue\\Database\\".$dbms;
			if(!class_exists($factory)) throw new \Exception("Database: $dbms is not implemented!");

			// Make sure the parameter is always in array format
			if(is_object($param)) $param=(array)$param;
			return new $factory($param);
		}

		static function sql_to_create_table($table, $columns){
			foreach($columns as $name=>$def){
				if($name=='_pkey'){
					// Constraints: Primary Key
					if(!is_array($def)) $def=array($def);
					$sql[]='CONSTRAINT PRIMARY KEY ('.implode(",", $def).')';
				}
				else{
					if(!is_array($def)) $def=array('type'=>$def);
					$s=array("`$name`", $def['type']);
					if(isset($def['null'])) $s[]=$def['null'] ? "NULL" : "NOT NULL";
					if(isset($def['default'])) $s[]="DEFAULT ".$def['default'];
					if(isset($def['auto_increment'])) $s[]="AUTO_INCREMENT";
					if(isset($def['pkey']) && $def['pkey']) $s[]="PRIMARY KEY";
					$sql[]=implode(" ", $s);
				}
			}
			return "CREATE TABLE IF NOT EXISTS `$table` (\n".implode(", \n", $sql)."\n);";
		}

		// TODO: refactor profile, use config inject
		static function open($profile='default'){
			if(!isset(self::$_cons[$profile]))
				self::$_cons[$profile]=new Database($profile);
			return self::$_cons[$profile];
		}

		//===========================================================

		protected $setting;

		public $query_count=0;
		public $last_query=null;
		protected $queryLog=null;

		public $dbh=null;
		public $lasterror=null;
		public $errors=null;

		public function enable_query_log(IClue_Log $log){
			$this->queryLog=$log;
		}
		public function disable_query_log(){
			$this->queryLog=null;
		}

		protected function setError($err){
			$this->lasterror=$err;
			$this->errors[]=$err;

			//throw new \Exception("SQL ERROR: {$err['code']} {$err['error']} [$this->last_query]");
            trigger_error("SQL ERROR: {$err['code']} {$err['error']} [$this->last_query]", E_USER_ERROR);
		}

		protected function clearError(){
			$this->lasterror=null;
			$this->errors=null;
		}

		// Basic implementation
		/**
		 * quote means return type is string
		 * so either it's quoted with ', or it should display null
		 */
		function quote($data){
			if(is_null($data) || $data===false)
				return 'null';
			elseif(is_string($data)){
				return "'".addslashes($data)."'";
			}
			else
				return $data;
		}

		function audit($sql, $time=0){
			// TODO: log slow query
			$this->last_query=$sql;
			$this->query_count++;
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

		function insert_id(){
			return null;
		}

		function exec($sql){
			if(func_num_args()>1){
				$sql=call_user_func_array(array($this, "format"), func_get_args());
			}

			$this->last_query=$sql;

			if($this->queryLog){
			    // TODO.
				//$this->queryLog->log($sql);
			}
		}

		function query($sql){
			$this->exec($sql);
		}

		abstract function get_var($sql);
		abstract function get_col($sql);
		abstract function get_row($sql, $mode=OBJECT);
		abstract function get_results($sql, $mode=OBJECT);

		function get_hash(){
			$args=func_get_args();

	        $mode=func_get_arg(func_num_args()-1);
	        if($mode!=OBJECT && $mode!=ARRAY_A){
	            $mode=ARRAY_A;
	        }

		    $hash=($mode==OBJECT) ? new stdClass : array();

		    $sql=call_user_func_array(array($this, 'format'), $args);

		    $rs=$this->get_results($sql, ARRAY_A);

		    foreach($rs as $row){
		        $key_name=array_keys($row)[0];
		        $key=$row[$key_name];

		        unset($row[$key_name]);
		        if(count($row)>1){
		        	$val=array_combine(array_keys($row), array_values($row));
		        	if($mode==OBJECT){
		        		$val=ary2obj($val);
		        	}
		        }
		        else{
		        	$val=array_values($row)[0];
		        }

		        if(is_null($key)) continue;

		        if($mode==OBJECT)
		            $hash->$key=$val;
		        else
		            $hash[$key]=$val;
		    }

		    return $hash;
		}

		function get_object(){
			$args=func_get_args();

			$class=array_pop($args);
			$sql=call_user_func_array(array($this, 'format'), $args);

		    $r=$this->get_row($sql, ARRAY_A);

		    if(empty($r))
		    	return null;
		    else{
		    	$obj=new $class($r);
		    	$obj->_snap_shot();
		    	$obj->after_retrieve();
		    }

		    return empty($r) ? null : new $class($r);
		}

		function get_objects(){
			$args=func_get_args();

			$class=array_pop($args);
			$sql=call_user_func_array(array($this, 'format'), $args);

		    $objs=array();
		    $rs=$this->get_results($sql, ARRAY_A);
		    if($rs) foreach($rs as $r){
		    	$obj=new $class($r);
		    	$obj->_snap_shot();
		    	$obj->after_retrieve();
		        $objs[]=$obj;
		    }

		    return $objs;
		}

		function has_table($table){return false;}
	}
}
?>
