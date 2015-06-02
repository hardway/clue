<?php
namespace Clue{
	// Constants to indicate how to retrieve data by get_row and get_results
	if(!defined('OBJECT')) define('OBJECT', 'OBJECT');
	if(!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
	if(!defined('ARRAY_N')) define('ARRAY_N', 'ARRAY_N');

	abstract class Database{
		use \Clue\Traits\Logger;

		protected static $_cons=array();

		static function create(array $param){
			$dbms=$param['type'];

			$factory="Clue\\Database\\".$dbms;
			if(!class_exists($factory)) throw new \Exception("Database: $dbms is not implemented!");

			// Make sure the parameter is always in array format
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

		public $slow_query_time_limit=0;

		public $dbh=null;
		public $last_error=null;
		public $errors=null;

		public function enable_slow_query_log($logfile='', $time_limit=10){
			$this->enable_log($logfile);
			$this->slow_query_time_limit=$time_limit;
		}

		protected function setError($err){
			$this->last_error=$err;
			$this->errors[]=$err;

			throw new \Exception("SQL ERROR: {$err['code']} {$err['error']} [$this->last_query]");
            //trigger_error("SQL ERROR: {$err['code']} {$err['error']} [$this->last_query]", E_USER_ERROR);
		}

		protected function clearError(){
			$this->last_error=null;
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
			// $this->log("[SQL ".number_format($time, 4)."] $sql");

			if($this->slow_query_time_limit>0 && $time>$this->slow_query_time_limit){
				$this->log("[SLOW QUERY ".number_format($time, 4)."] $sql", ['backtrace'=>'full']);
			}

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

		    $hash=($mode==OBJECT) ? new \stdClass : array();

		    array_push($args, ARRAY_A);
		    $rs=call_user_func_array(array($this, 'get_results'), $args);

		    foreach($rs as $row){
		    	$keys=array_keys($row);
		        $key_name=$keys[0];
		        $key=$row[$key_name];

		        unset($row[$key_name]);
		        if(count($row)>1){
		        	$val=array_combine(array_keys($row), array_values($row));
		        	if($mode==OBJECT){
		        		$val=ary2obj($val);
		        	}
		        }
		        else{
		        	$vals=array_values($row);
		        	$val=$vals[0];
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
			array_push($args, ARRAY_A);
			$r=call_user_func_array(array($this, 'get_row'), $args);

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
			array_push($args, ARRAY_A);

		    $objs=array();
			$rs=call_user_func_array(array($this, 'get_results'), $args);
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
