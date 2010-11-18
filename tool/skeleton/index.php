<?php 
    if(file_exists(__FILE__.".local")) require_once __FILE__.'.local';
    
	require_once 'clue/core.php';
	
	Clue_UI_Skin::load('simple');
	Clue_Application::init('.', array('config'=>new Clue_Config(/* Config file location */)));

	$router=Clue_Application::router();

	$router->connect('/:controller/:action');
	$router->connect('/:controller');
	
	app()->prepare();
	app()->dispatch();
?>
