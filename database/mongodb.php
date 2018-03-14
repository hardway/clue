<?php
/**
 * 只支持PHP7
 */
namespace Clue\Database{
    class MongoDB extends \Clue\Database{
        protected $_result;

        function __construct(array $param){
            if(!extension_loaded('mongodb')) throw new \Exception(__CLASS__.": extension mongo is missing!");

            $conn_str=sprintf("mongodb://%s:%s", @$param['host']?:'localhost', @$param['port']?:27017);
            $this->conn=new \MongoDB\Driver\Manager($conn_str);

            $this->db=$param['db'];
        }

        function __destruct(){
            if($this->db){
                $this->exec(['logout'=>1]);
            }
        }

        function insert($collection, $doc){
            if(isset($doc['id']) && !isset($doc['_id'])) $doc['_id']=$doc['id'];

            $cmd=new \MongoDB\Driver\Command([
                'insert'=>$collection,
                'documents'=>[$doc]
            ]);

            $rs=$this->conn->executeWriteCommand($this->db, $cmd);
            $r=$rs->toArray()[0];

            $this->affected_records=$r->n;
            if(@$r->writeErrors) foreach($r->writeErrors as $e){
                $this->setError(['code'=>$e->code, 'error'=>$e->errmsg]);
            }

            return $r->ok ? $doc['_id'] : false;
        }

        function replace($collection, $doc){
            if(isset($doc['id']) && !isset($doc['_id'])) $doc['_id']=$doc['id'];

            $cmd=new \MongoDB\Driver\Command([
                'update'=>$collection,
                'updates'=>[
                    [
                        'q'=>['_id'=>$doc['_id']],
                        'u'=>$doc,
                        'upsert'=>true
                    ]
                ]
            ]);

            $rs=$this->conn->executeWriteCommand($this->db, $cmd);
            $r=$rs->toArray()[0];

            return $r->ok ? $doc['_id'] : false;
        }

        function save($collection, $doc){
            return $this->replace($collection, $doc);
        }

        /**
         * 批量更新
         */
        function update($collection, $change, $query=[]){
            // 必须支持update operator
            $update_operators=['$currentDate', '$inc', '$min', '$max', '$mul', '$rename', '$set', '$setOnInsert', '$unset'];
            $uo_found=false;
            foreach($update_operators as $uo){
                if(isset($change[$uo])){
                    $uo_found=true;
                    break;
                }
            }

            if(!$uo_found){
                // 缺省为$set
                $change=['$set'=>$change];
            }

            $cmd=[
                'update'=>$collection,
                'updates'=>[
                    [
                        'q'=>$query,
                        'u'=>$change,
                        'multi'=>true
                    ]
                ]
            ];

            $rs=$this->conn->executeWriteCommand($this->db, new \MongoDB\Driver\Command($cmd));
            $r=$rs->toArray()[0];

            return $r->ok;
        }

        function delete($collection, $query=[]){
            $cmd=[
                'delete'=>$collection,
                'deletes'=>[
                    ['q'=>$query ?: new \stdClass(), 'limit'=>0]
                ]
            ];

            $rs=$this->conn->executeWriteCommand($this->db, new \MongoDB\Driver\Command($cmd));
            $r=$rs->toArray()[0];

            return $r->ok;
        }

        function count($collection, $query=[]){
            $cmd=new \MongoDB\Driver\Command([
                'count'=>$collection,
                'query'=>$query
            ]);

            $rs=$this->conn->executeReadCommand($this->db, $cmd);
            $r=$rs->toArray()[0];

            return $r->n;
        }

        function exec($cmd){
            $cmd=new \MongoDB\Driver\Command($cmd);

            $rs=$this->conn->executeCommand($this->db, $cmd);
            $r=$rs->toArray()[0];

            return $r;
        }

        function get_var($path, $query=[]){
            // Path必须是"Collection.Fields"格式
            list($collection, $field)=explode(".", $path, 2);

            $r=$this->get_row($collection, $query);

            foreach(explode(".", $field) as $f){
                if(!isset($r[$f])) return null;
                $r=$r[$f];
            }

            return $r;
        }

        function get_col($path, $query=[]){
            // Path必须是"Collection.Fields"格式
            list($collection, $field)=explode(".", $path, 2);

            $result=[];
            foreach($this->iterate_results($collection, $query, ["$field"=>1]) as $r){
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

        function get_row($collection, $query=[], $fields=[]){
            $cmd=[
                'find'=>$collection,
                'limit'=>1
            ];
            if($query) $cmd['filter']=$query;
            if($fields) $cmd['projection']=$fields;

            $rs=$this->conn->executeReadCommand($this->db, new \MongoDB\Driver\Command($cmd));
            $r=@$rs->toArray()[0] ?: null;

            return json_decode(json_encode($r), true);
        }

        function get_results($collection, $query=[], $fields=[], $options=[]){
            $result=[];

            foreach($this->iterate_results($collection, $query, $fields, $options) as $r){
                $result[]=$r;
            }

            return $result;
        }

        function iterate_results($collection, $query=[], $fields=[], $options=[]){
            $cmd=[
                'find'=>$collection,
            ];

            if($query) $cmd['filter']=$query;
            if($fields) $cmd['projection']=$fields;
            if(isset($options['limit'])) $cmd['limit']=$options['limit'];
            if(isset($options['skip'])) $cmd['skip']=$options['skip'];

            $rs=$this->conn->executeReadCommand($this->db, new \MongoDB\Driver\Command($cmd));
            foreach($rs as $r){
                yield json_decode(json_encode($r), true);
            }
        }
    }
}
?>
