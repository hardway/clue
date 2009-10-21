<?php  
	define('_JEXEC', 'joomla skin');
	
	require_once 'clue/ui/skin.php';
	
	class Clue_UI_JoomlaSkinModuleLoader{
		function __get($view){
			$view="view/module/$view.tpl";
			$content="";
			if(file_exists($view)){
				ob_start();
				require_once $view;
				$content = ob_get_contents();
				ob_end_clean();
			}
			return $content;
		}
		
		static function load($view){
			var_dump("Loading $view...");
		}
	}
	
	class Clue_UI_JoomlaSkinBuffer extends Clue_UI_SkinBuffer{
		function __get($name){
			if($name=='module'){
				return new Clue_UI_JoomlaSkinModuleLoader();
			}
			else if(isset($this->$name))
				return $this->$name;
			else
				return false;
		}
	}

	class Clue_UI_JoomlaSkin extends Clue_UI_SKin{
		protected $path;
		public $buffer;
		
		
		private $debug=false;
		
		private $module_mapping=array(
			'top'=>array(),
			'right'=>array(),
			'left'=>array(),
			'footer'=>array()
		);
		
		private $layout_mapping=array(
			0=>array('top', 'right', 'left', 'footer'),
		);
		
		private $layout;
		
		function __construct($options=array()){
			parent::__construct($options);
			
			$this->language="en-GB";
			$this->direction='ltr';
			$this->params=new JParameter($this->path . DS ."params.ini");			
			$this->buffer=new Clue_UI_JoomlaSkinBuffer;	// TODO: initialize with layout.
			
			$this->baseurl=$this->appbase;	// Joomla templates rely on this
			
			// Determine layout according to Itemid (joomla legacy?)
			$this->layout=JRequest::getCmd('Itemid', 0);
			if(!isset($this->layout_mapping[$this->layout])) $this->layout=0;	// Fallback to default
		}
		
		function register_module($module, $view){
			if(!isset($this->module_mapping[$module])){
				$this->module_mapping[$module]=array();
				$this->layout_mapping[0][]=$module;
			}
			$this->module_mapping[$module][]=$view;
		}
		
		function render(){
			ob_start();
			
			$config=new JParameter("templates/{$this->template}/params.ini");

			require_once $this->path . DS . 'index.php';
			$content = ob_get_contents();
			ob_end_clean();
						
			if(preg_match_all('#<jdoc:include\ type="([^"]+)" (.*)\/>#iU', $content, $matches)){
				// Generate skin parts bottom up.
				$matches[0] = array_reverse($matches[0]);
				$matches[1] = array_reverse($matches[1]);
				$matches[2] = array_reverse($matches[2]);
				
				$cnt = count($matches[1]);
				for($i = 0; $i < $cnt; $i++){
					$attribs = $this->parseAttributes($matches[2][$i]);
					$type  = $matches[1][$i];
					$name  = isset($attribs['name']) ? $attribs['name'] : null;
					$replace[$i] = $this->getBuffer($type, $name, $attribs);
				}
				
				$content = str_replace($matches[0], $replace, $content);
			}
			
			echo $content;
		}
		
		function getBuffer($type, $name=null, $attribs=null){
			if($type=='head'){
				return $this->getHeadBuffer();
			}
			
			switch($type){
				case 'message':
					return " ";
				case 'modules':
					$position=$name;
					if(in_array($position, $this->layout_mapping[$this->layout])){
						$content="";
						foreach($this->module_mapping[$position] as $module){
							$content.=$this->buffer->module->$module;
						}
						return $content;
					}
					break;
				case 'component':
					return $this->buffer->component;
				default:
					break;
			}
			// TODO: condition of debug
			return $this->debug ? "<div style='font-weight: bold; color: #600;'>$type:$name($attribs)</div>" : "";
		}
		
		function getHeadBuffer(){
			$buf="";
			foreach($this->header as $type=>$hs){
				if($type=='style'){
					$buf.="<style type='text/css' media='screen'>".implode("\n", $hs)."</style>";
				}
				else
					$buf.=implode("\n", $hs);
			}
			return $buf;
		}
		
		function getHeadData(){
			return $this->getHeader();
		}
		
		function addStyleDeclaration($content, $type = 'text/css'){
			$this->header['style'][] = $content;
		}
		
		function setHeadData($header){
			$this->setHeader($header);
		}
		
		function countModules($condition){
			$count=0;
			
			foreach(explode(' ', $condition) as $position){
				if(in_array($position, $this->layout_mapping[$this->layout])){
					$count+=count($this->module_mapping[$position]);
				}
			}
			
			return $count;
		}
				
		private function parseAttributes($raw){
			$attr=array();
			foreach(explode(" ", $raw) as $pair){
				if(strpos($pair, '=')>0){
					list($key, $val)=explode("=", $pair);
					$attr[$key]=trim($val, '"');
				}
			}
			return $attr;
		}
	}
	
	///---------------------------------------------------------------------------------
	/// Joomla Dummy Classes
	
	function jimport(){}
	
	// L10N Translation
	class JText{
		static function _($text){
			return $text;	// TODO;
		}
	}
	
	class JHTML{
		static function _($text){
			if($text=='behavior.mootools'){
				echo "<script type='text/javascript' charset='utf-8' src='".JURI::base()."assets/mootools.js'></script>";
			}
			else
				echo $text;	// TODO;
		}
	}
	
	class JFactory{
		static function getApplication(){
			return new stdclass();
		}
		
		static function getUser(){
			return new JUser();
		}
		
		static function getDBO(){
			return new JDatabase;
		}
	}
	
	class JDatabase{
		function setQuery($sql){
			//var_dump($sql);			
		}
		
		function loadObject(){
			
		}
		
		function loadResult(){
			
		}
	}
	
	class JSite{
		static function getMenu(){
			return new JMenu;
		}
	}
	
	class JUser{
		function get(){
			
		}
	}
	
	class JURI{
		static function getInstance(){
			return new JURI;
		}
		
		static function base(){
			return skin()->appbase."/";
		}
	}
	
	class JRequest{
		static function getCmd($name, $default=false){
			return isset($_GET[$name]) ? $_GET[$name] : $default;
		}
		
		static function getInt(){
			return 0;
		}
		
		static function getURI(){
			return false;	// TODO;
		}
	}
	
	class JRoute{
		static function _(){
			return false; // TODO;
		}
	}

	class JConfig{
		function __get($key){
			$config=app()->config;
			
			if($config->$key){
				return $config->$key;
			}
			else
				return false;
		}
	}
	
	class JParameter{
		protected $data;
		
		function __construct($path){
			if(file_exists($path))
				$this->data=parse_ini_file($path);
		}
		
		function get($key){
			return isset($this->data[$key]) ? $this->data[$key] : false;
		}
		
		function set($key, $val){
			$this->data[$key]=$val;
			return $val;
		}
		
		function toArray(){
			return array();
		}
	}

	class JMenu{
		static function getInstance(){
			return new JMenu;
		}
		
		function getItems(){
			return array();	// TODO: return default menu
		}
	}
	
	///---------------------------------------------------------------------------------
	/// Joomla Dummy Classes
	
	class JoomlaSample{
		protected $options=array(
			'module.debug.show'=>false
		);
		
		function __construct($options=array()){
			// Merge options
			foreach($options as $o=>$v){
				$this->options[$o]=$v;
			}
		}
		
		function load_sample($name){
			ob_start();
			require_once "joomlasample/$name.html";
			$content = ob_get_contents();
			ob_end_clean();
			
			return $content;
		}
	}
?>
