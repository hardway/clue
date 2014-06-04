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
			'/>([a-zA-Z0-9\_\-\*])/'=>'/$1',
			// dedendant of self
			'/(^|[^a-zA-Z0-9\_\-\*])(#|\.)([a-zA-Z0-9]+)/'=>'$1*$2$3',
			'/(^|[\>\+\|\~\,\s])([a-zA-Z\*]+)/'=>'$1//$2',
			'/\s+\/\//'=>'//',
			// :first-child
			'/([a-zA-Z0-9\_\-\*]+):first-child/'=>'*[1]/self::$1',
			// :last-child
			'/([a-zA-Z0-9\_\-\*]+):last-child/'=>'$1[not(following-sibling::*)]',

			// =attrib
			'/\[@([a-zA-Z0-9\_\-]+)=([^\]]+)\]/'=>"[@$1='$2']",
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
			if(preg_match('/meta charset\=[\"\']?([0-9a-zA-Z\-]+)/i', $html, $match)){
				$encoding=$match[1];
			}
			else if(preg_match('/encoding=[\"\']?([0-9a-zA-Z\-]+)/i', $html, $match))
				$encoding=$match[1];
			else
				$encoding='utf-8';
		}

		// Need to insert meta tag at first in case some of the webpage didn't have that.
		$html='<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'.$html;
		if(strtolower($encoding)!='utf-8') $html=mb_convert_encoding($html, 'utf-8', $encoding);

		$this->dom=new \DOMDocument();
		$this->dom->strictErrorChecking=false;
		$this->dom->substituteEntities=false;
		$this->dom->formatOutput=false;

		/* TODO: investigate tidy side effects
		$tidy = new \tidy;
		$html=$tidy->repairString($html);
		*/
		$html=str_replace('&nbsp;', ' ', $html);

		# TODO: 如何纠错，发现错误如何记录日志
		if($type=='html'){
			@$this->dom->loadHTML($this->_filter_content($html));
		}
		else if($type='xml'){
			@$this->dom->loadXML($html);
		}
		else {
			exit("Document Type Unknown.");
		}

		//$this->root=new Element($this->documentElement);

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
		else $xpath=preg_replace('/^\/\//', 'descendant-or-self::', $xpath);

		$nodeList=$this->xp->query($xpath, $context);

		return $nodeList->length>0 ? new Element($nodeList->item(0), $this) : null;
	}

	function xpath_elements($xpath, $context=null){
		if($context==null) $context=$this->dom;
		else $xpath=preg_replace('/^\/\//', 'descendant-or-self::', $xpath);

		$nodeList=$this->xp->query($xpath, $context);

		$result=array();
		for($i=0; $i<$nodeList->length; $i++){
			$result[]=new Element($nodeList->item($i), $this);
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

	function dump($el=null, $level=0){
		if($el==null)
			$el=$this->dom;
		elseif($el instanceof Element)
			$el=$el->el;

		print(str_repeat(' ', $level*4));

		switch($el->nodeType){
			case XML_DOCUMENT_NODE:
			case XML_HTML_DOCUMENT_NODE:
				foreach($el->childNodes as $c){
					$this->dump($c, $level+1);
				}
				break;

			case XML_ELEMENT_NODE:
				$tag=strtoupper($el->tagName);
				$id=$el->getAttribute("id");
				if(!empty($id)) $tag.="#$id";
				$class=$el->getAttribute("class");
				if(!empty($class)) $tag.=preg_replace("/\s+/",'.'," ".$class);

				$url="";
				if($el->tagName=='a'){
					$url=$el->getAttribute("href");
				}
				elseif($el->tagName=='img'){
					$url=$el->getAttribute("img");
				}

				printf("%s %s\n", $tag, $url);

				foreach($el->childNodes as $c){
					$this->dump($c, $level+1);
				}
				break;

			case XML_TEXT_NODE:
				printf("text(%d): %s\n", strlen($el->nodeValue), substr(trim(preg_replace('/\n|\r/', '', $el->nodeValue)), 0, 40));
				break;

			case XML_DOCUMENT_TYPE_NODE:
				// Skip
				break;

			default:
				var_dump($el);exit();
				exit("UNKNOWN NODE TYPE: ".$el->$nodeType);
		}
	}
}

class Element implements \ArrayAccess{
	public $el;	// Can be used by Clue_DOM_Parser
	protected $parser;

	function __construct($el, $parser){
		$this->el=$el;
		$this->parser=$parser;
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
		$action="get_$att";
		if(method_exists($this, $action)){
			return $this->$action($this->el);
		}
	}

	function getElement($css){
		return $this->parser->getElement($css, $this->el);
	}

	function getElements($css){
		return $this->parser->getElements($css, $this->el);
	}

	function getNext($selector){
		// TODO
		$next=$this->el->nextSibling;
		while($next){
			if($next->nodeName==$selector){
				return new Element($next, $this->parser);
			}
			else
				$next=$next->nextSibling;
		}

		return null;
	}

	function getParent($filter='.*'){
		$parent=$this->el->parentNode;
		while($parent!=null && !preg_match('/^'.$filter.'/', @$parent->tagName)){
			$parent=$parent->parentNode;
		}

		return is_null($parent) ? null : new Element($parent, $this->parser);
	}

	function get_text($n){
		$text="";
		if($n->childNodes) foreach($n->childNodes as $c){
			if($c->nodeType==XML_TEXT_NODE)
				$text.=trim($c->nodeValue);
			elseif($c->nodeType==XML_ELEMENT_NODE){
				$text.="\e".$this->get_text($c)."\e";
			}
		}

		return preg_replace("/\e+/", "\n", $text);
	}

	function get_html($n){
		$html="";
		$html="<".$n->tagName;
		if($n->attributes->length>0) foreach($n->attributes as $attr){
			$html.=sprintf(" %s=\"%s\"", $attr->name, $attr->value);
		}
		$html.=">";

		if($n->childNodes) foreach($n->childNodes as $c){
			if($c->nodeType==XML_TEXT_NODE)
				$html.=trim($c->nodeValue);
			elseif($c->nodeType==XML_ELEMENT_NODE){
				$html.=$this->get_html($c);
			}
		}
		$html.="</$n->tagName>";

		return $html;
	}

	function get_table($t){
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
					$c=new Element($c, $this->parser);
					$cells[]=$c->text;
				}
			}
			$table[]=$cells;
		}
		return $table;
	}

	function get_inner_html(){
		$html="";

		foreach($this->el->childNodes as $n){
			$d=new \DOMDocument();
			$d->appendChild($d->importNode($n, true));
			$html.=html_entity_decode($d->saveHTML(), ENT_NOQUOTES, "UTF-8");
		}

		return $html;
	}
}
?>
