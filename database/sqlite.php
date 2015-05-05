<?php
namespace Clue\Database{
	class Sqlite extends \Clue\Database{
		protected $_result;

		function __construct(array $param){
			// Make sure mysqli extension is enabled
			if(!extension_loaded('sqlite3'))
				throw new Exception(__CLASS__.": extension sqlite3 is missing!");

			// Check Parameter, TODO: access mode
			$this->dbh=new \SQLite3($param['db'], SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);

			if(!$this->dbh){
				$this->setError(array('error'=>SQLite3::lastErrorMsg()));
			}
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

		function insert_id(){
			return $this->dbh->lastInsertRowID();
		}

        function insert($table, $fields, $verb='insert'){
            $cols=array();
            $vals=array();
            foreach($fields as $c=>$v){
                $cols[]='`'.trim($c, '`').'`';
                $vals[]=":$c";
            }
            $sql="$verb into `$table`(".implode(',', $cols).") values(".implode(',', $vals).")";

            $stmt=$this->dbh->prepare($sql);
            foreach($fields as $c=>$v){
	            $stmt->bindValue(":$c", $v);
	        }

	        $this->_result=$stmt->execute();

            return $this->dbh->lastErrorCode() ? null : $this->dbh->lastInsertRowID();
        }

        function update($table, $fields, $where){
            $updates=array();

            foreach ($fields as $c=>$v) {
                $updates[]="`$c`=:$c";
            }

            $stmt=$this->dbh->prepare("update `$table` set ".implode(', ', $updates)." where $where");
            foreach($fields as $c=>$v){
	            $stmt->bindValue(":$c", $v);
	        }

	        $this->_result=$stmt->execute();

            return true;
        }

		function has_table($table){
			$cnt=$this->get_var("select count(*) from sqlite_master where type='table' and tbl_name='$table'");
			return $cnt==1;
		}

		function exec($sql){
			parent::exec($sql);

			$result_type=false;

			$this->free_result();
			$this->_result=$this->dbh->query($sql);

			if(!$this->_result){
				$this->setError(array('error'=>$this->dbh->lastErrorMsg(), 'code'=>$this->dbh->lastErrorCode()));
				return false;
			}

			// NOTE: should not free result since it might be used in get_var...
			return true;
		}

		function get_var($sql){
			return $this->dbh->querySingle($sql);
		}

		function get_row($sql, $mode=OBJECT){
			return $this->dbh->querySingle($sql, true);
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
				while($r=$this->_result->fetchArray(SQLITE3_ASSOC)){
					$r = json_decode(json_encode($r), FALSE);
					$result[]=$r;
				}
			}
			else if($mode==ARRAY_A){
				while($r=$this->_result->fetchArray(SQLITE3_ASSOC)){
					$result[]=$r;
				}
			}
			else if($mode==ARRAY_N){
				while($r=$this->_result->fetchArray(SQLITE3_NUM)){
					$result[]=$r;
				}
			}

			$this->free_result();
			return $result;
		}
	}
}
?>
