<?php  
	/*
		Inspired by Net_URL2 (http://pear.php.net/package/Net_URL2)
	*/
	
	class Clue_URL_Malform_Exception extends Exception{
		
	}
		
	class Clue_URL{
		public $scheme;
		public $user;
		public $pass;
		public $host;
		public $port;
		public $path;
		public $query;
		public $fragment;
		
		static function is_absolute($url){
			$parts=parse_url($url);
			return isset($parts['scheme']) && isset($parts['host']);
		}
		
		static function is_relative($url){
			return !$this->is_absolute($url);
		}
		
		function __construct($url){
			$parts=parse_url($url);
			
			$this->scheme=isset($parts['scheme']) ? $parts['scheme'] : 'http';
			
			// Only http and https is supported
			if($this->scheme!='http' && $this->scheme!='https')
				throw new Clue_URL_Malform_Exception($url);
			
			$this->user=isset($parts['user']) ? $parts['user'] : false;
			$this->pass=isset($parts['pass']) ? $parts['pass'] : false;
			$this->host=isset($parts['host']) ? $parts['host'] : false;
			$this->port=isset($parts['port']) ? $parts['port'] : ( $this->scheme=='http' ? 80 : 443 );
			$this->path=isset($parts['path']) ? $parts['path'] : '/';
			$this->query=isset($parts['query']) ? $parts['query'] : false;
			$this->fragment=isset($parts['fragment']) ? $parts['fragment'] : false;
		}
		
		function get_url(){
			$url="$this->scheme://";
			if($this->user) 
				$url .= $this->user . ($this->pass ? ":$this->pass" : "") . '@';
			
			$url.=$this->host;
			
			if($this->port!='80' && $this->port!='443')
				$url.=":$this->port";
				
			$url.=$this->path;
			
			if($this->query) $url .= "?$this->query";
			
			if($this->fragment) $url .= "#$this->fragment";
			
			return $url;
		}
		
		function follow($link){
			if($this->is_absolute($link))
				return new Clue_URL($link);
			else
				return $this->resolve($link);
		}
		
		function resolve($reference){
			// TODO: make sure reference is relative and this is absolute
			$target=clone $this;
			
			$parts=parse_url($reference);
			
			$path=$this->path;
			if(substr($parts['path'], 0, 1)=='/'){
				$target->path=$parts['path'];
			}
			else{
				$dp=strrpos($target->path, '/');
				$base=substr($target->path, 0, $dp);
				
				$target->path=$this->reduce_dotted_segments($base .'/'. $parts['path']);
			}
			
			$target->query=isset($parts['query']) ? $parts['query'] : false;
			$target->fragment=isset($parts['fragment']) ? $parts['fragment'] : false;
			
			return $target;
		}
		
		private function reduce_dotted_segments($path){
			$segs=explode("/", $path);
			$path=array();
			
			foreach($segs as $s){
				if($s=='.') 
					continue;
				else if($s=='..'){
					if(count($path)>1)
						array_pop($path);
				}
				else
					$path[]=$s;
			}
			
			return implode('/', $path);
		}
		
		function __toString(){
			return $this->get_url();
		}
	}
?>