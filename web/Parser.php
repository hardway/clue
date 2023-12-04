<?php
namespace Clue\Web;
class Parser{
    public $html;
    public $dom;
    public $xp;

    public $root=null;

    static function css2xpath($css){
        // 以/开始的话，应该已经是xpath了
        if(preg_match('/^\//', $css)) return $css;

        $attr_patterns=[
            // =attrib
            '/\[@?([a-zA-Z0-9\_\-]+)=([^\]]+)\]/'=>"[@$1=$2]",
            // *=attrib
            '/\[([a-zA-Z0-9\_\-]+)\*=([^\]]+)\]/'=>"[contains(@$1,$2)]",
            // ^=attrib
            '/\[([a-zA-Z0-9\_\-]+)\^=([^\]]+)\]/'=>"[starts-with(@$1,$2)]",
            // ~=attrib
            '/\[([a-zA-Z0-9\_\-]+)~=([^\]]+)\]/'=>"[contains(concat(' ',normalize-space(@$1),' '),concat(' ',$2,' '))]",
        ];

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
            // :nth-child
            '/([a-zA-Z0-9\_\-\*]+):nth-child\((\d+)\)/'=>'$1[position()=$2]',

            // ids and classes
            "/#([a-zA-Z0-9\_\-]+)/"=>"[@id='$1']",
            "/\.([a-zA-Z0-9\_\-]+)/"=>"[contains(concat(' ',normalize-space(@class),' '),' $1 ')]",

            // normalize multiple filters
            '/\]\[([^\]]+)/'=>' and ($1)',
        );

        $xpath=$css;

        // 预处理Attr属性
        $saved_attr=[];
        foreach($attr_patterns as $pattern=>$replace){
            $xpath=preg_replace_callback($pattern, function($m) use(&$saved_attr, $pattern, $replace){
                $saved_attr[]=preg_replace($pattern, $replace, $m[0]);
                $id=count($saved_attr)-1;
                return "{{A$id}}";
            }, $xpath);
        }

        foreach($re as $pattern=>$replace){
            $xpath=preg_replace($pattern, $replace, $xpath);
        }

        // Attr恢复
        foreach($saved_attr as $i=>$rep){
            $xpath=str_replace("{{A$i}}", $rep, $xpath);
        }

        return $xpath;
    }

    function __construct($html, $type='html', $encoding=null){
        $this->dom=new \DOMDocument();
        $this->dom->strictErrorChecking=false;
        $this->dom->substituteEntities=false;
        $this->dom->formatOutput=false;

        $this->html=$html;

        // 检测编码，并转换
        if($encoding==null){
            // 仅试图从头部寻找编码
            $head=strpos($html, "</head>");
            $head=substr($html, 0, $head ?: 1024);

            if(preg_match('/meta[^>]+charset\=[\"\']?([0-9a-zA-Z\-]+)/i', $head, $match)){
                $encoding=$match[1];
            }
            else if(preg_match('/encoding=[\"\']?([0-9a-zA-Z\-]+)/i', $head, $match))
                $encoding=$match[1];
            else
                $encoding='utf-8';
        }

        /* TODO: investigate tidy side effects
        $tidy = new \tidy;
        $html=$tidy->repairString($html);
        */
        $html=str_replace('&nbsp;', ' ', $html);

        # TODO: 如何纠错，发现错误如何记录日志
        if($type=='html'){
            // 按需添加META头部
            $html='<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'.$html;
            if(strtolower($encoding)!='utf-8') $html=@mb_convert_encoding($html, 'utf-8', $encoding);

            @$this->dom->loadHTML($this->_filter_content($html));
        }
        else if($type='xml'){
            @$this->dom->loadXML($html);
        }
        else {
            exit("Document Type Unknown.");
        }

        // Recursion reference will do harm?
        $this->root=new Element($this->dom->documentElement, $this);

        $this->xp=new \DOMXPath($this->dom);

        @gc_collect_cycles();
    }

    function __destruct(){
        $this->root=null;
        $this->xp=null;
        $this->dom=null;
        $this->html=null;
    }

    private function _filter_content($html){

        foreach(['script', 'SCRIPT', 'Script'] as $tag){
            $pos=0; $len=strlen($html);

            $stripped="";
            while($pos < $len){
                $begin=strpos($html, "<$tag", $pos);
                if(!$begin) break;
                $end=strpos($html, "</$tag>", $begin);
                if(!$end) break;
                $stripped.=substr($html, $pos, $begin - $pos);
                $pos=$end + 9;
            }

            $stripped.=substr($html, $pos);
            $html=$stripped;
        }

        return preg_replace(array(
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
        $node=$this->getElement($xpath, $context);
        if($node){
            $node->destroy();
            return $node;
        }

        return false;
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
    public $el; // Can be used by Clue_DOM_Parser
    protected $parser;

    function __construct($el, $parser){
        $this->el=$el;
        $this->parser=$parser;
    }

    function __destruct(){
        $this->el=null;
        $this->parser=null;
    }

    function __toString(){
        return $this->text;
    }

    function offsetExists($key):bool{
        return $this->el->hasAttribute($key);
    }

    #[\ReturnTypeWillChange]
    function offsetGet($key){
        return $this->el->getAttribute($key);
    }
    function offsetSet($key, $value):void{
        $this->el->setAttribute($key, $value);
    }
    function offsetUnset($key):void{
        $this->el->removeAttribute($key);
    }

    function __get($att){
        $action="get_$att";
        if(method_exists($this, $action)){
            return $this->$action($this->el);
        }
    }

    function destroy(){
        $this->el->parentNode->removeChild($this->el);
    }

    /**
     * 搜索匹配内容的元素
     */
    function searchElement($css, $string){
        $elements=$this->getElements($css);
        foreach($elements as $e){
            if(strpos($e->text, $string)!==false)
                return $e;
        }

        return null;
    }

    function getElement($css){
        return $this->parser->getElement($css, $this->el);
    }

    function getElements($css){
        return $this->parser->getElements($css, $this->el);
    }

    function getNext($filter='.+'){
        $next=$this->el->nextSibling;
        while($next && !preg_match('/^'.$filter.'/', @$next->tagName)){
            $next=$next->nextSibling;
        }

        return $next ? new Element($next, $this->parser) : null;
    }

    function getPrev($filter='.+'){
        $prev=$this->el->previousSibling;
        while($prev && !preg_match('/^'.$filter.'/', @$prev->tagName)){
            $prev=$prev->previousSibling;
        }

        return $prev ? new Element($prev, $this->parser) : null;
    }

    function getChildren($filter='.+'){
        $children=[];

        foreach($this->el->childNodes as $e){
            if($e->nodeType!=XML_ELEMENT_NODE) continue;

            if(preg_match('/^'.$filter.'/', $e->tagName)){
                $children[]=new Element($e, $this->parser);
            }
        }

        return $children;
    }

    function getParent($filter='.+'){
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
                $text.=$c->nodeValue;
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
                $html.=$c->nodeValue;
            elseif($c->nodeType==XML_ELEMENT_NODE){
                $html.=$this->get_html($c);
            }
        }
        $html.="</$n->tagName>";

        return $html;
    }

    /**
     * 以数组形式返回
     */
    function get_array($n){
        $ary=[];

        // 属性用@作为前缀
        if($n->attributes->length>0) foreach($n->attributes as $attr){
            $ary["@".$attr->name]=$attr->value;
        }

        if($n->childNodes) foreach($n->childNodes as $c){
            if($c->nodeType==XML_TEXT_NODE){
                // 文本
                $ary['@text']=$c->nodeValue;
            }
            elseif($c->nodeType==XML_ELEMENT_NODE){
                $tag=$c->tagName;

                $val=$this->get_array($c);

                if(!isset($ary[$tag])){
                    // 单个元素
                    $ary[$tag]=$val;
                }
                elseif(isset($ary[$tag][0])){
                    // 多个元素
                    $ary[$tag][]=$val;
                }
                else{
                    // 发现多个元素
                    $ary[$tag]=[$ary[$tag], $val];
                }
            }
        }

        // 如果只有@text属性，则自动简化数组
        if(array_keys($ary)==['@text']){
            $ary=$ary['@text'];
        }

        return $ary;
    }

    function get_table(){
        $rows=array();

        $t=$this->el;
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
            // $html.=$d->saveHTML();
        }

        return $html;
    }
}
?>
