<?php
    require_once dirname(__DIR__).'/stub.php';
    require_once "constructor.php";

    $ctor=new Clue\Tool\Constructor();

    $command=isset($argv[1]) ? $argv[1] : "help";

    if(method_exists($ctor, $command)){
        $ctor->command=$command;
        try{
            call_user_func_array(array($ctor, $command), array_slice($argv, 2));
        }
        catch(Exception $e){
            echo $e->getMessage()."\n";
        }
    }
    else{
        echo "Unknown command: $command\n";
    }
?>
