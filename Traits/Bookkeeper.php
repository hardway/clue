<?php
namespace Clue\Traits;

trait Bookkeeper{
    static private $_trait_bookkeeper_db=null;
    static private $_trait_bookkeeper_table=null;

    static function enable_bookkeeping($db, $table){
        $db=($db instanceof \Clue\Database) ? $db : \Clue\Database::create($db);

        if($db->has_table($table)){
            self::$_trait_bookkeeper_db=$db;
            self::$_trait_bookkeeper_table=$table;
        }
    }

    static function disable_bookkeeping(){
        self::$_trait_bookkeeper_db=null;
        self::$_trait_bookkeeper_table=null;
    }

    static function allow_bookkeeping(){
        return self::$_trait_bookkeeper_db && self::$_trait_bookkeeper_table;
    }

    static function bookkeep($data){
        if(!self::allow_bookkeeping()) return false;
        self::$_trait_bookkeeper_db->insert(self::$_trait_bookkeeper_table, $data);
    }
}
?>
