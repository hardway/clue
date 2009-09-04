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
	
	class Clue_Upload{
		protected $file;
		function __construct($name){
			$this->file=isset($_FILES[$name]) ? $_FILES[$name] : false;
		}
		
		function isValid(){
			// TODO: 使用选项检测文件类型
			
			if(!is_array($this->file)) return false;
			if(empty($this->file["tmp_name"])) return false;
			if(intval($this->file["size"])==0) return false;
			
			return true;
		}
		
		function save($position){
			if(!is_array($this->file)) return false;
			
			$target= $this->file["name"];
			$target= is_dir($position) ? $position . DIRECTORY_SEPARATOR . $target : $position;
			
			$source=$this->file["tmp_name"];
			
			return move_uploaded_file($source, $target);
		}
	}
	
	class Clue_Base62{
		private $alpha = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
		private $alen = 62;
		
		function decode($s){			
			$rv=$pos=0;
			
			for($i=strlen($s)-1; $i>=0; $i--){
				$c=$s[$i];
				$rv+=strpos($this->alpha, $c) * pow($this->alen, $pos);
				$pos++;
			}
			
			return $rv;
		}
	
		function encode($num){
			$rv = "";
			while($num!=0){
				$rv = $this->alpha[$num % $this->alen] . $rv;
				$num = floor($num/$this->alen);
			}
			return $rv;
		}
	}
	
	function REQ($name, $default=false){
		return isset($_REQUEST[$name]) ? $_REQUEST[$name] : $default;
	}
	
	function POST($name=null, $default=false){
		if(! isset($name)) return ($_SERVER['REQUEST_METHOD']=='POST');

		return isset($_POST[$name]) ? $_POST[$name] : $default;
	}
	
	function GET($name=null, $default=false){
		if(! isset($name)) return ($_SERVER['REQUEST_METHOD']=='GET');
		
		return isset($_GET[$name]) ? $_GET[$name] : $default;
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
