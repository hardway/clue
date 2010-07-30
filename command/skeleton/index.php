<?php 
    if(file_exists(__FILE__.".local")) require_once __FILE__.'.local';
    
	require_once 'clue/core.php';
	
	Clue_Application::init('.', array('config'=>new Clue_Config('C:/config/oa.php')));

	$router=Clue_Application::router();

	$router->connect('/:controller/:action');
	$router->connect('/:controller');
	
	app()->prepare();
	app()->dispatch();
?>
