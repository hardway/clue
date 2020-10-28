<?php
namespace Clue;

class MongoRecord extends ActiveRecord{
    static protected $_db;

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
        else if(isset($GLOBALS['db'])){
            return static::$_db=$GLOBALS['db'];
        }
        else
            return null;
    }

    static function distinct($field, array $query=null){
        $model=self::model();
        return self::db()->distinct($model['table'], $field, $query);
    }

    static function get($id){
        $model=self::model();
        $pkey=$model['columns'][$model['pkey']]['name'];

        $row=self::db()->get_row($model['table'], ["$pkey"=>$id]);
        if($row){
            $class=get_called_class();
            $r=new $class($row);
            // $r->_snap_shot();
            $r->after_retrieve();

            return $r;
        }
        else
            return false;
    }

    static function iterate($cond=[], $option=[]){
        $class=get_called_class();

        $iter=self::db()->iterate_results(self::model()['table'], $cond, [], $option);
        foreach($iter as $r){
            $r=new $class($r);
            // $r->_snap_shot();
            $r->after_retrieve();

            yield $r;
        }
    }

    static function find($cond=[], $option=[]){
        return iterator_to_array(self::iterate($cond, $option));
    }

    function save(){
        // 验证业务逻辑是否允许保存
        if(!$this->validate()) return false;

        // $old_data=$this->_snap;
        // $dirty_data=[];
        // foreach($model['columns'] as $c=>$m){
        //     if(isset($m['readonly']) || @$this->_snap[$c]===$this->$c) continue;

        //     $dirty_data[$c]=$this->$c;
        // }

        // if(!$this->before_save($dirty_data)) return false;

        $obj=$this->to_array();

        $model=self::model();
        $ok=self::db()->replace($model['table'], $obj);

        // $this->_snap_shot();
        // $this->after_save($this->_snap, $old_data);

        return $ok;
    }
}
