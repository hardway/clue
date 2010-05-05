<?php  
	require_once 'clue/core.php';
	
	class Clue_ActiveRecord{
		static protected $_db;
		
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
		            $model['table']=strtolower($class);
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
		        
		        $class=new ReflectionClass($class);
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
		
        static function use_database($db){
            static::$_db=$db;
        }
        
		static function db(){
		    if(static::$_db != null){
		        return static::$_db;
		    }
		    else if(self::$_db !=null){
		        return self::$_db;
		    }
		    // TODO: decouple this extra relationship
		    else if(Clue_Application::initialized()){
				return static::$_db=Clue_Application::db();
			}
			else
				return null;
		}
		
		static function get($id){
		    $model=self::model();
		    
		    $row=self::db()->get_row("select * from {$model['table']} where {$model['pkey']}='".self::db()->escape($id)."'", ARRAY_A);
		    if($row){
		        $class=get_called_class();
		        $r=new $class($row);
		        
		        return $r;
		    }
		    else
		        return false;
		}
		
		static function __callStatic($name, array $arguments){
		    if(preg_match('/(count|find|find_one|find_all)_by_(\w+)/', $name, $match)){
		        $method=$match[1];
		        $condition=array($match[2]=>$arguments[0]);
		        return static::$method($condition);
		    }
		    else
		        throw new Exception("Call to undefined static method: $name");
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
					    if($val==null){
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
		    
			$sql="select * from `{$model["table"]}` ";
			$sql.=self::_get_where_clause($condition, $range);
						
			switch(strtolower($range)){
				default:
					$range='all';
					
				case 'all':
					$objects=array();
					$rs=self::db()->get_results($sql, ARRAY_A);
					
					if(is_array($rs)){
						foreach($rs as $r){
							$objects[]=new $class($r);
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
						return new $class($r);
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
			return intval(self::db()->get_var("select count(*) from `{$model["table"]}` ".self::_get_where_clause($condition)));
		}


		protected $_snap;
		protected $_errors;		
		protected $_change;

		function __construct($data=null){
			$this->_errors=array();

			if(is_array($data)){
			    $this->bind($data);
			}
			else{
				$this->init();
			}
			
			$this->_snap_shot();
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
		
		function check(){
			// always true, because root class didn't have any business constraints
			return true;
		}
				
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
				
		function save(){
		    // TODO, better organization
			if($this->check()===false){
				throw $this->errors();
			}
			
			// TODO: use prepared statement to improve security and code clearance
			$model=self::model();
			$table=$model['table'];
			$pk=$model['pkey'];
			
			if($this->is_new()){	// Insert New
				$clist=array();
				$vlist=array();
				foreach($model['columns'] as $c=>$m){
					if(isset($m['readonly']) || $this->_snap[$c]==$this->$c) continue;
					
					$clist[]="`".$m['name']."`";
					$vlist[]=self::db()->quote($this->$c);
				}
				$sql="insert into $table (".join(", ", $clist).") values(".join(",", $vlist).")";
			}
			else{ // Update Value
				$list=array();
				foreach($model['columns'] as $c=>$m){
					if(isset($m['readonly']) || $this->_snap[$c]==$this->$c) continue;
					
					$list[]="`".$m['name']."`=".self::db()->quote($this->$c);
				}
				if(count($list)>0)
					$sql="update $table set ".join(",", $list)." where `$pk`='".$this->$pk."'";
				else{
				    // Nothing has changed.
				    return true;
				}					
			}
			
			// TODO: check affected rows
			$ret=self::db()->exec($sql);
			
			// Update or Insert is successful.
			// Update the primary key if new record inserted.
			if(empty($this->$pk)){
				$this->$pk=self::db()->insertId();
			}
			
			$this->_snap_shot();
			return $ret;		
		}
		
		function destroy(){
			$model=self::model();
			$pk=$model['pkey'];
			
			$sql="delete from {$model["table"]} where `$pk`='".$this->$pk."'";
			$ret=self::db()->exec($sql);
			
			$this->init();
			$this->_snap_shot();
			
			return $ret;
		}
		
		// TODO: proper error handling
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