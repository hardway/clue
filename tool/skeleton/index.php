<?php
	require realpath(__DIR__."/clue.phar") ?: 'clue/stub.php';

	$config=include "config.php";

    $app=new Clue\Application(array(
    	'config'=>$config
    ));
    $db=$app['db']['default'];

    $app->run();

