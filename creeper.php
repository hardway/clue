<?php  
	class Clue_Creeper{
		public $response;
		public $content;
		
		private $curl;
		
		function __construct(){
			$this->curl=curl_init();
			curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
		}
		
		function __destruct(){
			curl_close($this->curl);
		}
		
		function open($url){
			curl_setopt($this->curl, CURLOPT_URL, $url);
			$this->content=curl_exec($this->curl);
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
