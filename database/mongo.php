<?php
namespace Clue\Database{
	class Mongo extends \Clue\Database{
		protected $_result;

		function __construct(array $param){
			if(!extension_loaded('mongo')) throw new \Exception(__CLASS__.": extension mongo is missing!");

			$conn_str=sprintf("mongodb://%s:%s", @$param['host']?:'localhost', @$param['port']?:27017);
			$this->conn=new \MongoClient($conn_str);

			$this->dbh=$this->conn->selectDB($param['db']);

			if(!$this->dbh){
				$this->setError(array('error'=>"Can't connect mongo database."));
			}
		}

		function __destruct(){
			if($this->conn){
				$this->conn->close();
				$this->conn=null;
			}
		}

		function insert($collection, $doc){
			$collection=$this->dbh->selectCollection($collection);

			if(isset($doc['id']) && !isset($doc['_id'])) $doc['_id']=$doc['id'];

			$r=$collection->save($doc);

			return $r['ok'] ? $doc['_id'] : false;
		}

		function replace($collection, $doc){
			return $this->insert($collection, $doc);
		}

		function update($collection, $change, $query=[]){
			$collection=$this->dbh->selectCollection($collection);
			$r=$collection->update($query, ['$set'=>$change], ['multiple'=>1]);

			return $r['ok'];
		}

		function delete($collection, $query){
			$collection=$this->dbh->selectCollection($collection);
			$r=$collection->remove($query);

			return $r['ok'];
		}

		function count($collection, $query=[]){
			$collection=$this->dbh->selectCollection($collection);
			return $collection->count($query);
		}

		function has_table($table){
			$tables=$this->dbh->getCollectionNames();
			return in_array($table, $tables);
		}

		function get_var($path, $query=[]){
			// Path必须是"Collection.Fields"格式
			list($collection, $field)=explode(".", $path, 2);

			$collection=$this->dbh->selectCollection($collection);

			$r=$collection->findOne($query, [$field]);
			foreach(explode(".", $field) as $f){
				if(!isset($r[$f])) return null;
				$r=$r[$f];
			}

			return $r;
		}

		function get_col($path, $query=[]){
			// Path必须是"Collection.Fields"格式
			list($collection, $field)=explode(".", $path, 2);
			$collection=$this->dbh->selectCollection($collection);

			$result=[];
			foreach($collection->find($query, [$field]) as $r){
				foreach(explode(".", $field) as $f){
					if(!isset($r[$f])){
						$r=null; break;
					}

					$r=$r[$f];
				}

				$result[]=$r;
			}

			return $result;
		}

		function get_row($collection, $query=[], $mode=OBJECT){
			$collection=$this->dbh->selectCollection($collection);

			$r=$collection->findOne($query);
			return $r;
		}

		function get_results($collection, $query=[], $fields=[], $options=[]){
			$result=[];

			foreach($this->iterate_results($collection, $query, $fields, $options) as $r){
				$result[]=$r;
			}

			return $result;
		}

		function iterate_results($collection, $query=[], $fields=[], $options=[]){
			$collection=$this->dbh->selectCollection($collection);

			$cursor=$collection->find($query, $fields);
			if(isset($options['limit'])) $cursor->limit($options['limit']);

			foreach($cursor as $r){
				yield $r;
			}
		}
	}
}
?>
