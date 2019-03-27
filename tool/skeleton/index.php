<?php
    require_once 'stub.php';

    // 支持PHP Built-in Server
    // php -S <SERVER>:<PORT> index.php
    if(php_sapi_name()=='cli-server' && $app->is_static()) return false;

    try{
        $app->run();
    }
    catch(Exception $e){
        error_log("[Exception]".$e->getMessage());
        error_log($e->getTraceAsString());

        $app->error($e->getMessage());
        $app->redirect_return();
    }
