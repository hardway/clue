<?php
    require_once 'stub.php';

    // 支持PHP Built-in Server
    // php -S <SERVER>:<PORT> index.php
    if(php_sapi_name()=='cli-server' && $app->is_static()) return false;

    $app->run();

