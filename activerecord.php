<?php
	namespace Clue;

	class ActiveRecord{
		static protected $_db;
		static protected $_acl;

		protected static $_model=array(
			/* Example
			 * "table"=>"blog",
			 * "pkey"=>"id",
			 * "columns"=>array(
			 *		"id"=>array("name"=>"id", "type"=>"number"),
			 * 		"title"=>array("name"=>"title", "type"=>"string"),
			 * 		"body"=>array("name"=>"content", "type"=>"string"),
			 * 		"author"=>array("name"=>"author", "type"=>"string")
			 * )
			*/
		);

		static function deduce_model(){
		    $model=&static::$_model;

		    if(!isset($model['complete'])){
		        $class=get_called_class();

		        if(!isset($model['table'])){
		            $table=strtolower($class);
		            if(strpos($table, "\\")!==false){
		            	$table=substr($table, strpos($table, "\\"));
		            }
		            $model['table']="$table";
		        }

		        if(!isset($model['pkey'])){
		            $model['pkey']='id';
		        }

		        // Detect columns
		        if(isset($model['columns'])){
		            $columns=$model['columns'];
	            }
	            else
	                $columns=array();

		        $class=new \ReflectionClass($class);
		        foreach($class->getProperties() as $prop){
		            $col=$prop->getName();
		            if($prop->isPublic() && !$prop->isStatic() && substr($col, 0, 1)!='_' && !isset($columns[$col])){
		                $columns[$col]=array('name'=>$col); // TODO: default data type
		            }
		        }
		        $model['columns']=$columns;

		        // TODO: Build table relationships
		        $model['complete']=true;
		    }
		}

		static function model(){
		    self::deduce_model();
		    return static::$_model;
		}

		static function use_acl($acl_class){
			static::$_acl=$acl_class;
		}

        static function use_database($db){
            static::$_db=$db;
        }

		static function db(){
		    global $app;

		    if(static::$_db != null){
		        return static::$_db;
		    }
		    else if(self::$_db !=null){
		        return self::$_db;
		    }
		    // TODO: decouple this extra relationship
		    else if(isset($app)){
				return static::$_db=$app['db']['default'];
			}
			else
				return null;
		}

		static function get($id){
		    $model=self::model();

		    $row=self::db()->get_row("select * from `{$model['table']}` where `{$model['pkey']}`='".self::db()->escape($id)."'", ARRAY_A);
		    if($row){
		        $class=get_called_class();
		        $r=new $class($row);
		        $r->_snap_shot();
        		$r->after_retrieve();
		        return $r;
		    }
		    else
		        return false;
		}

		static function delete($ids){
			if(!array($ids)) $ids=array($ids);
			$class=get_called_class();

			$cnt=0;
			foreach($ids as $id){
				$r=self::get($id);
				if($r instanceof $class){
					$r->destroy();
					$cnt++;
				}
			}
			return $cnt;
		}

		static function __callStatic($name, array $arguments){
		    if(preg_match('/(count|find|find_one|find_all)_by_(\w+)/', $name, $match)){
		        $method=$match[1];
		        $condition=array($match[2]=>$arguments[0]);
		        return static::$method($condition);
		    }
		    else
		        throw new \Exception("Call to undefined static method: $name");
		}

		static function _get_where_clause($condition, $range='all'){
		    $sql="";

		    // condition
			if(is_string($condition)) $condition=array($condition);

			$orderby="";
			if(count($condition)>0){
				$list=array();
				foreach($condition as $col=>$val){
					if(is_string($col)){
					    //TODO type convertion
					    if($val===null){
					        $list[]="`$col` is null";
					    }
					    else{
						    $list[]="`{$col}` = ".self::db()->quote($val);
					    }
					}
					else{
						if(strpos($val, 'order by')===0)
							$orderby=$val;
						else
							$list[]=$val;
					}
				}

				if(count($list)>0)
					$sql.=" where ".join(" and ", $list);
				if(strlen($orderby)>0)
					$sql.=' '.$orderby;
			}

			// range
			if(preg_match('/#?(\d+)\-#?(\d+)/', $range, $match)>0){
				$begin=$match[1]-1; $end=$match[2];
				$limit=$end-$begin;
				$sql.= " limit {$limit} offset {$begin}";
			}
			else if(preg_match('/limit/', $range, $match)){
			    $sql.=" $range";
			}
			else if(intval($range)>0){
				$limit=intval($range);
				$sql.= " limit $limit";
			}
			else if($range=='one'){
			    $sql.= " limit 1";
			}

			return $sql;
		}

		/**
		 * Find records based on condition
		 * eg.  $condition=array("name like 'tom%'", "sex='M'")
		 *      $condition="age>18"
    	 *      $condition=array("sex"=>'F', "order by name")
    	 *      $range="all"
    	 *      $range='1-20'
		 */
		static function find($condition, $range='all'){
		    $model=self::model();
		    $class=get_called_class();

			$sql="select * from {$model["table"]} ";
			$sql.=self::_get_where_clause($condition, $range);

			switch(strtolower($range)){
				default:
					$range='all';

				case 'all':
					$objects=array();
					$rs=self::db()->get_results($sql, ARRAY_A);

					if(is_array($rs)){
						foreach($rs as $r){
                            $r=new $class($r);
                            $r->_snap_shot();
                            $r->after_retrieve();
							$objects[]=$r;
						}
						return $objects;
					}
					else{
						return false;
					}
					break;

				case 'one':
					$r=self::db()->get_row($sql, ARRAY_A);
					if($r==false){
						return false;
					}
					else{
                        $r=new $class($r);
                        $r->_snap_shot();
                        $r->after_retrieve();
						return $r;
					}
					break;
			}
		}

		static function find_all($condition=array()){
		    return self::find($condition, 'all');
		}

		static function find_one($condition=array()){
			return self::find($condition, 'one');
		}

		static function count($condition=array()){
			$model=self::model();
			return intval(self::db()->get_var("select count(*) from {$model["table"]} ".self::_get_where_clause($condition)));
		}


		protected $_snap;
		protected $_errors;

		function __construct($data=null){
			$this->_errors=array();

			if(is_array($data)){
			    $this->bind($data);
			}
			else{
				$this->init();
			}

			$this->after_construct();
		}

		function __get($key){
		    if($key=='db') return self::db();

		    if(method_exists($this, "get_$key")){
		        $method="get_$key";
		        return $this->$method();
		    }
            else
                return $this->$key;
		}

		function __set($key, $val){
		    if(method_exists($this, "set_$key")){
		        $method="set_$key";
		        $this->$method($val);
		    }
            else
                $this->$key=$val;
		}

		function bind(array $data){
			$model=self::model();
			foreach($model['columns'] as $c=>$m){
				if(array_key_exists($c, $data)){
					$this->$c=$data[$m['name']];
				}
			}
		}

		protected function init(){
			// Empty.
		}

		function validate(){
		    $this->clear_validation_error();
			// always true, because root class didn't have any business constraints
			return true;
		}
	// Call back handlers
	function after_construct(){}	// new AR()
	function after_retrieve(){}		// AR::get(id)
	function before_save(){ return true; }
	function after_save(){}
	function before_destroy(){ return true; }
	function after_destroy(){}

		protected function _snap_shot(){
			$model=self::model();

			foreach(array_keys($model['columns']) as $f){
				$this->_snap[$f]=$this->$f;
			}
		}

		function is_new(){
			$model=self::model();
			return empty($this->_snap[$model['pkey']]);
		}

		function add_history($data, $action){
			$model=self::model();

			if(!isset($model['history_table']) || empty(self::$_acl)) return false;

			$data['history_user']=call_user_func(array(self::$_acl,"username"));
			$data['history_action']=$action;
			$data['history_time']=date("Y-m-d H:i:s");
			$data['history_comment']=null;

			self::db()->insert($model['history_table'], $data);
		}

		function save(){
		    if(!$this->validate()) return false;
		if(!$this->before_save()) return false;

			// TODO: use prepared statement to improve security and code clearance
			$model=self::model();
			$table=$model['table'];
			$pk=$model['pkey'];

			if($this->is_new()){	// Insert New
				$clist=array();
				$vlist=array();
				$history_data=array();
				foreach($model['columns'] as $c=>$m){
					if(isset($m['readonly']) || $this->_snap[$c]==$this->$c) continue;

					$clist[]="`".$m['name']."`";
					$vlist[]=self::db()->quote($this->$c);
					$history_data[$m['name']]=$this->$c;
				}
				$sql="insert into `$table` (".join(", ", $clist).") values(".join(",", $vlist).")";
				$history_action='C';
			}
			else{ // Update Value
				$list=array();
				$history_data=array();
				foreach($model['columns'] as $c=>$m){
					if(isset($m['readonly']) || $this->_snap[$c]==$this->$c) continue;

					$list[]="`".$m['name']."`=".self::db()->quote($this->$c);
					$history_data[$m['name']]=$this->$c;
				}
				if(count($list)>0){
					$sql="update `$table` set ".join(",", $list)." where `$pk`='".$this->$pk."'";
					$history_action='U';
				}
				else{
				    // Nothing has changed.
				    return true;
				}
			}

			$success=self::db()->exec($sql);

			// Update or Insert is successful.
			// Update the primary key if new record inserted.
			if($success){
    			if(empty($this->$pk)){
    				$this->$pk=self::db()->insert_id();
    			}

    			$history_data[$pk]=$this->$pk;
    			$this->add_history($history_data, $history_action);

    			$this->_snap_shot();
			}

		$this->after_save();

			return $success;
		}

		function destroy(){
		if(!$this->before_destroy()) return false;
			$model=self::model();
			$pk=$model['pkey'];

			$history_data=array();
			foreach($model['columns'] as $c=>$m){
				$history_data[$m['name']]=$this->$c;
			}
			$history_data[$pk]=$this->$pk;

			$sql="delete from {$model["table"]} where `$pk`='".$this->$pk."'";
			$ret=self::db()->exec($sql);

			$this->add_history($history_data, 'D');

			$this->init();
			$this->_snap_shot();

		$this->after_destroy();
			return $ret;
		}

		// TODO: proper error handling
		function clear_validation_error(){
		    $errors=array();
		    foreach($this->_errors as $err){
		        if($err['type']=='validation') continue;
		        $errors[]=$err;
		    }
		    $this->_errors=$errors;
		}

		function set_validation_error($error){
		    $this->_errors[]=array('type'=>'validation', 'error'=>$error);
		}

		function hasError(){
			return count($this->_errors)>0;
		}

		function setBindError($column, $err){
			$this->_errors[]=array('type'=>'bind', 'column'=>$column, 'error'=>$err);
		}

		function setDBError($sql, $err){
			$this->_errors[]=array('type'=>'db', 'sql'=>$sql, 'error'=>$err);
		}

		function setError($err, $type='other'){
			$this->_errors[]=array('type'=>$type, 'error'=>$err);
		}

		function errors(){
			return $this->_errors;
		}

		function clearError(){
			$this->_errors=array();
		}

		/**
		 * Bind data value from database into attributes
		 *
		 * @param string $name
		 * @param string $value
		 * @param string $type
		 * @return mixed data types
		 */
		function dbbind($name, $value){
			if($value===null) return null;

			// Name will be ignored in this base class.
			$meta=$this->getMeta();

			switch($meta['columns'][$name]['type']){
				case 'int':
					return intval($value);
				case 'string':
					return strval($value);
				case 'bool':
				case 'boolean':
					return intval($value)>0;
				case 'time':
				case 'datetime':
				case 'date':
				case 'timestamp':
					return new Time($value);
				default:
					$type=$meta['columns'][$name]['type'];
					return new $type($value);
			}
		}

		/**
		 * Cast attribute value into database data types
		 *
		 * @param string $name
		 * @param string $value
		 * @param string $type
		 * @return mixed number or string (that's the only types database will accept)
		 */
		function dbcast($name, $value){
			$meta=$this->getMeta();
			$db=&$this->_db;
			// Name will be ignored in this base class.

			$type=null;
			if(array_key_exists($name, $meta['columns'])){
				$type=$meta['columns'][$name]['type'];
			}
			else{	// Iterate to find the name as db field name
				foreach($meta['columns'] as $n=>$m){
					if($n==$name){
						$type=$m['type'];
						break;
					}
				}
			}

			switch($type){
				case 'time':
				case 'datetime':
				case 'date':
				case 'timestamp':
					$value=$value->format(DATETIME_LONG, null);
					return ($value===NULL) ? 'null' : $db->quote($value, Database::PARAM_STR);
				case 'bool':
				case 'boolean':
					return $value?1:0;
				case 'int':
					return $value===NULL ? 'null' : $db->quote($value, Database::PARAM_INT);
				default:
					return $value===NULL ? 'null' : $db->quote($value);
			}
		}

		/**
            * TODO: move to database
		 * Bind data value from web input into attributes, typically HTTP
		 *
		 * @param string $name attribute name
		 * @param string $type data type
		 * @return typed data after bind mapping
		 */
		function webbind($name, $value){
			// Name will be ignored in this base class.
			$meta=$this->getMeta();

			switch($meta['columns'][$name]['type']){
				case 'time':
				case 'datetime':
				case 'date':
				case 'timestamp':
					return new Time($value);
				case 'int':
					return intval($value);
				case 'string':
					return strval($value);
				case 'bool':
				case 'boolean':
					return $value=='on';
				default:
					return $value;
			}
		}
	}
?>
