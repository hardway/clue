<?php  
	class Clue_DOM_Parser{
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
					$encoding=array("utf-8", "gbk");
			}
			
			// Need to insert meta tag at first in case some of the webpage didn't have that.
			$html='<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'.$html;
			$html=mb_convert_encoding($html, 'utf-8', $encoding);
			
			$this->dom=new DOMDocument();
			$this->dom->strictErrorChecking=false;			
			$this->dom->substituteEntities=false;
			$this->dom->formatOutput=false;
			
			if($type=='html'){
				@$this->dom->loadHTML($this->_filter_content($html));
			}
			else if($type='xml'){
				@$this->dom->loadXML($html);
			}
			else {
				exit("Document Type Unknown.");
			}
			
			$this->xp=new DOMXPath($this->dom);
		}
		
		function __destruct(){
			$this->html=null;
			$this->dom=null;
			$this->xp=null;
		}
		
		private function _filter_content($html){
			return preg_replace(array(
				'|<script.+?<\/script>|is',
				'|<!--.+?-->|is'
			), null, $html);
		}
		
		function get_elements($xpath, $context=null){
			if($context==null) $context=$this->dom;
			$nodeList=$this->xp->query($xpath, $context);
			
			$result=array();
			for($i=0; $i<$nodeList->length; $i++){
				$result[]=new Clue_DOM_Element($nodeList->item($i));
			}
			return $result;
		}
		
		function get_element_by_id($id){
			$e=$this->dom->getElementById($id);
			return $e ? new Clue_DOM_Element($e) : null;
		}
		
		function get_element($xpath, $context=null){
			if($context==null) $context=$this->dom;
			$nodeList=$this->xp->query($xpath, $context);
			
			return $nodeList->length>0 ? new Clue_DOM_Element($nodeList->item(0)) : null;
		}
		
		function get_text($xpath, $context=null){
			$node=$this->getElement($xpath, $context);

			return $node ? $node->innerText : "";
		}
		
		function remove_element($xpath, $context=null){
			$node=$this->get_element($xpath, $context);
			if($node)
				$node->parentNode->removeChild($node);
			else
				echo $xpath;
		}
	}
?>