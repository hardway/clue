<?php
namespace Clue\Web{
	class Client{
		public $request_header=[];
		public $header;
		public $status=null;			// 返回的HTTP状态
		public $content;

		public $agent="ClueHTTPClient";
		public $referer=null;

		private $cache;
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
				'connect_timeout'=>15,
				'timeout'=>60
			);

			$config=array_merge($default_config, $config);

			$this->curl=curl_init();
			curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, $config['connect_timeout']);
			curl_setopt($this->curl, CURLOPT_TIMEOUT, $config['timeout']);

			if(preg_match('/^sock[45s]?:\/\/([a-z0-9\-_\.]+):(\d+)$/i', $config['http_proxy'], $m)){
				list($_, $proxy, $port)=$m;
				curl_setopt($this->curl, CURLOPT_PROXY, $proxy);
				curl_setopt($this->curl, CURLOPT_PROXYPORT, $port);

				// Use socks5-hostname to prevent GFW DNS attack
				if(!defined('CURLPROXY_SOCKS5_HOSTNAME')) define('CURLPROXY_SOCKS5_HOSTNAME', 7);
				curl_setopt($this->curl, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
				// curl_setopt($this->curl, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
			}
			elseif(preg_match('/^(http:\/\/)?([a-z0-9\-_\.]+):(\d+)/i', $config['http_proxy'], $m)){
				list($_, $scheme, $proxy, $port)=$m;

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
			if(isset($parts['scheme'])) return $url;

			$current=parse_url($current ?: $this->referer);

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
			if($this->referer)
				curl_setopt($this->curl, CURLOPT_REFERER, $this->referer);

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
			if($this->referer)
				curl_setopt($this->curl, CURLOPT_REFERER, $this->referer);

			curl_exec($this->curl);
			// TODO: check curl_errno

			fclose($file);
			@curl_setopt($this->curl, CURLOPT_FILE, null);
		}

		function open($url, $forceRefresh=false){
			$this->content=null;

			// 尝试从cache获取
			if(!$forceRefresh && $this->cache){
				list($this->content, $meta)=$this->cache->get($url);
				$this->status=$meta['status'];
				$this->header=$meta['header'];
			}

			$this->cache_hit=true;

			if(!$this->content){
				$this->cache_hit=false;

				curl_setopt($this->curl, CURLOPT_URL, $url);
				curl_setopt($this->curl, CURLOPT_POST, false);
				curl_setopt($this->curl, CURLOPT_HEADER, true);
				curl_setopt($this->curl, CURLOPT_ENCODING , "");
				curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
				if($this->referer){
					curl_setopt($this->curl, CURLOPT_REFERER, $this->referer);
				}

				$this->_parse_response(curl_exec($this->curl));

			    $this->errno=curl_errno($this->curl);
			    $this->error=curl_error($this->curl);

			    if($this->errno==0 && $this->cache){
					$this->cache->put($url, $this->content, ['status'=>$this->status, 'header'=>$this->header]);
				}
			}
		}

		private function _parse_response($response){
			$this->header=[];
			while(preg_match('/^HTTP\/(\d+\.\d+)\s+(\d+).+?\r\n\r\n/ms', $response, $header)){
				$this->status=$header[2];
				foreach(explode("\n", $header[0]) as $row){
					if(preg_match('/^([a-z0-9-]+):(.+)$/i', $row, $m)){
						$this->header[trim($m[1])]=trim($m[2]);
					}
				}

				// 去掉HTTP头部
				$response=substr($response, strlen($header[0]));
			}

			$this->content=$response;
		}

		protected function _http_get($url){

		}
	}
}
?>
