<?php
namespace Clue\Logger;

class DB implements Logger{
    function __construct($db, $table){
        if($db instanceof Clue\Database)
            $this->db=$db;
        else
            $this->db=\Clue\Database::create($db);

        $this->table=$table;
    }

    function write($data){
        $this->db->insert($this->table, $data);
    }
}
