<?php
/**
 * 只支持PHP7
 */
namespace Clue\Database{
    class MongoDB extends \Clue\Database{
        protected $_result;

        function __construct(array $param){
            if(!extension_loaded('mongodb')) throw new \Exception(__CLASS__.": extension mongodb is missing!");

            $conn_str=sprintf("mongodb://%s:%s", $param['host']??'localhost', $param['port']??27017);
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
            if(isset($doc['_id']['$oid'])) $doc['_oid']=new \MongoDB\BSON\ObjectId($doc['_id']['$oid']);

            $cmd=[
                'insert'=>$collection,
                'documents'=>[$doc]
            ];

            $r=$this->command($cmd, 'write', 'row');

            if(@$r->writeErrors) foreach($r->writeErrors as $e){
                $this->setError(['code'=>$e->code, 'error'=>$e->errmsg]);
            }

            return $r->ok ? @$doc['_id'] : false;
        }

        function replace($collection, $doc){
            if(isset($doc['id']) && !isset($doc['_id'])) $doc['_id']=$doc['id'];
            if(isset($doc['_id']['$oid'])) $doc['_id']=new \MongoDB\BSON\ObjectId($doc['_id']['$oid']);

            $cmd=[
                'update'=>$collection,
                'updates'=>[
                    [
                        'q'=>['_id'=>$doc['_id']],
                        'u'=>$doc,
                        'upsert'=>true
                    ]
                ]
            ];

            $r=$this->command($cmd, 'write', 'row');

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

            $r=$this->command($cmd, 'write', 'row');

            return $r->ok;
        }

        function delete($collection, $query=[]){
            $cmd=[
                'delete'=>$collection,
                'deletes'=>[
                    ['q'=>$query ?: new \stdClass(), 'limit'=>0]
                ]
            ];

            $r=$this->command($cmd, 'write', 'row');

            return $r->ok;
        }

        function count($collection, $query=[]){
            if(empty($query)) $query=null;

            $cmd=[
                'count'=>$collection,
                'query'=>$query
            ];

            $r=$this->command($cmd, 'read', 'row');

            return $r->n;
        }

        /**
         * 执行服务端查询/修改命令
         * @param $func 执行方式: default | read | write
         * @param $return 返回风格: row | result | iterator
         */
        function command($cmd, $func="executeCommand", $return="row"){
            if(!$cmd instanceof \MongoDB\Driver\Command){
                $sql=json_encode($cmd);
                $cmd=new \MongoDB\Driver\Command($cmd);
            }
            else{
                $sql="Compiled MongoDB Command";
            }

            $funcMap=[
                'default'=>'executeCommand',
                'read'=>'executeReadCommand',
                'write'=>'executeWriteCommand',
            ];
            $func=$funcMap[$func];

            $t_begin=microtime(true);
            $rs=$this->conn->$func($this->db, $cmd);
            $t_end=microtime(true);

            $this->audit($sql, $t_end - $t_begin, "TODO: LOCATION");

            switch($return){
                case 'row':
                    return $rs->toArray()[0] ?? null;
                    break;

                case 'result':
                    return $rs->toArray();
                    break;

                case 'iterator':
                default:
                    return $rs;
            }
        }

        function exec($cmd){
            return $this->command($cmd, 'default', 'row');
        }

        function distinct($collection, $field, $query=null){
            $cmd=[
                'distinct'=>$collection,
                'key'=>$field,
                'query'=>$query
            ];

            $r=$this->command($cmd, 'default', 'row');
            return $r->values;
        }

        /**
         * @param $collection
         * @param $stage 第一个管道（可依次增加更多）
         */
        function aggregate($collection, $stage){
            $cmd=[
                'aggregate'=>$collection,
                'pipeline'=>array_slice(func_get_args(), 1),
                'cursor'=>['batchSize'=>1000]
            ];

            $rs=$this->command($cmd, 'read');
            return json_decode(json_encode($rs), true);
        }

        function group_count($collection, $group_field){
            $rs=$this->aggregate($collection, [
                '$group'=>[
                    '_id'=>'$'.$group_field,
                    'val'=>['$sum'=>1]
                ]
            ]);

            $cnt=[];
            foreach($rs as $r){
                $cnt[$r['_id']]=$r['val'];
            }

            return $cnt;
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

        function get_row($collection, $query=[], $fields=[], $options=[]){
            $cmd=[
                'find'=>$collection,
                'limit'=>1
            ];
            if($query) $cmd['filter']=$query;
            if($fields) $cmd['projection']=$fields;
            if(isset($options['sort'])) $cmd['sort']=$options['sort'];

            $r=$this->command($cmd, 'read', 'row');

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
            if(isset($options['sort'])) $cmd['sort']=$options['sort'];

            $rs=$this->command($cmd, 'read', 'iterator');
            foreach($rs as $r){
                yield json_decode(json_encode($r), true);
            }
        }
    }
}
?>
