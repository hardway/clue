<?php
namespace Clue\Web{
	/* Storage layout
		CACHE
			\_ A867 								Hash Prefix
				\_ A86798348c8j8x230k99				Hash
					\_ meta 						unserizlied data
					|_ 20130413113412				Content version

	 	META=array(
	 		'url'=>'http://www.google.com',
			'revisions'=>array(
				'yyyymmddhhmmss', 'yyyymmddhhmmss', ...
			)
	 	)
	*/
	class CacheStore{
		private $cache_dir;
		private $cache_ttl;

		function __construct($cache_dir, $cache_ttl=86400){
			// Make sure the cache directory exists
			if(!is_dir($cache_dir)){
				@mkdir($cache_dir, 0775, true);
				if(!is_dir($cache_dir)){
					throw new Exception("Cache directory didn't exist and can't be created: $cache_dir");
				}
			}
			$this->cache_dir=$cache_dir;
			$this->cache_ttl=$cache_ttl;
		}

		private function _cache_folder($url){
			$hash=md5($url);

			return sprintf("%s/%s/%s", $this->cache_dir, substr($hash, 0, 4), $hash);
		}

		function get($url){
			$folder=$this->_cache_folder($url);

			if(!is_dir($folder) || !file_exists("$folder/meta")) return false;

			$meta=unserialize(file_get_contents("$folder/meta"));
			$rev=end($meta['revisions']);

			if(!file_exists("$folder/$rev")) return false;

			$outdated=filemtime("$folder/$rev")+$this->cache_ttl < time();
			if($outdated) return false;

			$gzcontent=file_get_contents("$folder/$rev");
			return  gzinflate(substr($gzcontent,10,-8));
		}

		function put($url, $content){
			$folder=$this->_cache_folder($url);

			if(!is_dir($folder)) mkdir($folder, 0775, true);

			if(file_exists("$folder/meta")){
				$meta=unserialize(file_get_contents("$folder/meta"));
				$rev=end($meta['revisions']);
				$old_hash=md5_file("$folder/$rev");
				$new_hash=md5($content);

				$save=$old_hash!=$new_hash;
				if(!$save) touch("$folder/$rev");
			}
			else{
				$meta=array(
					'url'=>$url,
					'revisions'=>array()
				);

				$save=true;
			}

			if($save){
				$rev=date("Ymdhis");
				file_put_contents("$folder/$rev", gzencode($content));
				$meta['revisions'][]=$rev;

				file_put_contents("$folder/meta", serialize($meta));
			}
		}
	}

	class Client{
		public $responseHeader;
		public $content;
		public $agent="ClueHTTPClient";

		private $cache;

		private $curl;
		private $history=array();

		/**
		 * Example of config file:
		 *
		 * $config=array(
		 * 		'cookie'=>'curl.cookie'
		 * )
		*/

		function __construct($config=array()){
			$this->curl=curl_init();
			curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, 15);
			curl_setopt($this->curl, CURLOPT_TIMEOUT, 60);

			$http_proxy=getenv('http_proxy');
			if($http_proxy){
				list($proxy, $port)=explode(":", $http_proxy);
				curl_setopt($this->curl, CURLOPT_PROXY, $proxy);
				curl_setopt($this->curl, CURLOPT_PROXYPORT, $port);
			}

			curl_setopt($this->curl, CURLOPT_USERAGENT, $this->agent);
		}

		function __destruct(){
			curl_close($this->curl);
		}

		function __get($name){
			if($name=='status'){
				return curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
			}
		}

		function enable_cache($cache_dir, $cache_ttl=86400){
			$this->cache=new CacheStore($cache_dir, $cache_ttl);
		}

		function disable_cache(){
			$this->cache=null;
		}

		function enable_cookie($cookie_file){
			curl_setopt($this->curl, CURLOPT_COOKIEJAR, $cookie_file);	// write
			curl_setopt($this->curl, CURLOPT_COOKIEFILE, $cookie_file);	// read
		}

		function set_cookie($cookies=array()){
			$pair=array();
			foreach($cookies as $k=>$v){
				$pair[]="$k=$v";
			}
			curl_setopt($this->curl, CURLOPT_COOKIE, implode("; ", $pair));
		}


		private function follow_url($base, $url){
			// TODO: unittest
			if(preg_match('|(http://[^/]+)([^?#]*)|i', $base, $root)){
				$path=dirname($root[2])."/";
				$root=$root[1];
				if(substr($url, 0, 1)=='/'){
					return $root.$url;
				}
				else if(substr($url, 0, 2)=='..'){
					exit("Don't know how to handle url like ../../, $url");
				}
				else{
					return $root.$path.$url;
				}
			}
		}

		private function visit($url, $save_history=true){
			$url=trim($url);

			// TODO: refactor into method
			// Encode url
			$url=str_replace(" ", "%20", $url);

			// TODO: better way to tell if it's absolute and relative url
			if(substr($url, 0, 7)!='http://'){
				$url=$this->follow_url(end($this->history), $url);
			}

			if($save_history) $this->history[]=$url;
			return $url;
		}

		function set_agent($agent){
			$this->agent=$agent;
			curl_setopt($this->curl, CURLOPT_USERAGENT, $this->agent);
		}

		function get($url){
			$this->open($url);
			return $this->content;
		}

		function post($url, $data){
			curl_setopt($this->curl, CURLOPT_URL, $url);
			curl_setopt($this->curl, CURLOPT_POST, true);
			curl_setopt($this->curl, CURLOPT_HEADER, true);
			curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);

			$formData=array();
			foreach($data as $k=>$v){ $formData[]="$k=$v";}
			$formData=implode("&", $formData);

			curl_setopt($this->curl, CURLOPT_POSTFIELDS, $formData);
			$this->_parse_response(curl_exec($this->curl));

			// TODO: check curl_errno
		}

		function download($url, $dest){
			$file=fopen($dest, 'w');
			curl_setopt($this->curl, CURLOPT_FILE, $file);
			curl_setopt($this->curl, CURLOPT_POST, false);
			curl_setopt($this->curl, CURLOPT_URL, $url);
			curl_setopt($this->curl, CURLOPT_HEADER, false);
			curl_setopt($this->curl, CURLOPT_REFERER, end($this->history));

			curl_exec($this->curl);
			// TODO: check curl_errno

			fclose($file);
		}

		function open($url, $forceRefresh=false){
			$url=$this->visit($url);

			$this->content=null;

			// 尝试从cache获取
			if($this->cache){
				$this->content=$this->cache->get($url);
			}

			if(!$this->content){
				curl_setopt($this->curl, CURLOPT_URL, $url);
				curl_setopt($this->curl, CURLOPT_POST, false);
				curl_setopt($this->curl, CURLOPT_HEADER, true);
				curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);

				$this->_parse_response(curl_exec($this->curl));
			    // TODO: check curl_errno

				if($this->cache){
					$this->cache->put($url, $this->content);
				}
			}
		}

		private function _parse_response($response){
			$sep=strpos($response, "\r\n\r\n");

			if(substr($response, 0, 4)=='HTTP' && $sep>0){
				$this->responseHeader=substr($response, 0, $sep);
				$this->content=substr($response, $sep);
			}
			else{
				$this->responseHeader=null;
				$this->content=$response;
			}
		}

		protected function _http_get($url){

		}
	}
}
?>
