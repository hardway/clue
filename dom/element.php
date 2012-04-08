<?php  
namespace Clue{
	class Clue_DOM_Element extends \DOMElement{
		public $el;	// Can be used by Clue_DOM_Parser
		
		function __construct($el){
			$this->el=$el;
		}
		
		function __get($att){
			if($att=='innerText'){
				return $this->el->nodeValue;
			}
			else if($att=='innerHTML'){
				return $this->_get_inner_html();
			}
			
			return $this->el->getAttribute($att);
		}
		
		function get_element($selector){
			$result=$this->get_elements($selector);
			return count($result)>0 ? $result[0] : null;
		}
		
		function get_elements($selector){
			// TODO
			$nodeList=$this->_deep_search($this->el, $selector);
			
			$result=array();
			foreach($nodeList as $n){				
				$result[]=new Clue_DOM_Element($n);
			}
			return $result;
		}
		
		function next($selector){
			// TODO
			$next=$this->el->nextSibling;
			while($next){
				if($next->nodeName==$selector){
					return new Clue_DOM_Element($next);
				}
				else
					$next=$next->nextSibling;
			}
			
			return null;
		}
		
		function parent(){
			$parent=$this->el->parentNode;
			return is_null($parent) ? null : new Clue_DOM_Element($parent);
		}
		
		private function _deep_search($e, $tag){
			$result=array();
			
			foreach($e->childNodes as $n){
				if($n->nodeName==$tag)
					$result[]=$n;
				if($n->childNodes && $n->childNodes->length>0)
					$result=array_merge($result, $this->_deep_search($n, $tag));
			}
			
			return $result;
		}
		
		private function _get_inner_html(){
			$html="";
			
			foreach($this->el->childNodes as $n){
				$d=new DOMDocument();
				$d->appendChild($d->importNode($n, true));
				$html.=html_entity_decode($d->saveHTML(), ENT_NOQUOTES, "UTF-8");
			}
			
			return $html;
		}
	}
}
?>