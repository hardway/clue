<?php
namespace Clue\Database{
	class Sqlite extends \Clue\Database{
		protected $_result;

        /**
         * @param $param
         *          readonly 是否只读打开，不会锁死数据
         */
		function __construct(array $param){
			// Make sure mysqli extension is enabled
			if(!extension_loaded('sqlite3'))
				throw new \Exception(__CLASS__.": extension sqlite3 is missing!");

			// SQLite3 构造失败会抛异常，无需额外检查
			$this->dbh=new \SQLite3($param['db'], @$param['readonly'] ? SQLITE3_OPEN_READONLY : SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);

            $this->dbh->createFunction('regexp', function($pattern, $string) {
                if(preg_match('/'.$pattern.'/i', $string)) {
                    return true;
                }
                return false;
            }, 2);
		}

		function __destruct(){
			$this->free_result();
			if($this->dbh){
				$this->dbh->close();
				$this->dbh=null;
			}
		}

		protected function free_result(){
			if(is_object($this->_result)){
				$this->_result->finalize();
			}
			$this->_result=null;
		}

        function quote($data){
            if(is_null($data) || $data===false) return 'null';
            return "'".$this->escape($data)."'";
        }

        function escape($data){
            if(is_null($data) || $data===false) return 'null';
            return \SQLite3::escapeString((string)$data);
        }

		function insert_id(){
			return $this->dbh->lastInsertRowID();
		}

        function replace($table, $fields){
            return $this->insert($table, $fields, 'insert or replace');
        }

        function insert_ignore($table, $fields){
            return $this->insert($table, $fields, 'insert or ignore');
        }

        function insert($table, $fields, $verb='insert'){
            $cols=[];
            $vals=[];
            $table='`'.trim($table, '`').'`';
            foreach($fields as $c=>$v){
                $cols[]='`'.trim($c, '`').'`';
                $vals[]=":$c";
            }
            $sql="$verb into $table(".implode(',', $cols).") values(".implode(',', $vals).")";

            $stmt=$this->dbh->prepare($sql);
            foreach($fields as $c=>$v){
	            $stmt->bindValue(":$c", $v);
	        }

	        $this->_result=$stmt->execute();

            return $this->dbh->lastErrorCode() ? null : $this->dbh->lastInsertRowID();
        }

        function update($table, $fields, $where){
            $updates=[];
            $table='`'.trim($table, '`').'`';

            foreach ($fields as $c=>$v) {
                $updates[]="`$c`=:$c";
            }

            $stmt=$this->dbh->prepare("update $table set ".implode(', ', $updates)." where $where");
            foreach($fields as $c=>$v){
	            $stmt->bindValue(":$c", $v);
	        }

	        $this->_result=$stmt->execute();

            return $this->dbh->changes();
        }

		function iterate_results($sql, $mode=OBJECT){
            // 解析参数，支持 format 风格
            $args=func_get_args();
            $mode=array_pop($args);
            if($mode!=OBJECT && $mode!=ARRAY_A && $mode!=ARRAY_N){
                array_push($args, $mode);
                $mode=OBJECT;
            }

            $this->free_result();
            $sql=call_user_func_array(array($this, "format"), $args);
            $this->audit($sql);

            $this->_result=$this->dbh->query($sql);
            if(!$this->_result){
                $this->setError(array('error'=>$this->dbh->lastErrorMsg(), 'code'=>$this->dbh->lastErrorCode()));
                return;
            }

            $fetchMode=($mode==ARRAY_N) ? SQLITE3_NUM : SQLITE3_ASSOC;

            try {
                while($r=$this->_result->fetchArray($fetchMode)){
                    yield $mode===OBJECT ? (object)$r : $r;
                }
            } finally {
                $this->free_result();
            }
        }

		function has_table($table){
			$cnt=$this->get_var(
				"select count(*) from sqlite_master where type='table' and tbl_name=".$this->quote($table)
			);
			return $cnt==1;
		}

		function exec($sql){
			$this->free_result();

            if(func_num_args()>1){
                $sql=call_user_func_array(array($this, "format"), func_get_args());
            }

			$this->audit($sql);
			$this->_result=$this->dbh->query($sql);

			if(!$this->_result){
				$this->setError(array('error'=>$this->dbh->lastErrorMsg(), 'code'=>$this->dbh->lastErrorCode()));
				return false;
			}

			// NOTE: should not free result since it might be used in get_var...
			return true;
		}

		function get_var($sql){
			$this->audit($sql);
			return $this->dbh->querySingle($sql);
		}

		function get_row($sql, $mode=OBJECT){
			$this->audit($sql);
			$r=$this->dbh->querySingle($sql, true);  // 始终返回 associative array
			if(empty($r)) return null;

			if($mode==ARRAY_N){
				return array_values($r);
			}
			if($mode==OBJECT){
				return (object)$r;
			}
			return $r;  // ARRAY_A
		}

		function get_col($sql){
			if(!$this->exec($sql)) return false;

			$result=array();
			while($r=$this->_result->fetchArray(SQLITE3_NUM)){
				$result[]=$r[0];
			}

			$this->free_result();
			return $result;
		}

		function get_results($sql, $mode=OBJECT){
            $result=[];
            foreach($this->iterate_results(...func_get_args()) as $r){
                $result[]=$r;
            }
            return $result;
		}
	}
}
?>
