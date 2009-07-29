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
		
		static function format2HTML($comment, $preservSpace=false){
			$str=$comment;
			
			$str=str_replace(
				array("&", "<", ">", "\n", "\""),
				array("&amp;", "&lt;", "&gt", "<br/>", "&quot;"),
				$str);
				
			if($preservSpace){
				$str=str_replace(" ", "&nbsp;", $str);
			}
			return $str;
		}
	}
	
	function REQ($name, $default=false){
		return isset($_REQUEST[$name]) ? $_REQUEST[$name] : $default;
	}
	
	function POST($name=null, $default=false){
		if(! isset($name)) return ($_SERVER['REQUEST_METHOD']=='POST');

		return isset($_POST[$name]) ? $_REQUEST[$name] : $default;
	}
	
	function GET($name=null, $default=false){
		if(! isset($name)) return ($_SERVER['REQUEST_METHOD']=='GET');
		
		return isset($_GET[$name]) ? $_REQUEST[$name] : $default;
	}
	
	function SESSION($name, $default=false){
		@session_start();
		return isset($_SESSION[$name]) ? $_SESSION[$name] : $default;
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
