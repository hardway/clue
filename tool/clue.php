<?php
    require_once dirname(__DIR__).'/stub.php';
    require_once "constructor.php";

    $ctor=new Clue\Tool\Constructor();

    $opt=new Clue\CLI\OptionParser();
    $opt->add_option(['name'=>'version', 'short'=>'-v', 'help'=>'Show version information']);
    $opt->add_option(['name'=>'help', 'short'=>'-h', 'long'=>'--help', 'help'=>'Display this information']);
    list($options, $args)=$opt->parse();

    $ctor->opt=$opt;

    if($options['version']) array_unshift($args, 'version');
    if($options['help']) array_unshift($args, 'help');

    $command=isset($args[0]) ? $args[0] : "help";
    $subcommand=@$args[1];

    try{
        if(method_exists($ctor, $command."_".$subcommand)){
            $ctor->command=$command."_".$subcommand;
            call_user_func_array(array($ctor, $command."_".$subcommand), array_slice($args, 2));
        }
        elseif(method_exists($ctor, $command)){
            $ctor->command=$command;
            call_user_func_array(array($ctor, $command), array_slice($args, 1));
        }
        else{
            echo "Unknown command: $command\n";
        }
    }
    catch(Exception $e){
        echo $e->getMessage()."\n";
    }
