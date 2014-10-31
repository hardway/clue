<?php
namespace Clue\Database{
	class Mysql extends \Clue\Database{
		protected $_result;

		function __construct(array $param){
			// Make sure mysqli extension is enabled
			if(!extension_loaded('mysqli'))
				throw new \Exception(__CLASS__.": extension mysqli is missing!");

			// Check Parameter, TODO
			// echo "Creating MySQL Connection.\n";
			$this->dbh=mysqli_connect($param['host'], $param['username'], $param['password'], $param['db'], $param['port'] ?: null);

			if(!$this->dbh){
				$this->setError(array('code'=>mysqli_connect_errno(), 'error'=>mysqli_connect_error()));
			}

			// set default client encoding
			if(isset($param['encoding'])){
				$encoding=$param['encoding'];
				$this->exec("set names $encoding");
			}
		}

		function close(){
			$this->free_result();

			if($this->dbh){
				@mysqli_close($this->dbh);
				$this->dbh=null;
			}
		}

		protected function free_result(){
			if(is_object($this->_result)){
				$this->_result->close();
				$this->_result=null;
			}
		}

		function insert_id(){
			return mysqli_insert_id($this->dbh);
		}

		function insert($table, $fields){
			$cols=array();
			$vals=array();
			foreach($fields as $c=>$v){
				$cols[]='`'.trim($c, '`').'`';
				$vals[]=$this->quote($v);
			}
			$sql="insert into `$table`(".implode(',', $cols).") values(".implode(',', $vals).")";
			$this->exec($sql);
			return $this->insert_id();
		}

	    function update($table, $fields, $where){
	        $updates=array();

	        foreach ($fields as $c=>$v) {
	            $updates[]="`$c`=".$this->quote($v);
	        }
	        $sql="
	            update `$table` set ".implode(', ', $updates)."
	            where $where
	        ";

	        return $this->exec($sql);
	    }

	    function remove($table, $where){
	        $sql="
	            delete from `$table` where $where
	        ";
	        $this->exec($sql);
	    }

		function affected_rows(){
		    return mysqli_affected_rows($this->dbh);
		}

	    function format($sql){
	        $args=func_get_args();
	        $me=$this;

	        $idx=1;
	        $sql=preg_replace_callback('/%(t|c|s|d|f|%)/', function($m) use($me, $args, &$idx){
	        	if($m[1]=='%') return '%';

	            if(!array_key_exists($idx, $args)){
	                throw new \Exception("Not enough arguments for SQL statement.");
	            }

	            $var="";
	            switch($m[1]){
	                case 't':   // Table/View
	                case 'c':   // Column
	                    $var="`".$args[$idx]."`";
	                    break;
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

    function exec($sql)
    {
        $this->free_result();

        if(func_num_args()>1){
            $sql=call_user_func_array(array($this, "format"), func_get_args());
        }

        $query_begin=microtime(true);
        $this->_result=mysqli_query($this->dbh, $sql);
        $query_end=microtime(true);

        $this->audit($sql, $query_end - $query_begin);

        if (!$this->_result) {
            $this->setError(
                array(
                    'code'=>mysqli_errno($this->dbh),
                    'error'=>mysqli_error($this->dbh)
                )
            );

            return false;
        }

        // NOTE: should not free result since it might be used in get_var...
        // TODO: caused resouce lock, and leak maybe

        return true;
    }


	    function get_var($sql)
	    {
	        if (!call_user_func_array(array($this, "exec"), func_get_args())) {
	            return false;
	        }

	        $row=mysqli_fetch_row($this->_result);
	        $this->free_result();

	        return empty($row) ? null : $row[0];
	    }

	    function get_row($sql, $mode=OBJECT)
	    {
	        if (!call_user_func_array(array($this, "exec"), func_get_args())) {
	            return false;
	        }

	        $result=false;

	        $mode=func_get_arg(func_num_args()-1);
	        if($mode!=OBJECT && $mode!=ARRAY_A && $mode!=ARRAY_N){
	            $mode=OBJECT;
	        }

	        if ($mode==OBJECT) {
	            $result=mysqli_fetch_object($this->_result);
	        } elseif ($mode==ARRAY_A) {
	            $result=mysqli_fetch_assoc($this->_result);
	        } elseif ($mode==ARRAY_N) {
	            $result=mysqli_fetch_row($this->_result);
	        } else {
	            $result=mysqli_fetch_array($this->_result);
	        }

	        $this->free_result();
	        return $result;
	    }

	    function get_col($sql)
	    {
	        if (!call_user_func_array(array($this, "exec"), func_get_args())) {
	            return false;
	        }

	        $result=array();
	        while ($r=mysqli_fetch_row($this->_result)) {
	            $result[]=$r[0];
	        }

	        $this->free_result();
	        return $result;
	    }

	    function get_results($sql, $mode=OBJECT)
	    {
	        if (!call_user_func_array(array($this, "exec"), func_get_args())) {
	            return false;
	        }

	        $result=array();
	        $mode=func_get_arg(func_num_args()-1);
	        if($mode!=OBJECT && $mode!=ARRAY_A && $mode!=ARRAY_N){
	            $mode=OBJECT;
	        }

	        if ($mode==OBJECT) {
	            while ($r=mysqli_fetch_object($this->_result)) {
	                $result[]=$r;
	            }
	        } elseif ($mode==ARRAY_A) {
	            while ($r=mysqli_fetch_assoc($this->_result)) {
	                $result[]=$r;
	            }
	        } elseif ($mode==ARRAY_N) {
	            while ($r=mysqli_fetch_row($this->_result)) {
	                $result[]=$r;
	            }
	        }

	        $this->free_result();
	        return $result;
	    }

	    # Memeory optimized
	    function foreach_row($sql, $handler, $mode=OBJECT)
	    {
	    	$args=func_get_args();

	    	$mode=array_pop($args);
	        if($mode!=OBJECT && $mode!=ARRAY_A && $mode!=ARRAY_N){
	        	array_push($args, $mode);
	            $mode=OBJECT;
	        }

	    	$handler=array_pop($args);

	        if (!call_user_func_array(array($this, "exec"), $args)) {
	            return false;
	        }

	        $cnt=0;

	        $res=$this->_result; $this->_result=null;
	        while (true) {
	            switch($mode){
	            case OBJECT:
	                $r=mysqli_fetch_object($res);
	                break;
	            case ARRAY_A:
	                $r=mysqli_fetch_assoc($res);
	                break;
	            case ARRAY_N:
	                $r=mysqli_fetch_row($res);
	                break;
	            }

	            if (empty($r)) {
	                break;
	            }

	            $handler($r); $cnt++;
	        }
	        $res->close();

	        return $cnt;
	    }

		function has_table($table){
			$tables=$this->get_col("show tables");
			return in_array($table, $tables);
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
}
?>
