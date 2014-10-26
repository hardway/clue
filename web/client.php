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
					throw new \Exception("Cache directory didn't exist and can't be created: $cache_dir");
				}
			}
			$this->cache_dir=$cache_dir;
			$this->cache_ttl=$cache_ttl;
		}

		private function _cache_folder($url){
			$hash=md5($url);

			return sprintf("%s/%s/%s", $this->cache_dir, substr($hash, 0, 4), $hash);
		}

		function destroy($url){
			$folder=$this->_cache_folder($url);
			foreach(scandir($folder) as $f){
				if(is_file("$folder/$f")) @unlink("$folder/$f");
			}
			return rmdir($folder);
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
		public $header;
		public $status=null;			// 返回的HTTP状态
		public $content;

		public $agent="ClueHTTPClient";
		public $inprivate=false;
		public $history=array();

		private $cache;
		private $http_proxy=false;		// 使用http_proxy会影响后续解析response header

		private $curl;

		/**
		 * Example of config file:
		 *
		 * $config=array(
		 * 		'cookie'=>'curl.cookie'
		 * )
		*/

		function __construct($config=array()){
			$default_config=array(
				'http_proxy'=>getenv("http_proxy"),
				'socks_proxy'=>null,
				'connect_timeout'=>15,
				'timeout'=>60
			);

			$config=array_merge($default_config, $config);

			$this->curl=curl_init();
			curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, $config['connect_timeout']);
			curl_setopt($this->curl, CURLOPT_TIMEOUT, $config['timeout']);

			if($config['http_proxy']){
				list($proxy, $port)=explode(":", $config['http_proxy']);
				// echo "Using proxy server: $proxy, port: $port\n";
				curl_setopt($this->curl, CURLOPT_PROXY, $proxy);
				curl_setopt($this->curl, CURLOPT_PROXYPORT, $port);
				$this->http_proxy="$proxy:$port";
			}
			elseif($config['socks_proxy']){
				list($proxy, $port)=explode(":", $config['socks_proxy']);
				curl_setopt($this->curl, CURLOPT_PROXY, $proxy);
				curl_setopt($this->curl, CURLOPT_PROXYPORT, $port);

				// Use socks5-hostname to prevent GFW DNS attack
				if(!defined('CURLPROXY_SOCKS5_HOSTNAME')) define('CURLPROXY_SOCKS5_HOSTNAME', 7);
				// curl_setopt($this->curl, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
				curl_setopt($this->curl, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
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

		function destroy_cache($url){
			$this->cache->destroy($url);
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

		public function follow_url($url, $current=null){
			if(empty($url)) return $current;

			$parts=parse_url(trim($url));

			// Another host
			if(isset($parts['host'])) return $url;

			$current=parse_url($current ?: end($this->history));

			$path=isset($current['path']) ? explode("/",  $current['path']) : array("");
			if(isset($parts['path'])){
				// Jump to root if path begins with '/'
				if(strpos($parts['path'],'/')===0) $path=array();

				// Remove tip file
				if(count($path)>1) array_pop($path);

				// Normalize path
				foreach(explode("/", $parts['path']) as $p){
					if($p=="."){
						continue;
					}
					elseif($p=='..'){
						if(count($path)>1) array_pop($path);
						continue;
					}
					else{
						array_push($path, $p);
					}
				}
			}

			// Build url
			$result=array();
			$result[]=$current['scheme'].'://';
			$result[]=$current['host'];
			$result[]=isset($current['port']) ? $current['port'] : "";
			$result[]=implode("/", $path);
			$result[]=isset($parts['query']) ? '?'.$parts['query'] : "";
			$result[]=isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

			return implode("", $result);
		}

		private function visit($url, $save_history=true){
			$url=$this->follow_url($url, end($this->history));

			if($save_history) $this->history[]=$url;

			return $url;
		}

		function set_agent($agent){
			$this->agent=$agent;
			curl_setopt($this->curl, CURLOPT_USERAGENT, $this->agent);
		}

		function get($url, $data=array()){
			if($data){
				$info=parse_url($url);
				parse_str(@$info['query'], $query);
				$info['query']=http_build_query($data+$query);

				$url=$this->_build_url($info);
			}

     		// Build the the final output URL
			$this->open($url);
			return $this->content;
		}

		function _build_url(array $info){
			$url=(isset($info["scheme"])?$info["scheme"]."://":"").
				(isset($info["user"])?$info["user"].":":"").
				(isset($info["pass"])?$info["pass"]."@":"").
				(isset($info["host"])?$info["host"]:"").
				(isset($info["port"])?":".$info["port"]:"").
				(isset($info["path"])?$info["path"]:"").
				(isset($info["query"])?"?".$info["query"]:"").
				(isset($info["fragment"])?"#".$info["fragment"]:"");

			return $url;
		}

		function post($url, $data){
			curl_setopt($this->curl, CURLOPT_URL, $url);
			curl_setopt($this->curl, CURLOPT_POST, true);
			curl_setopt($this->curl, CURLOPT_HEADER, true);
			curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
			if(!$this->inprivate)
				curl_setopt($this->curl, CURLOPT_REFERER, end($this->history));

			if(is_array($data)){
				$formData=array();
				foreach($data as $k=>$v){ $formData[]="$k=".rawurlencode($v);}
				$formData=implode("&", $formData);
			}
			else{
				$formData=$data;
			}

			curl_setopt($this->curl, CURLOPT_POSTFIELDS, $formData);
			$this->_parse_response(curl_exec($this->curl));

			return $this->content;
		}

		function download($url, $dest){
			$file=fopen($dest, 'w');

			curl_setopt($this->curl, CURLOPT_FILE, $file);
			curl_setopt($this->curl, CURLOPT_POST, false);
			curl_setopt($this->curl, CURLOPT_URL, $url);
			curl_setopt($this->curl, CURLOPT_HEADER, false);
			if(!$this->inprivate)
				curl_setopt($this->curl, CURLOPT_REFERER, end($this->history));

			curl_exec($this->curl);
			// TODO: check curl_errno

			fclose($file);
			@curl_setopt($this->curl, CURLOPT_FILE, null);
		}

		function open($url, $forceRefresh=false){
			$this->content=null;

			// 尝试从cache获取
			if(!$forceRefresh && $this->cache){
				$this->content=$this->cache->get($url);
			}

			$this->cache_hit=true;

			if(!$this->content){
				$this->cache_hit=false;

				curl_setopt($this->curl, CURLOPT_URL, $url);
				curl_setopt($this->curl, CURLOPT_POST, false);
				curl_setopt($this->curl, CURLOPT_HEADER, true);
				curl_setopt($this->curl, CURLOPT_ENCODING , "");
				curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
				if(!$this->inprivate){
					curl_setopt($this->curl, CURLOPT_REFERER, end($this->history));
				}

				$this->_parse_response(curl_exec($this->curl));

			    $this->errno=curl_errno($this->curl);
			    $this->error=curl_error($this->curl);

			    if($this->errno==0 && $this->cache){
					$url=$this->visit($url);
					$this->cache->put($url, $this->content);
				}
			}
		}

		private function _parse_response($response){
			$sep=strpos($response, "\r\n\r\n");

			if($this->http_proxy){	// 去除Proxy产生的Response Header
				$sep=strpos($response, "\r\n\r\n", $sep+4);
			}

			$this->header=[];

			if(substr($response, 0, 4)=='HTTP' && $sep>0){
				$response_header=substr($response, 0, $sep);
				$this->content=substr($response, $sep);

				foreach(explode("\n", $response_header) as $row){
					if(preg_match('/http\/(\d+\.\d+)\s+(\d+)\s+/i', $row, $m)){
						$this->status=$m[2];
					}
					elseif(preg_match('/^([a-z0-9-]+):(.+)$/i', $row, $m)){
						$this->header[trim($m[1])]=trim($m[2]);
					}
				}
			}
			else{
				$this->content=$response;
			}
		}

		protected function _http_get($url){

		}
	}
}
?>
