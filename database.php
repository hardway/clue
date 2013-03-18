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
		
		public $lastquery=null;
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
		
		function format($sql){
			$args=func_get_args();
			$me=$this;

			$idx=1;
			$sql=preg_replace_callback('/%(s|d|f|%)/', function($m) use($me, $args, &$idx){
				if(!isset($args[$idx])){
					throw new \Exception("Not enough arguments for SQL statement.");
				}

				$var="";
				switch($m[1]){
					case 's':
						$var=$me->quote($args[$idx]);
						break;
					case 'd':
						$var=intval($args[$idx]);
						break;
					case 'f':
						$var=floatval($args[$idx]);
						break;
				}

				$idx++;
				return $var;
			}, $sql);
			return $sql;
		}

		function exec($sql){
			if(func_num_args()>1){
				$sql=call_user_func_array(array($this, "format"), func_get_args());
			}

			$this->lastquery=$sql;
			
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
		
		function get_hash($sql, $mode=ARRAY_A){
		    $hash=($mode==OBJECT) ? new stdClass : array();
		    $rs=$this->get_results($sql, ARRAY_N);
		    foreach($rs as $row){
		        $key=$row[0];
		        $val=$row[1];
		        
		        if(is_null($key)) continue;
		        
		        if($mode==OBJECT)
		            $hash->$key=$val;
		        else
		            $hash[$key]=$val;
		    }
		    return $hash;
		}
		
		function get_object($sql, $class){
		    $r=$this->get_row($sql, ARRAY_A);
		    return empty($r) ? null : new $class($r);
		}
		
		function get_objects($sql, $class){
		    $objs=array();
		    $rs=$this->get_results($sql, ARRAY_A);
		    if($rs) foreach($rs as $r){
		        $objs[]=new $class($r);
		    }
		    
		    return $objs;
		}
		
		function has_table($table){return false;}
	}
}
?>
