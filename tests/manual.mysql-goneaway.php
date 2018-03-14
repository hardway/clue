<?php
    require_once dirname(__DIR__).'/stub.php';
// var_dump(ini_get('mysqli.reconnect'));
// exit();

    $db=Clue\Database::create(['type'=>'mysql', 'host'=>'db.dev', 'username'=>'root', 'password', 'db'=>'mysql']);
    while(true){
        var_dump(join(", ", $db->get_col("show tables")));
        sleep(10);
    }
