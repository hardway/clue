<?php 
	session_start();
	
	if(file_exists(__DIR__."/clue.phar"))
		require_once 'clue.phar';
	else
		require 'clue/stub.php';
	
    $config=require_once __DIR__.'/config.php';
    $app->init(array('config'=>$config));

    $router=$app->router;
    $router->connect('/:controller/:action');
    $router->connect('/:controller');

    $app->run();
?>
