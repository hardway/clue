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
		}
		
		function put($url, $content){
			$url=$this->db->quote($url);
			$last_fetch=time();
			
			$this->db->exec("insert into cache(url, last_fetch) values($url, $last_fetch)");
			$id=$this->db->insertId();
			
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
		
		function __construct($option=array()){
			if(isset($option['cache'])) $this->cache=new Clue_Creeper_Cache($option['cache']);
			
			$this->curl=curl_init();
			curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
			
			if(isset($option['proxy'])){
				list($proxy, $port)=explode(":", $option['proxy']);
				curl_setopt($this->curl, CURLOPT_PROXY, $proxy);
				curl_setopt($this->curl, CURLOPT_PROXYPORT, $port);
			}
		}
		
		function __destruct(){
			curl_close($this->curl);
		}
		
		function open($url){
			// check cache
			$this->content= $this->cache ? $this->cache->get($url) : false;
			if(!$this->content){				
				// echo "Creeping $url\n";
				curl_setopt($this->curl, CURLOPT_URL, $url);
				$this->content=curl_exec($this->curl);
				
				if($this->cache) $this->cache->put($url, $this->content);	// save cache
			}
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
				@$this->dom->loadHTML($html);
			}
			else if($type='xml'){
				@$this->dom->loadXML($html);
			}
			else {
				exit("Document Type Unknown.");
			}
			
			$this->xp=new DOMXPath($this->dom);
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
	}
?>
