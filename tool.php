<?php
/**
 *	琐碎的工具函数和类
 */
namespace Clue{
    // 将XML无损转换为数组(json)
    function xml2ary(\SimpleXMLElement $xml){
        $parser = function (\SimpleXMLElement $xml, array $collection = []) use (&$parser) {
            $nodes = $xml->children();
            $attributes = $xml->attributes();

            if (0 !== count($attributes)) {
                foreach ($attributes as $attrName => $attrValue) {
                    $collection['@attr'][$attrName] = strval($attrValue);
                }
            }

            if (0 === $nodes->count()) {
                $collection['@text'] = strval($xml);
                return $collection;
            }

            foreach ($nodes as $nodeName => $nodeValue) {
                if (count($nodeValue->xpath('../' . $nodeName)) < 2) {
                    $collection[$nodeName] = $parser($nodeValue);
                    continue;
                }

                $collection[$nodeName][] = $parser($nodeValue);
            }

            return $collection;
        };

        return [
            $xml->getName() => $parser($xml)
        ];
    }

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

    /**
     * 执行外部程序 - 使用proc_open
     *
     * @param $options [
     *			cwd 		=> 当前目录
     *			env 		=> 环境变量
     * 			timeout 	=> 超时（若规定时间没有任何输出则强行退出）
     * 		  ]
     */
	function run_command($command, $options=[]){
		$timeout=$options['timeout'] ?: 0;
		$last_output=null;					// 最后输出时间
		$verbose=@$options['verbose'] ?: false;

		$cwd=@$options['cwd'] ?: null;
		$env=@$options['env'] ?: null;
		$desc = array(
		   0 => array("pipe", "r"),
		   1 => array("pipe", "w"),
		   2 => array("pipe", "w")
		);

		if($verbose) error_log("[EXEC] $command");

		$process = proc_open($command, $desc, $pipes, $cwd, $env);

		if (is_resource($process)) {
			fclose($pipes[0]);

			$read = [$pipes[1], $pipes[2]];
			$write = null;
			$except = null;

			while ($read) {
				$c = stream_select($read, $write, $except, 1);
				if($c===false) break;

				foreach($read as $r){
					fputs(STDOUT, fgets($r));
					$last_output=time();    // 更新最后输出时间
				}

				$read=array_filter([$pipes[1], $pipes[2]], function($p){return !feof($p);});

				if($last_output && $timeout && time() - $last_output > $timeout){
					// 超过timeout没有任何输出
					error_log("[CLUE] Proc terminated due to idle timeout: $timeout");
					proc_terminate($process);
					break;
				}
			}

			proc_terminate($process);
			fclose($pipes[1]);
			fclose($pipes[2]);
			$ret = proc_close($process);

			return $ret;
		}
		else{
			throw new Exception("proc_open failed: $command");
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

    function readable_seconds($time, $precision=0){
        $formats = array('hours', 'days');
        $carries=[3600, 24];

        $format='seconds';

        while(!empty($carries)){
            $c=array_shift($carries);
            if($time < $c) break;
            $time /= $c;
            $format=array_shift($formats);
        }
        return round($time, $precision).' '.$format;
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

            if ($zip->open($filename, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)!==TRUE) {
                exit("cannot open <$filename>\n");
            }

            if(!is_dir($folder)) return false;
            $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iter as $path) {
                if($path->getFileName()=='.' || $path->getFileName()=='..') continue;

                if($path->isFile()){
                    $zip->addFile($path, str_replace($folder.'/', '', $path));
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

	    /**
	     * 递归删除文件夹
	     *
	     * @param $dir
	     * @param $remove_base 是否删除原始根目录（$dir）
	     */
	    static function remove_directory($dir, $remove_base=true){
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

			if($remove_base){
				rmdir($dir);
			}

			return true;
	    }

	    static function clear_directory($dir){
	    	self::remove_directory($dir, false);
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

        /**
         * 切换为系统用户user
         */
        function require_user($user){
            if(is_int($user)){
                $uid=$user;
            }
            else{
                $uinfo=posix_getpwnam($user);
                $uid=$uinfo['uid'];
            }

            posix_seteuid($uid);
            if(posix_geteuid()!=$uid) panic("Switch to user $user failed.");
        }

	    # user PHP_OS or php_uname() to get operation system name

        /**
         * 自动加上HTTP缓存标头
         * @param $timestamp 文件最后修改日期
         * @param $etag 文件hash
         * @param $expires 缓存时间（默认30天）
         */
		static function http_auto_cache($timestamp, $etag=null, $expires=30){
			$etag = ($etag ? "$etag-" : ''). $timestamp;
            $expires=is_int($expires) ? time()+86400*$expires : strtotime($expires);

			header("Cache-Control: public");
		    header("Last-Modified: ".gmdate('D, d M Y H:i:s ', $timestamp) . 'GMT');
            header("Expires: ".date("r", $expires));
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

    class Base36 extends BaseCoder{
        static protected $alpha = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    }

	class Base16 extends BaseCoder{
		static protected $alpha = "ABCDEF1234567890";
	}

    class Base62 extends BaseCoder{
        static protected $alpha = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
    }

	class Browser{
		static function is_ie(){
			return strpos(@$_SERVER['HTTP_USER_AGENT'], 'MSIE')!==false;
		}
	}

    // REF: https://github.com/fwolf/uuid.php
    /**
     *  @param $custom 自定义信息（例如服务器编号，客户IP, ...）
     */
    function uuid($custom=null){
        static $TIMESTAMP_OFFSET = 1514764800;    // 2018-01-01
        static $LEN_TIME=9;
        static $LEN_CUSTOM=6;
        static $LEN_RANDOM=5;

        // 时间，秒(6byte) + 毫秒(4bytes)
        list($ms, $sec) = explode(' ', microtime());
        $ms = str_pad(round($ms * 1000000), 6, '0', STR_PAD_LEFT);

        $time_part = base_convert($sec - $TIMESTAMP_OFFSET . $ms, 10, 36);
        $time_part = substr(str_pad($time_part, $LEN_TIME, '0', STR_PAD_LEFT), 0-$LEN_TIME);

        $custom_part = substr(str_pad($custom, $LEN_CUSTOM, '0', STR_PAD_LEFT), 0-$LEN_CUSTOM);

        $random_part =base_convert(round(substr(uniqid('', true), -8) / 2), 10, 36);
        $random_part = substr(str_pad($random_part, $LEN_RANDOM, '0', STR_PAD_LEFT), 0-$LEN_RANDOM);

        $uuid = implode("", [$time_part, $custom_part, $random_part]);

        return $uuid;
    }

    // 安全类方法
    // TODO: REFACTOR 单独放到security或者sanitize文件
    // TODO: 默认要求所有的GET / POST 等方法指明具体的sanitizer否则报错或警告
    // TODO: controller / action (params) 也需要规范（通过heredoc进行？）

    
    /**
     * 确保传入的id_array都是整数，防止SQL注入
     * @param $id_array 字符串或者数组（形如 "1,2,3" 或者 [1, 2, 3]）
     */
    function sanitize_id_array($id_array){
        if(is_string($id_array)){
            $id_array=implode(",", array_filter(array_map('intval', explode(",", $id_array))));
        }
        elseif(is_array($id_array)){
            $id_array=array_filter(array_map("intval", $id_array));
        }

        return $id_array;
    }

    /**
     * 文本
     * 特殊字符被转码
     */
    function sanitize_string($input, $length=null){
        $str=filter_var(trim($input), FILTER_SANITIZE_STRING);
        if($length!==null) $str=mb_substr($str, 0, $length);

        return $str;
    }

    /**
     * 人名、地名
     * 不允许特殊字符
     * 注意，不应反复编码，否则会产生多余字符
     */
    function sanitize_name($input){
        $input=filter_var(trim($input), FILTER_SANITIZE_SPECIAL_CHARS);
        return $input;
    }

    function sanitize_url($input){
        return filter_var(trim($input), FILTER_SANITIZE_URL);
    }

    function sanitize_email($input){
        return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
    }

    function sanitize_telephone($input){
        $phone=preg_replace("/[^0-9\-]/", '', $input);
        return $phone;
    }

    function sanitize_number($input){
        $num=preg_replace("/[^0-9\.+-]/", '', $input);
        return $num;
    }

    /**
     * 敏感数据掩码
     *
     * @param $string 敏感数据
     * @param $format 前后保留的字符和中间的掩码字符，例如0#4, 3*3
    */
    function mask_string($string, $format="3*3"){
        preg_match("/^(\d+)(.)(\d+)$/", $format, $fmt) ?: panic("Invalid mask format: $format");
        $left=substr($string, 0, $fmt[1]);
        $right=substr($string, 0 - intval($fmt[3]));
        $masked=str_repeat($fmt[2], strlen($string) - strlen($left) - strlen($right));

        return $left.$masked.$right;
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

		return isset($_GET[$name]) ? str_replace('+', ' ', $_GET[$name]) : $default;
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
		function panic($message, $code=null){
		    throw new \Exception($message, $code);
		}
	}

    function collect($items){
        return new Clue\Collection($items);
    }

    /**
     * 强制转换对象类型
     */
    function cast($class, $obj){
        $c=unserialize(
            preg_replace(
                '/^O:\d+:"[^"]++"/',
                'O:'.strlen($class).':"'.$class.'"',
                serialize($obj)
            )
        );

        // 必须是继承关系
        if(!is_subclass_of($obj, $class) && !is_subclass_of($c, get_class($obj))){
            panic("Cast classes not related is not allowed.");
        }

        return $c;
    }

    /**
     * 仅返回key在fields中的结果
     */
    function array_include($ary, $fields){
        if(version_compare(PHP_VERSION, '5.6.0') >=0){
            return array_filter($ary, function($f) use($fields){return in_array($f, $fields);}, ARRAY_FILTER_USE_KEY);
        }
        else{
            foreach($ary as $k=>$v){
                if(!in_array($k, $fields)){
                    unset($ary[$k]);
                }
            }
            return $ary;
        }
    }

    /**
     * 剔除key在fields中的结果
     */
    function array_exclude($ary, $fields){
        if(version_compare(PHP_VERSION, '5.6.0') >=0){
            return array_filter($ary, function($f) use($fields){return !in_array($f, $fields);}, ARRAY_FILTER_USE_KEY);
        }
        else{
            foreach($ary as $k=>$v){
                if(in_array($k, $fields)){
                    unset($ary[$k]);
                }
            }
            return $ary;
        }
    }

    /**
     * 日期格式化
     */
    if(!function_exists('format_datetime')){
        function format_datetime($datetime, $format){
            if($datetime=='now') $datetime=time();
            if(empty($datetime)) return null;

            if(is_numeric($datetime))
                return date($format, $datetime);
            else
                return date($format, strtotime($datetime));
        }
    }

    if(!function_exists('ansi_date')){
        function ansi_date($date='now'){ return format_datetime($date, "Y-m-d"); }
    }

    if(!function_exists('ansi_time')){
        function ansi_time($date='now'){ return format_datetime($date, "H:i:s"); }
    }

    if(!function_exists('ansi_datetime')){
        function ansi_datetime($datetime='now'){ return format_datetime($datetime, "Y-m-d H:i:s"); }
    }

    // JSON5
    include_once __DIR__.'/vendor/json5.php';
}
