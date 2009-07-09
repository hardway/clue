<?php  
	require_once 'clue/core.php';
	
	class Clue_ActiveRecord{
		static public $default_database;
		
		static public $_meta=array(
			/* Example
			 * "table"=>"blog",
			 * "primarykey"=>"id",
			 * "columns"=>array(
			 * 		"title"=>array("name"=>"title", "type"=>"string"),
			 * 		"body"=>array("name"=>"content", "type"=>"string"),
			 * 		"author"=>array("name"=>"author", "type"=>"string")
			 * )
			*/
		);
		
		public $id;
		protected $_db;
		protected $_sync;
		protected $_errors;

		// FUTURE: more complicated constructor
		//	such as new R(array('name'=>'tom', 'age'=>12))
		function __construct($id=null, $db=null){
			self::__rebuildMeta();
			
			if($db==null) $db=self::$default_database;
			
			$this->_db=$db;
			$this->id=$id;
			$this->_sync=false;
			$this->_errors=array();

			if($this->id!=null)
				$this->sync();
			else
				$this->init();
		}
		
		protected function init(){
			// Empty.
		}
		
		protected function __rebuildMeta(){
			$class=get_class($this);
			// echo "Rebulding $class<br />";

			$SCV=get_class_vars(get_class($this));
			
			// build meta automatically
			if(isset($SCV['_meta']['built'])) return;
			
			$SCV['_meta']['built']=true;
			
			// write back SCV["_meta"], first time
			$rx=new ReflectionClass(get_class($this));
			$rx->setStaticPropertyValue("_meta", $SCV["_meta"]);

			if(!isset($SCV["_meta"]["table"])) $SCV["_meta"]["table"]=strtolower(get_class($this));
			if(!isset($SCV["_meta"]["pkey"])) $SCV["_meta"]["pkey"]="id";
			if(!isset($SCV["_meta"]["columns"])) $SCV["_meta"]["columns"]=array();
			$columns=&$SCV["_meta"]["columns"];
			
			foreach(array_keys(get_object_vars($this)) as $col){
				if($col[0]=="_" || $col=='id' || $col==$SCV["_meta"]["pkey"]) continue;
				$default=array("name"=>$col, "type"=>"string");	// FUTURE: auto determine by convention
				$columns[$col]=isset($columns[$col]) ? array_merge($default, $columns[$col]) : $default;
			}
			
			// build table relations
			if(isset($SCV['_meta']['has_one']))	foreach($SCV['_meta']['has_one'] as $col=>$rel){
				list($classname, $join)=explode('|', $rel);
				list($colname, $dest)=explode('=', $join);
				list($desttable, $destcol)=explode('.', $dest);
				// var_dump(compact(array('colname', 'dest', 'desttable', 'destcol')));
				$columns[$col]['name']=$colname;
				$columns[$col]['type']=$classname;
			}
									
			// write back SCV["_meta"], second time
			$rx->setStaticPropertyValue("_meta", $SCV["_meta"]);
			
		}
		
		protected function getMeta(){
			$SCV=get_class_vars(get_class($this));
			return $SCV['_meta'];
		}
		
		function __toString(){
			// FUTURE: display data in a more decent format, like krumo
			return $this->id . "(".($this->_sync ? "SYNC" : "N/A").")";
		}
		
		function __get($prop){
			if($prop=="id" || $prop[0]=="_") 
				return $this->$prop;
			else{
				$this->sync();
				return isset($this->$prop) ? $this->$prop : false;
			}
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
			$meta=&$this->getMeta();
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
		 * Bind data value from web input into attributes, typically HTTP
		 *
		 * @param string $name attribute name
		 * @param string $type data type
		 * @return typed data after bind mapping
		 */
		function webbind($name, $value){
			// Name will be ignored in this base class.
			$meta=&$this->getMeta();
			
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
		
		function sync(){
			$meta=$this->getMeta();
			
			if($this->_sync!=true){
				$sql="select * from `".$meta["table"]."` where `".$meta["pkey"]."`='$this->id'";

				$r=$this->_db->get_row($sql, ARRAY_A);
				
				if($r==null){
					// data not found.
				}
				else{
					foreach($meta["columns"] as $c=>&$m){
						$this->$c=$this->dbbind($c, $r[$m["name"]], $m['type']);
					}
					
					$this->_sync=true;
				}				
			}
			
			return $this->_sync;
		}
		
		function bind(array $data){
			$meta=&$this->getMeta();
			foreach($meta['columns'] as $c=>$m){
				if(array_key_exists($c, $data)){
					try{
						$this->$c=$this->webbind($c, $data[$c], $m['type']);
					}
					catch(Exception $e){
						$this->setBindError($c, $e->getMessage());
					}
				}
			}
		}
		
		function check(){
			// always true, because root class didn't have any business constraints
			return true;
		}
		
		function save(){
			if($this->check()===false){
				return false;
			}
			
			// FUTURE: use prepared statement to improve security and code clearance
			$meta=&$this->getMeta();
			
			if($this->id==null){	// Insert New
				$collist=array();
				$vallist=array();
				foreach($meta['columns'] as $c=>$m){
					if(isset($m['readonly'])) continue;
					$collist[]="`".$m['name']."`";
					$vallist[]=$this->dbcast($c, $this->$c, $m['type']);
				}
				$sql="insert into {$meta["table"]} (".join(", ", $collist).") values(".join(",", $vallist).")";
			}
			else{ // Update Value
				$list=array();
				// TODO: use _data cache to update modified data only.
				foreach($meta['columns'] as $c=>$m){
					if(isset($m['readonly'])) continue;
					$list[]="`".$m['name']."`=".$this->dbcast($c, $this->$c, $m['type']);
				}
				$sql="update {$meta["table"]} set ".join(",", $list)." where `{$meta["pkey"]}`='$this->id'";
			}
			
			$ret=$this->_db->exec($sql);
			if($ret===false){
				$this->setDBError($sql, $this->_db->errorInfo());
			}
			else{
				// Update or Insert is successful.
				// Update the primary key if new record inserted.
				if($this->id==null) $this->id=$this->_db->lastInsertId();
			}
			return $ret;
		}
		
		function destroy($option=""){
			$meta=&$this->getMeta();
			$sql="delete from {$meta["table"]} where `{$meta["pkey"]}`='{$this->id}'";
			$ret=$this->_db->exec($sql);
			if($ret===false){
				$this->setDBError($sql, $this->_db->errorInfo());
			}
			return $ret;
		}
		
		public function countAll(){
			$meta=&$this->getMeta();
			return $this->_db->get_var("select count(*) from `{$meta["table"]}`");
		}
		
		public function findAll($condition=array()){
			return $this->find($condition, 'all');
		}
		
		public function findOne($condition=array()){
			return $this->find($condition, 'one');
		}
		
		/**
		 * Find records based on condition
		 *
		 * @param string $method
		 * 		one, all, #-#
		 * @param array $condition
		 * @return array of objecs
		 */
		public function find($condition, $range='all'){
			if(is_string($condition)) $condition=array($condition);
			$orderby="";
			
			$class=get_class($this);
			$SCV=get_class_vars(get_class($this));
			
			$sql="select `{$SCV["_meta"]["pkey"]}` from `{$SCV["_meta"]["table"]}`";
			
			if(count($condition)>0){
				$list=array();
				foreach($condition as $col=>$val){
					if(is_string($col)){
						$val=$this->dbcast($col, $val);
						$list[]="`{$col}` ".($val=='null'?'is':'=').' '.$val;
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
			
			switch(strtolower($range)){
				default:
					$range='all';
					
				case 'all':
					$objects=array();
					$rs=$this->_db->get_col($sql);
					
					if(is_array($rs)){
						foreach($rs as $id){
							$objects[]=new $class($id);
						}
						return $objects;
					}
					else{
						$this->setDBError($sql, $this->_db->errorInfo());
						return false;
					}
										
					break;
					
				case 'one':
					$ret=$this->_db->get_var($sql);
					if($ret==false){
						$this->setDBError($sql, $this->_db->errorInfo());
						return false;
					}
					else{
						$object=new $class($ret);
						return $object;
					}
					break;
			}			
		}
		
		function isEmpty(){ return $this->isNull(); }
		function isNull(){ return $this->id==null; }
		
		static function getConnection(){
			global $db;
			return $db;
		}
	}
?>