<?php 
	require_once 'clue/router.php';
	require_once 'clue/activerecord.php';	// TODO: autoload problem, try comment this line to see the error.
	require_once 'clue/database.php';
	require_once 'clue/config.php';
	require_once 'clue/application.php';
	
	Clue_Application::init();
	
	/* Force ACL 
	
	if(Clue_Application::router()->controller()=='login'){
	}
	else{	
		$acl=new ACLHelper();
		$acl->forceLogged();
	}
	
	*/
	
	Clue_Application::run();
?>
