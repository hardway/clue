<?php
require_once dirname(__DIR__).'/stub.php';

use \Clue\Logger;
use \Clue\Guard;

$cmd=new Clue\CLI\Command("Guard Example", 'example');
$cmd->add_global('verbose', 'Verbose');
$cmd->add_global('debug', 'Debug');
$cmd->add_global('dry', 'Dry Run');
$cmd->handle($argv);

/**
 * CLI模式
 */
function example_cli(){
    $g=new Guard();
    $foo=['bar'=>1];
    $g->debug($foo);

    $f=new ClassThatDoesNotExist();
}

/**
 * Web模式
 */
function example_web(){
    $file="/tmp/example_web.php";
    $script=<<<'EOS'
        <?php
            require "clue/stub.php";

            $g=new Clue\Guard();
            $foo=['bar'=>1];
            $g->debug($foo);

            $f=new ClassThatDoesNotExist();
    EOS;

    file_put_contents($file, $script);
    exec("open http://localhost:8080/");
    exec("php -S localhost:8080 $file");
    @unlink($file);
}
