<?php  
    require_once 'clue/core.php';
    
    class Dispatch_TestController{
        function hello_world(){
            echo "Hello World.";
            exit();
        }
    }
    
    Clue_Application::init('.', array('config'=>new Clue_Config("config.php")));
    
    app()->router()->connect('^/did/not/exist/$', array('controller'=>'Did_Not_Exist', 'action'=>'hello_world'));
	app()->router()->connect('^/another/one$', array('controller'=>'Another_One', 'action'=>'hello_world'));
	app()->router()->connect('/:anything', array('controller'=>'Dispatch_Test', 'action'=>'hello_world'));
	
    app()->run();
?>
