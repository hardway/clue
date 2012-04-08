<?php  
namespace Clue{
    class Session{
        function __construct(){
            if(!session_id()) session_start();
        }
        
		function put($name, $value){
		    $_SESSION[$name]=$value;
		}
		
		function get($name){
		    return isset($_SESSION[$name]) ? $_SESSION[$name] : "";
		}
		
		function take($name){
		    $value=$this->get($name);
		    unset($_SESSION[$name]);
		    return $value;
		}
    }
}
?>
