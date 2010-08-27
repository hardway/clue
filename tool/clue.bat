@goto php
<?php    
    require_once 'clue/core.php';
    
    $ctor=new Clue_Tool_Constructor();
    
    $command=isset($argv[1]) ? $argv[1] : "help";
    
    if(method_exists($ctor, $command)){
        call_user_func_array(array($ctor, $command), array_slice($argv, 2));
    }
    else{
        echo "Unknown command: $command\n";
    }
?>
<?php exit(); ?>
:php
@php -f %~dp0%~n0.bat %*
