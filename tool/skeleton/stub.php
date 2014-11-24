<?php
    define('APP_ROOT', __DIR__);

    require realpath(__DIR__."/clue.phar") ?: 'clue/stub.php';

    // Add path if needed
    //
    // Clue\add_site_path(APP_ROOT.'/some-site-file-folder');
    // Clue\add_class_path(APP_ROOT.'/some-external-library');

    // Start Engine
    $config=include Clue\site_file("config.php");
    $app=new Clue\Application(array('config'=>$config));
    $db=$app['db']['default'];
