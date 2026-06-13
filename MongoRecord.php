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

    static function count($cond=[]){
        return self::db()->count(self::model()['table'], $cond);
    }

    static function get($id){
        $model=self::model();
        $pkey=$model['columns'][$model['pkey']]['name'];

        $row=self::db()->get_row($model['table'], ["$pkey"=>$id]);
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

    static function find_one($cond=[]){
        $class=get_called_class();
        $r=self::db()->get_row(self::model()['table'], $cond);
        if($r){
            $obj=new $class($r);
            $obj->_snap_shot();
            $obj->after_retrieve();
            return $obj;
        }
        return null;
    }

    static function find_all($cond=[]){
        return self::find($cond);
    }

    static function iterate($cond=[], $option=[]){
        $class=get_called_class();

        $iter=self::db()->iterate_results(self::model()['table'], $cond, [], $option);
        foreach($iter as $r){
            $r=new $class($r);
            $r->_snap_shot();
            $r->after_retrieve();

            yield $r;
        }
    }

    static function find($cond=[], $option=[]){
        return iterator_to_array(self::iterate($cond, $option));
    }

    function save(){
        if(!$this->validate()) return false;

        $model=self::model();

        if($this->id==null){
            $this->id=\Clue\uuid();
        }

        $old_data=$this->_snap;
        $dirty_data=[];
        foreach($model['columns'] as $c=>$m){
            if(isset($m['readonly']) || @$this->_snap[$c]===$this->$c) continue;
            $dirty_data[$c]=$this->$c;
        }

        if(!$this->before_save($dirty_data)) return false;

        $obj=$this->to_array();
        $ok=self::db()->replace($model['table'], $obj);

        $this->_snap_shot();
        $this->after_save($this->_snap, $old_data);

        return $ok;
    }
}
