<?php  
	class Clue_Tool{
		static function uuid(){
			return sha1(getmypid().uniqid(rand()).@$_SERVER['SERVER_NAME']);
		}
		
		static function nocache(){
			header( 'Cache-Control: no-store, no-cache, must-revalidate' );
			header( 'Cache-Control: post-check=0, pre-check=0', false );
			header( 'Pragma: no-cache' );
		}
	}
	
	function REQ($name, $default=false){
		return isset($_REQUEST[$name]) ? $_REQUEST[$name] : $default;
	}
	
	function POST($name, $default=false){
		return isset($_POST[$name]) ? $_REQUEST[$name] : $default;
	}
	
	function GET($name, $default=false){
		return isset($_GET[$name]) ? $_REQUEST[$name] : $default;
	}
	
	function REQS(){
		$n=func_num_args();
		$a=func_get_args();

		$ret=array();
		for($i=0; $i<$n; $i++){
			$name=$a[$i];
			$ret[$name]=isset($_REQUEST[$name]) ? $_REQUEST[$name] : false;
		}
		
		return $ret;
	}
?>
