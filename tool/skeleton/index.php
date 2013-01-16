<?php 
	session_start();
	
	if(file_exists(__DIR__."/clue.phar"))
		require_once 'clue.phar';
	else
		require 'clue/stub.php';
	
    $app=new Clue\Application(array(
    	'config'=>include 'config.php'
    ));

    include "route.php";
    include "acl.php";

    $app->run();

