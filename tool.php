<?php  
	class Clue_Tool{
		static function os(){
			if(isset($_SERVER['OS'])){
				$os=strtolower($_SERVER['OS']);
				if(strpos($os, 'windows')===0){
					return 'windows';
				}
			}
			else if(isset($_SERVER['WINDIR'])){
				return 'windows';
			}
			
			return "unknown";
		}
		
		/**
		 * Create directory recursively, without warning
		 */
		static function mkdir($dir){
			$dir=str_replace("\\", '/', $dir);
			
			$ds=null;
			foreach(explode('/', $dir) as $seg){
				$ds=empty($ds) ? $seg : $ds.'/'.$seg;
				if(!file_exists($ds)){
					mkdir($ds);
				}
			}			
		}
		
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
		
		public $name;
		
		function __construct($name){
			$this->file=isset($_FILES[$name]) ? $_FILES[$name] : false;
			$this->name=is_array($this->file) ? $this->file['name'] : false;
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
	
	class Clue_BaseCoder{
		protected $alpha="0123456789";
		protected $alen = 10;
		
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
	
	class Clue_Base36 extends Clue_BaseCoder{
		protected $alpha = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
		protected $alen = 36;
	}
	
	class Clue_Base62 extends Clue_BaseCoder{
		protected $alpha = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
		protected $alen = 62;
	}
	
	class Clue_Browser{
		static function is_ie(){
			return strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE')!==false;
		}
	}
	
	//////////////////////////////////////
	// Shortcuts
	//////////////////////////////////////
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
	
	if (!function_exists('lc')) {
		require_once 'clue/tool/lc.php';
		
		function lc($expression, $Data = array()) {
			return ListComprehension::execute($expression, $Data);
		}
	}


?>
