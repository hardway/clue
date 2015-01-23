<?php
namespace Clue\Logger;
class DB extends Syslog{
    function __construct($db_or_config, $table){
        if($db_or_config instanceof Clue\Database)
            $this->db=$db_or_config;
        else
            $this->db=\Clue\Database::create($db_or_config);

        $this->table=$table;
    }

    function write($data){
        $this->db->insert($this->table, $data);
    }
}
