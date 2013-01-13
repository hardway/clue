<?php 
	session_start();
	
	if(file_exists(__DIR__."/clue.phar"))
		require_once 'clue.phar';
	else
		require 'clue/stub.php';
	
    $config=include 'config.php';
    $app->init(array('config'=>$config));

    $router=$app->router;
    include "route.php";

    $app->run();
?>
