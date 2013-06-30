<?php
namespace Clue\Web;
class Parser{
	public $html;
	public $dom;
	public $xp;

	static function css2xpath($css){
		// Regex solution, ported from https://code.google.com/p/css2xpath
		// REF: verification test against http://css2xpath.appspot.com/
		$re=array(
			// add @ for attribs
			"/\[([^\]~\!\|\*\$\^]+)(=[^\]]+)?\]/"=>"[@$1$2]",
			// multiple queries
			'/\s*,\s*/'=>'|',
			'/\s*(\+|~|>)\s*/'=>'$1',
			'/([a-zA-Z0-9\_\-\*])~([a-zA-Z0-9\_\-\*])/'=>'$1/following-sibling::$2',
			'/([a-zA-Z0-9\_\-\*])\+([a-zA-Z0-9\_\-\*])/'=>'$1/following-sibling::*[1]/self::$2',
			'/([a-zA-Z0-9\_\-\*])>([a-zA-Z0-9\_\-\*])/'=>'$1/$2',
			// dedendant of self
			'/(^|[^a-zA-Z0-9\_\-\*])(#|\.)([a-zA-Z0-9]+)/'=>'$1$2$3',
			'/(^|[\>\+\|\~\,\s])([a-zA-Z\*]+)/'=>'$1//$2',
			'/\s+\/\//'=>'//',
			// *=attrib
			'/\[([a-zA-Z0-9\_\-]+)\*=([^\]]+)\]/'=>"[contains(@$1,$2)]",
			// ~=attrib
			'/\[([a-zA-Z0-9\_\-]+)~=([^\]]+)\]/'=>"[contains(concat(' ',normalize-space(@$1),' '),concat(' ',$2,' '))]",

			// ids and classes
			"/#([a-zA-Z0-9\_\-]+)/"=>"[@id='$1']",
			"/\.([a-zA-Z0-9\_\-]+)/"=>"[contains(concat(' ',normalize-space(@class),' '),' $1 ')]",

			// normalize multiple filters
			'/\]\[([^\]]+)/'=>' and ($1)',
		);

		$xpath=$css;
		foreach($re as $pattern=>$replace){
			$xpath=preg_replace($pattern, $replace, $xpath);
		}

		return $xpath;
	}

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

		$this->dom=new \DOMDocument();
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

		$this->xp=new \DOMXPath($this->dom);
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

	function get_element_by_id($id){
		$e=$this->dom->getElementById($id);
		return $e ? new Element($e) : null;
	}

	function getElement($css, $context=null){
		$xpath=Parser::css2xpath($css);
		return $this->xpath_element($xpath, $context);
	}
	function getElements($css,$context=null){
		$xpath=Parser::css2xpath($css);
		return $this->xpath_elements($xpath, $context);
	}

	function xpath_element($xpath, $context=null){
		if($context==null) $context=$this->dom;
		$nodeList=$this->xp->query($xpath, $context);

		return $nodeList->length>0 ? new Element($nodeList->item(0)) : null;
	}
	function xpath_elements($xpath, $context=null){
		if($context==null) $context=$this->dom;
		$nodeList=$this->xp->query($xpath, $context);

		$result=array();
		for($i=0; $i<$nodeList->length; $i++){
			$result[]=new Element($nodeList->item($i));
		}
		return $result;
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

class Element extends \DOMElement implements \ArrayAccess{
	public $el;	// Can be used by Clue_DOM_Parser

	function __construct($el){
		$this->el=$el;
	}

	function offsetExists($key){

	}
	function offsetGet($key){
		return $this->el->getAttribute($key);
	}
	function offsetSet($key, $value){

	}
	function offsetUnset($key){

	}

	function __get($att){
		if($att=='text'){
			return $this->extract_text($this->el);
		}
		else if($att=='table')
			return $this->extract_table($this->el);
		else if($att=='html'){
			return $this->extract_array($this->el);
		}
	}

	function get_element($selector){
		$result=$this->get_elements($selector);
		return count($result)>0 ? $result[0] : null;
	}

	function get_elements($selector){
		$xp=new \DOMXPath($this->el->ownerDocument);
		$nodeList=$xp->query($selector, $this->el);

		$result=array();
		foreach($nodeList as $n){
			$result[]=new Element($n);
		}
		return $result;
	}

	function next($selector){
		// TODO
		$next=$this->el->nextSibling;
		while($next){
			if($next->nodeName==$selector){
				return new Element($next);
			}
			else
				$next=$next->nextSibling;
		}

		return null;
	}

	function parent(){
		$parent=$this->el->parentNode;
		return is_null($parent) ? null : new Element($parent);
	}

	function extract_text($n){
		$text="";
		if($n->childNodes) foreach($n->childNodes as $c){
			if($c->nodeType==XML_TEXT_NODE)
				$text.=trim($c->nodeValue);
			elseif($c->nodeType==XML_ELEMENT_NODE)
				$text.=$this->extract_text($c);
		}

		return $text;
	}

	function extract_table($t){
		$rows=array();
		if($t->childNodes) foreach($t->childNodes as $c){
			if(in_array($c->nodeName, array('tbody','thead','tfoot'))){
				foreach($c->childNodes as $tr){
					$rows[]=$tr;
				}
			}
			elseif($c->nodeName=='tr'){
				$rows[]=$c;
			}
		}
		$table=array();
		foreach($rows as $r){
			$cells=array();
			if($r->childNodes) foreach($r->childNodes as $c){
				if(in_array($c->nodeName, array('td','th'))){
					$c=new Element($c);
					$cells[]=$c->text;
				}
			}
			$table[]=$cells;
		}
		return $table;
	}

	private function _get_inner_html(){
		$html="";

		return $this->get_text($this->el);

		foreach($this->el->childNodes as $n){
			$d=new \DOMDocument();
			$d->appendChild($d->importNode($n, true));
			$html.=html_entity_decode($d->saveHTML(), ENT_NOQUOTES, "UTF-8");
		}

		return $html;
	}
}
?>
