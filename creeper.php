<?php  
	require_once 'clue/database.php';
	require_once 'clue/tool.php';
	
	class Clue_Creeper_Cache{
		private $cache;
		private $db;
		private $base36;
		
		function __construct($cache){
			$this->base36=new Clue_Base36();
			
			if(is_dir($cache) && file_exists($cache)){
				$this->cache=$cache;
				$this->db=Clue_Database::create('sqlite', array('db'=>"$cache/cache.db"));
				
				// check if cache table exists
				if(!$this->db->has_table('cache')){
					$this->db->exec("
						CREATE TABLE [cache] (
							[id] INTEGER  NOT NULL PRIMARY KEY,
							[url] VARCHAR(4096)  UNIQUE NULL,
							[last_fetch] INTEGER  NULL,
							[ttl] INTEGER DEFAULT '86400' NULL
						)
					");
				}
			}
		}
		
		function cache_file($id, $writing=false){
			$enc=$this->base36->encode($id);
			$folder=strlen($enc)>=2 ? "$this->cache/".substr($enc, 0, 2) : "$this->cache/00";
			$file="$folder/$enc";
			
			if($writing && !file_exists($folder)) mkdir($folder);
			
			return $file;
		}
		
		function get($url){
			// TODO: check TTL against last_fetch
			
			$url=$this->db->quote($url);
			$id=$this->db->get_var("select id from cache where url=$url");
			if($id){
				//echo "HIT($id): $url\n";
				// Try to load content from cache file
				$file=$this->cache_file($id);
				if(file_exists($file)){
					return file_get_contents($file);
				}
				else{
					// Cache file missing, delete record in database.
					$this->db->exec("delete from cache where id=$id");
					echo "Cache file missing, record removed($id)\n";
				}
			}
			
			return false;	// Cache missed
		}
		
		function put($url, $content){
			$url=$this->db->quote($url);
			$last_fetch=time();
			
			$id=$this->db->get_var("select id from cache where url=$url");
			if(!$id){
				$this->db->exec("insert into cache(url, last_fetch) values($url, $last_fetch)");
				$id=$this->db->insertId();
			}
			else{
				$this->db->exec("update cache set last_fetch=$last_fetch where id=$id");
			}
			
			//echo "Caching $url to $id ";
			$file=$this->cache_file($id, true);
			file_put_contents($file, $content);
			//echo "\n";
		}
	}
	
	class Clue_Creeper{
		public $response;
		public $content;
		private $cache;
		private $cachedb;
		
		private $curl;
		private $history=array();
		
		function __construct($option=array()){
			if(isset($option['cache'])) $this->cache=new Clue_Creeper_Cache($option['cache']);
			
			$this->curl=curl_init();
			curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);
			
			if(isset($option['proxy']) && strpos($option['proxy'], ':')>0){
				list($proxy, $port)=explode(":", $option['proxy']);
				curl_setopt($this->curl, CURLOPT_PROXY, $proxy);
				curl_setopt($this->curl, CURLOPT_PROXYPORT, $port);
			}
		}
		
		function __destruct(){
			curl_close($this->curl);
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
		
		function open($url, $force_download=false){
			$url=$this->visit($url);
			
			// check cache
			$this->content= $this->cache ? $this->cache->get($url) : false;
			if(!$this->content || $force_download){
				// echo "Creeping $url\n";
				curl_setopt($this->curl, CURLOPT_URL, $url);
				$this->content=curl_exec($this->curl);
				
				if($this->cache) $this->cache->put($url, $this->content);	// save cache
			}
		}
		
		function save($url, $path){
			$url=$this->visit($url, false);
			
			echo "Saving $url to $path\n";
			curl_setopt($this->curl, CURLOPT_URL, $url);
			curl_setopt($this->curl, CURLOPT_REFERER, end($this->history));
			file_put_contents($path, curl_exec($this->curl));
		}
		
		protected function _http_get($url){
			
		}
	}
	
	class Clue_Ripper{
		public $html;
		public $dom;
		public $xp;
		
		function __construct($html, $type='html', $encoding=null){
			$this->html=$html;
			// Prepare the raw html, convert to utf-8 encoding.
			
			// Detect encoding
			if($encoding==null){
				if(preg_match('|charset=([0-9a-zA-Z\-]+)|i', $html, $match))
					$encoding=$match[1];
				else if(preg_match('|encoding="([0-9a-zA-Z\-]+)"|i', $html, $match))
					$encoding=$match[1];
				else
					$encoding="utf-8";
			}
			if(strtolower($encoding)!="utf-8"){
				// Need to insert meta tag at first in case some of the 
				$html=
					'<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'.
					mb_convert_encoding($html, 'utf-8', $encoding);
			}
			
			$this->dom=new DOMDocument();
			$this->dom->strictErrorChecking=false;			
			$this->dom->substituteEntities=false;
			$this->dom->formatOutput=false;
			
			if($type=='html'){
				@$this->dom->loadHTML($this->_filterContent($html));
			}
			else if($type='xml'){
				@$this->dom->loadXML($html);
			}
			else {
				exit("Document Type Unknown.");
			}
			
			$this->xp=new DOMXPath($this->dom);
		}
		
		private function _filterContent($html){
			return preg_replace(array(
				'|<script.+?<\/script>|is',
				'|<!--.+?-->|is'
			), null, $html);
		}
		
		function getElements($xpath, $context=null){
			if($context==null) $context=$this->dom;
			return $this->xp->query($xpath, $context);
		}
		
		function getElement($xpath, $context=null){
			return @$this->getElements($xpath, $context)->item(0);
		}
		
		function getText($xpath, $context=null){
			$node=$this->getElement($xpath, $context);
			return $node ? $node->nodeValue : "";
		}
		
		function removeElement($xpath, $context=null){
			$node=$this->getElement($xpath, $context);
			if($node)
				$node->parentNode->removeChild($node);
			else
				echo $xpath;
		}
	}
?>
