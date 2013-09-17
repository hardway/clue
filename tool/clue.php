<?php
    require_once dirname(__DIR__).'/stub.php';
    require_once "constructor.php";

    $ctor=new Clue\Tool\Constructor();

    $command=isset($argv[1]) ? $argv[1] : "help";
    $subcommand=@$argv[2];

    try{
        if(method_exists($ctor, $command."_".$subcommand)){
            $ctor->command=$command."_".$subcommand;
            call_user_func_array(array($ctor, $command."_".$subcommand), array_slice($argv, 3));
        }
        elseif(method_exists($ctor, $command)){
            $ctor->command=$command;
            call_user_func_array(array($ctor, $command), array_slice($argv, 2));
        }
        else{
            echo "Unknown command: $command\n";
        }
    }
    catch(Exception $e){
        echo $e->getMessage()."\n";
    }
