<?php
/**
 *	工具函数和类
 */
namespace Clue{
	# 将array转换为object
    function ary2obj($array) {
        if(!is_array($array)) {
            return $array;
        }

        $object = new stdClass();
        if (is_array($array) && count($array) > 0) {
          foreach ($array as $name=>$value) {
             $name = strtolower(trim($name));
             if (!empty($name)) {
                $object->$name = ary2obj($value);
             }
          }
          return $object;
        }
        else {
          return FALSE;
        }
    }

    # 检测浏览器cookie支持
    function cookie_test(){
        if($_COOKIE['cookie_test']=='1'){
            if(isset($_GET['cookie_test'])){
                header("Location: ".str_replace('cookie_test', '', $_SERVER['REQUEST_URI']));
                exit();
            }
            return true;
        }
        else{
            if(strpos($_SERVER['QUERY_STRYING'], 'cookie_test')===FALSE){
                setcookie('cookie_test', '1', time()+86400, '/');
                header("Location: ".$_SERVER['REQUEST_URI'].'?cookie_test');
                exit();
            }

            return false;
        }
    }

	class Tool{
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
}
namespace{
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

	function SERVER($name=null, $default=false){
		return isset($_SERVER[$name]) ? $_SERVER[$name] : $default;
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
}
?>
