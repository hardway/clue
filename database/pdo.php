<?php  
	class Clue_Database_PDO extends Clue_Database{
		protected $_result;
				
		function __construct(array $param){
			// Make sure mysqli extension is enabled
			if(!extension_loaded('pdo')) 
				throw new Exception(__CLASS__.": extension PDO is missing!");
			
			// Check Parameter, TODO: access mode
			try{
				$this->dbh=new PDO($param['dsn'], $param['username'], $param['password']);
			}
			catch(PDOException $e){
				$this->setError(array('error'=>$e->getMessage()));
			}
		}
		
		function __destruct(){
			$this->free_result();
			if($this->dbh){
				$this->dbh=null;
			}
		}
		
		protected function free_result(){
			if(is_object($this->_result)){
				$this->_result->closeCursor();
			}
			$this->_result=null;
		}
		
		function insert_id(){
			return $this->dbh->lastInsertId();
		}
				
		function exec($sql){
			parent::exec($sql);

			return $this->dbh->exec($sql);
		}
		
		function query($sql){
			parent::query($sql);
			
			$this->_result=$this->dbh->query($sql);
			return true;
		}
		
		function get_var($sql){
			if(!$this->query($sql)) return false;
			
			$row=$this->_result->fetchColumn();
			$this->free_result();
			
			return $row;
		}
		
		function get_row($sql, $mode=OBJECT){
			if(!$this->query($sql)) return false;
			
			$result=false;
			
			if($mode==OBJECT)
				$result=$this->_result->fetchObject();
			else if($mode==ARRAY_A)
				$result=$this->_result->fetch(PDO::FETCH_ASSOC);
			else if($mode==ARRAY_N)
				$result=$this->_result->fetch(PDO::FETCH_NUM);
			else
				$result=$this->_result->fetch(PDO::FETCH_NUM);
			
			$this->free_result();
			return $result;
		}
		
		function get_col($sql){
			if(!$this->query($sql)) return false;
			
			$result=array();
			while($r=$this->_result->fetchColumn()){
				$result[]=$r;
			}
			
			$this->free_result();
			return $result;
		}
		
		function get_results($sql, $mode=OBJECT){
			if(!$this->query($sql)) return false;
			
			$result=array();
			
			if($mode==OBJECT){
				while($r=$this->_result->fetchObject()){
					$result[]=$r;
				}
			}
			else if($mode==ARRAY_A){
				while($r=$this->_result->fetch(PDO::FETCH_ASSOC)){
					$result[]=$r;
				}
			}
			else if($mode==ARRAY_N){
				while($r=$this->_result->fetch(PDO::FETCH_NUM)){
					$result[]=$r;
				}
			}
			
			$this->free_result();
			return $result;
		}
	}
?>