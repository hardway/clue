<?php
namespace Clue\Web{
	// TODO md5 collision in mind
	class Clue_Creeper_Cache{
		private $cacheDir;

		function __construct($cacheDir){
			// Make sure the cache directory exists
			if(!file_exists($cacheDir)){
				mkdir($cacheDir);
				if(!file_exists($cacheDir)){
					throw new Exception("Cache directory didn't exist and can't be created: $cacheDir");
				}
			}
			$this->cacheDir=$cacheDir;
		}

		private function _cache_folder($hash){
			$folder=$this->cacheDir;
			foreach(array(substr($hash, 0, 3), $hash) as $f){
				$folder=$folder . '/' . $f;
				if(!file_exists($folder)) mkdir($folder);
			}
			return $folder;
		}

		function get($url){
			$folder=$this->_cache_folder(md5($url));

			$file="$folder/raw";

			if(file_exists($file))
				return file_get_contents($file);
			else
				throw new Exception("Cache file missing: $url");

			return false;	// Cache missed
		}

		function put($url, $content){
			$folder=$this->_cache_folder(md5($url));

			// Save URL infomation
			if(file_exists("$folder/url")){
				$urlResidents=explode("\n", trim(file_get_contents("$folder/url")));
				if(!in_array($url, $urlResidents)){
					throw new Exception("URL Collision detected! $url");
				}
			}
			else{
				file_put_contents("$folder/url", $url);
			}

			// Save RAW content
			file_put_contents("$folder/raw", $content);
		}

		function timestamp($url){
			$file=$this->_cache_folder(md5($url)) . "/raw";

			return file_exists($file) ? filemtime($file) : 0;
		}
	}

	class Client{
		public $responseHeader;
		public $content;
		public $agent="ClueHTTPClient";

		private $cache;
		private $cacheTTL=86400;	// Default is 24 Hours
		private $cachedb;

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

			$cacheDir=getenv('creeper_cache');
			if($cacheDir) $this->enable_cache($cacheDir);

			$http_proxy=getenv('http_proxy');
			if($http_proxy){
				list($proxy, $port)=explode(":", $http_proxy);
				curl_setopt($this->curl, CURLOPT_PROXY, $proxy);
				curl_setopt($this->curl, CURLOPT_PROXYPORT, $port);
			}

			curl_setopt($this->curl, CURLOPT_USERAGENT, $this->agent);

			if(isset($config['cookie'])){
				curl_setopt($this->curl, CURLOPT_COOKIEJAR, $config['cookie']);	// write
				curl_setopt($this->curl, CURLOPT_COOKIEFILE, $config['cookie']);// read
			}
		}

		function __destruct(){
			curl_close($this->curl);
		}

		function __get($name){
			if($name=='status'){
				return curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
			}
		}

		function enable_cache($cacheDir){
			$this->cache=new Clue_Creeper_Cache($cacheDir);
		}

		function disable_cache(){
			$this->cache=null;
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

		function set_cookie($cookies=array()){
			$pair=array();
			foreach($cookies as $k=>$v){
				$pair[]="$k=$v";
			}
			curl_setopt($this->curl, CURLOPT_COOKIE, implode("; ", $pair));
		}

		function set_agent($agent){
			$this->agent=$agent;
			curl_setopt($this->curl, CURLOPT_USERAGENT, $this->agent);
		}

		function get($url, $forceReload=false){
			$this->open($url, $forceReload);
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

			// check cache
			$t=time();

			if(!$this->cache || $this->cacheTTL < $t - $this->cache->timestamp($url) || $forceRefresh){ // cache outdated.
				//$this->content= $this->cache ? $this->cache->get($url) : false;
				// echo "Creeping $url\n";
				curl_setopt($this->curl, CURLOPT_URL, $url);
				curl_setopt($this->curl, CURLOPT_POST, false);
				curl_setopt($this->curl, CURLOPT_HEADER, true);
				curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);

				$this->_parse_response(curl_exec($this->curl));
			    // TODO: check curl_errno

				if($this->cache)
					$this->cache->put($url, $this->content);
			}
			else{
				$this->content=$this->cache->get($url);
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
