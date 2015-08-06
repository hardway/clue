<?php
/**
 *	琐碎的工具函数和类
 */
namespace Clue{
	# 将array转换为object
    function ary2obj($array) {
        if(!is_array($array)) {
            return $array;
        }

        $object = new \stdClass();
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

    function readable_bytes($size){
        $format = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
        $pos = 0;

        while ($size >= 1024) {
            $size /= 1024;
            $pos++;
        }
        return round($size, 2).' '.$format[$pos];
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

    /**
     * Download remote image
     *
     * @access public
     * @param {string} remote url to the image
     * @param {string} $path to save the image including the image name
     * return void
     */
    function download_remote_image($url, $path){
    	$ch = curl_init($url);
    	curl_setopt($ch, CURLOPT_HEADER, 0);
    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    	curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);

    	$raw_image_data = curl_exec($ch);
    	curl_close ($ch);

    	if( ! file_exists($path)){
    		@mkdir(dirname($path), 0775, true);
    	}else {
    		unlink($path);
    	}

    	$fp = fopen($path,'x');
    	fwrite($fp, $raw_image_data);

    	fclose($fp);
    }

	class Tool{
        // REF: https://gist.github.com/tylerhall/521810
        static function random_password($length = 9, $add_dashes = false, $available_sets = 'luds')
        {
            $sets = array();
            if(strpos($available_sets, 'l') !== false)
                $sets[] = 'abcdefghjkmnpqrstuvwxyz';
            if(strpos($available_sets, 'u') !== false)
                $sets[] = 'ABCDEFGHJKMNPQRSTUVWXYZ';
            if(strpos($available_sets, 'd') !== false)
                $sets[] = '23456789';
            if(strpos($available_sets, 's') !== false)
                $sets[] = '!@#$%&*?';

            $all = '';
            $password = '';
            foreach($sets as $set)
            {
                $password .= $set[array_rand(str_split($set))];
                $all .= $set;
            }

            $all = str_split($all);
            for($i = 0; $i < $length - count($sets); $i++)
                $password .= $all[array_rand($all)];

            $password = str_shuffle($password);

            if(!$add_dashes)
                return $password;

            $dash_len = floor(sqrt($length));
            $dash_str = '';
            while(strlen($password) > $dash_len)
            {
                $dash_str .= substr($password, 0, $dash_len) . '-';
                $password = substr($password, $dash_len);
            }
            $dash_str .= $password;
            return $dash_str;
        }

        static function compress_zip($folder, $filename){
            $zip = new \ZipArchive();

            if ($zip->open($filename, \ZipArchive::CREATE)!==TRUE) {
                exit("cannot open <$filename>\n");
            }

            if(!is_dir($folder)) return false;
            $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iter as $path) {
                if($path->getFileName()=='.' || $path->getFileName()=='..') continue;

                if($path->isFile()){
                    $zip->addFile($path, str_replace($folder, '', $path));
                }
            }

            $zip->close();
        }

        static function decompress_zip($filename, $folder){
            $zip = new \ZipArchive();

            $zip->open($filename);
            $zip->extractTo($folder);
            $zip->close();
        }

	    # 递归删除文件夹
	    static function remove_directory($dir){
	    	if(!is_dir($dir)) return false;
			$iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir), \RecursiveIteratorIterator::CHILD_FIRST);

			foreach ($iter as $path) {
				if($path->getFileName()=='.' || $path->getFileName()=='..') continue;

				if ($path->isDir()) {
					rmdir($path);
				} else {
					unlink($path);
				}
			}

			rmdir($dir);

			return true;
	    }

	    # 递归复制文件夹
	    static function copy_directory($src, $dest, $mode=null){
	    	if(!is_dir($src)) return false;

	    	if(!is_dir($dest)){
	    		@mkdir($dest, $mode ?: (fileperms($src) & 0777), true);
	    		$dest=realpath($dest);
	    		if(!is_dir($dest)) return false;	// 无法创建目标文件夹
	    	}

			$iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src), \RecursiveIteratorIterator::SELF_FIRST);

			foreach ($iter as $path) {
				if($path->getFileName()=='.' || $path->getFileName()=='..') continue;

				$target=str_replace($src, $dest, $path);
				if ($path->isDir()) {
					if(!is_dir($target))
						mkdir($target, $mode ?: (fileperms($path) & 0777));
				} else {
					copy($path, $target);
				}
			}

			return true;
	    }

	    # user PHP_OS or php_uname() to get operation system name

		static function http_auto_cache($timestamp, $etag=null){
			$etag = ($etag ? "$etag-" : ''). $timestamp;

			header("Cache-Control: public");
		    header("Last-Modified: ".gmdate('D, d M Y H:i:s ', $timestamp) . 'GMT');
		    header("ETag: $etag");

			$if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) : false;
			$if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? $_SERVER['HTTP_IF_NONE_MATCH'] : false;
			if ($if_none_match == $etag  || (!$if_none_match && $timestamp == $if_modified_since))
			{
            	header('Not Modified', true, 304);
			    exit();
			}
		}

		static function http_no_cache(){
			header('Cache-Control: no-store, no-cache, must-revalidate');
			header('Cache-Control: post-check=0, pre-check=0', false);
			header('Pragma: no-cache');
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

	class BaseCoder{
		static protected $alpha="0123456789";

		static function decode($s){
			$rv=$pos=0;
			$alen=strlen(static::$alpha);

			for($i=strlen($s)-1; $i>=0; $i--){
				$c=$s[$i];
				$rv+=strpos(static::$alpha, $c) * pow($alen, $pos);
				$pos++;
			}

			return $rv;
		}

		static function encode($num){
			$rv = "";
			$alen=strlen(static::$alpha);
			while($num!=0){
				$rv = static::$alpha[$num % $alen] . $rv;
				$num = floor($num/$alen);
			}
			return $rv;
		}
	}

	class Base32 extends BaseCoder{
		static protected $alpha = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
	}

	class Base16 extends BaseCoder{
		static protected $alpha = "ABCDEF1234567890";
	}

	class Browser{
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

    function SESSION($name=null, $default=false){
        return isset($_SESSION[$name]) ? $_SESSION[$name] : $default;
    }

    function COOKIE($name=null, $default=false){
        return isset($_COOKIE[$name]) ? $_COOKIE[$name] : $default;
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

	if(!function_exists('panic')){
		function panic($message){
		    throw new Exception($message);
		}
	}
}
?>
