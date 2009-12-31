<?php  
	class Clue_DOM_Element extends DOMElement{
		private $el;
		
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

?>