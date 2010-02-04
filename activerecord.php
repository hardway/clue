<?php  
	require_once 'clue/core.php';
	
	class Clue_ActiveRecord{
		static public $default_database;
		
		static public $_meta=array(
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
		
		protected $_db;

		protected $_snap;
		
		protected $_errors;
		
		protected $_change;

		static function dbo(){
			if(self::$default_database==null && Clue_Application::initialized()){
				return self::$default_database=Clue_Application::db();
			}
			else
				return self::$default_database;
		}
		
		// FUTURE: more complicated constructor
		//	such as new R(array('name'=>'tom', 'age'=>12))
		function __construct($id=null, $db=null){
			self::__rebuildMeta();
						
			if($db==null)$db=self::dbo();
			$this->_db=&$db;
			
			$this->_errors=array();

			if($id!=null){
				$meta=$this->getMeta(); $pk=$meta['pkey'];
				$this->$pk=$id;
				$this->sync();
			}
			else{
				$this->init();
			}
			
			$this->_snap_shot();
		}
		
		protected function init(){
			// Empty.
		}
		
		protected function _snap_dirty(){
			$meta=$this->getMeta();
			
			foreach(array_keys($meta['columns']) as $f){
				if($this->_snap[$f]!=$this->$f) return true;
			}
			
			return false;
		}
		
		protected function _snap_shot(){
			$meta=$this->getMeta();
			
			foreach(array_keys($meta['columns']) as $f){
				$this->_snap[$f]=$this->$f;
			}
		}
		
		protected function __rebuildMeta(){
			$class=get_class($this);
			// echo "Rebulding $class<br />";

			$SCV=get_class_vars($class);
			
			// build meta only first time.
			if(isset($SCV['_meta']['built'])) return;
			
			$SCV['_meta']['built']=true;
			// write back SCV["_meta"], first time
			$rx=new ReflectionClass(get_class($this));
			$rx->setStaticPropertyValue("_meta", $SCV["_meta"]);

			if(!isset($SCV["_meta"]["table"])) $SCV["_meta"]["table"]=strtolower($class);
			if(!isset($SCV["_meta"]["pkey"])) $SCV["_meta"]["pkey"]="id";
			if(!isset($SCV["_meta"]["columns"])) $SCV["_meta"]["columns"]=array();
			$columns=&$SCV["_meta"]["columns"];
			
			foreach(array_keys(get_object_vars($this)) as $col){
				if($col[0]=="_") continue;
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
		
		function &getMeta(){
			$SCV=get_class_vars(get_class($this));
			return $SCV['_meta'];
		}
		
		function &getTable(){
			$meta=$this->getMeta();
			return $meta['table'];
		}
		
		function &getPK(){
			$meta=$this->getMeta();
			return $meta['pkey'];
		}
		
		function __toString(){
			// FUTURE: display data in a more decent format, like krumo
			$pk=$this->getPK();
			return $this->$pk . "(".($this->_snap_dirty() ? "DIRTY" : "SYNC").")";
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
		
		function sync(){
			$meta=$this->getMeta();
			
			if($this->_snap_dirty()){
				$sql="select * from `".$meta["table"]."` where `".$meta["pkey"]."`='$this->id'";

				$r=$this->_db->get_row($sql, ARRAY_A);
				
				if($r==null){
					// data not found.
				}
				else{
					foreach($meta["columns"] as $c=>&$m){
						$this->$c=$this->dbbind($c, $r[$m["name"]], $m['type']);
					}
				}
			}
			
			$this->_change=array();
			return true;
		}
		
		function bind(array $data){
			$meta=$this->getMeta();
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
		
		function isNew(){
			$meta=$this->getMeta();
			return empty($this->_snap[$meta['pkey']]);
		}
		
		function changelog($name=null){
			if($name==null) $name=get_class($this);
			
			$log=array();
			
						
			if(isset($this->_change['insert'])){
				foreach($this->_change as $n=>$v){
					if($n=='insert') continue;
					$log[]="$n :=> \"$v\"";
				}
				return "Created new {$name}[".$this->_change['insert']."] with {". implode(", ", $log)."}";
			}
			else if(isset($this->_change['update'])){
				foreach($this->_change as $n=>$v){
					if($n=='update') continue;
					$log[]="$n :=> \"$v\"";
				}
				return "Update {$name}[".$this->_change['update']."] with {". implode(", ", $log)."}";
			}
			else if(isset($this->_change['delete'])){
				foreach($this->_change as $n=>$v){
					if($n=='delete') continue;
					$log[]="$n :=> \"$v\"";
				}
				return "Delete {$name}[".$this->_change['delete']."] with {". implode(", ", $log)."}";
			}
			else{
				// NO change detected.
				return false;
			}
		}
		
		function save(){
			if($this->check()===false){
				var_dump($this->errors());
				return false;
			}
			
			// FUTURE: use prepared statement to improve security and code clearance
			$meta=$this->getMeta();
			$table=$meta['table'];
			$pk=$meta['pkey'];
			
			if($this->isNew()){	// Insert New
				$collist=array();
				$vallist=array();
				foreach($meta['columns'] as $c=>$m){
					if(isset($m['readonly'])) continue;
					if($this->_snap[$c]==$this->$c) continue;
					$collist[]="`".$m['name']."`";
					$vallist[]=$this->dbcast($c, $this->$c, $m['type']);
					
					// Record change
					$this->_change[$c]=$this->$c;
				}
				$sql="insert into $table (".join(", ", $collist).") values(".join(",", $vallist).")";
			}
			else{ // Update Value
				$list=array();
				foreach($meta['columns'] as $c=>$m){
					if(isset($m['readonly'])) continue;
					if($this->_snap[$c]==$this->$c) continue;
					$list[]="`".$m['name']."`=".$this->dbcast($c, $this->$c, $m['type']);
					
					// Record change
					$this->_change[$c]=$this->$c;
				}
				if(count($list)>0)
					$sql="update $table set ".join(",", $list)." where `$pk`='".$this->$pk."'";
				else
					$sql=false;
			}
			
			if($sql){
				$ret=$this->_db->exec($sql);
				if($ret===false){
					$this->setDBError($sql, $this->_db->errors);
				}
				else{
					// Update or Insert is successful.
					// Update the primary key if new record inserted.
					if(empty($this->$pk)){
						$this->$pk=$this->_db->insertId();
						$this->_change['insert']=$this->$pk;
					}
					else{
						$this->_change['update']=$this->$pk;
					}
				}
				
				$this->_snap_shot();
				return $ret;
			}
			return true;			
		}
		
		function destroy(){
			$meta=$this->getMeta();
			$pk=$meta['pkey'];
			
			// Record changes.
			$this->_change['delete']=$this->$pk;
			foreach($meta['columns'] as $c=>$m){
				if(isset($m['readonly'])) continue;
				// Record change
				$this->_change[$c]=$this->$c;
			}
			
			$sql="delete from {$meta["table"]} where `$pk`='".$this->$pk."'";
			$ret=$this->_db->exec($sql);
			if($ret===false){
				$this->setDBError($sql, $this->_db->errorInfo());
			}
			
			$this->init();
			$this->_snap_shot();
			
			return $ret;
		}
		
		public function countAll(){
			$meta=$this->getMeta();
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
			else if(intval($range)>0){
				$limit=intval($range);
				$sql.= " limit $limit";
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
						$this->setDBError($sql, $this->_db->lasterror);
						return false;
					}
					break;
					
				case 'one':
					$ret=$this->_db->get_var($sql);
					if($ret==false){
						$this->setDBError($sql, $this->_db->lasterror);
						return false;
					}
					else{
						$object=new $class($ret);
						return $object;
					}
					break;
			}			
		}
	}
?>