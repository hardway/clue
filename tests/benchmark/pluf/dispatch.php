<?php  
    require_once 'pluf/Pluf.php';
    require_once 'pluf/Pluf/HTTP/URL.php';
    
    class Pluf_Dispatch_Test{
        function hello_world(){
            echo "Hello World.";
            exit();
        }
    }
    
    Pluf::start("pluf.test");

    $GLOBALS['_PX_views']=array(
        array('regex' => '#^/did/not/exist/$#',
               'base' => '/',
               'model' => 'Did_Not_Exist',
               'method' => 'hello_world'),
        array('regex' => '#^/another/one/(.*)/$#',
               'base' => '/',
               'model' => 'Another_One',
               'method' => 'hello_world'),
        array('regex' => '#^.*$#',
               'base' => '/',
               'model' => 'Pluf_Dispatch_Test',
               'method' => 'hello_world')
    );
    
    Pluf_Dispatcher::dispatch();
?>
