<?php 
namespace Clue{
	abstract class Clue_UI_Skin{
		protected $header=array(
			'title'=>array(),
			'description'=>array(),
			'link'=>array(),
			'metaTags'=>array(),
			'links'=>array(),
			'styleSheets'=>array(),
			'style'=>array(),
			'scripts'=>array(),
			'script'=>array(),
			'custom'=>array()
		);
		protected $body='';
		
		public $template;
		public $template_path;
		public $appbase;
		
		function __construct($options=array()){
			$this->template=$options['template'];
			$this->path=$options['template_root'] . DS . $this->template;
			$this->appbase=$options['appbase'];	// TODO;
		}
		
		function get_header(){
			return $this->header;
		}
		
		function set_title($title){
			$this->header['title']=array("<title>$title</title>");
		}
		
		function add_script_file($scriptFile){
			$this->header['scripts'][]="<script type='text/javascript' src='$scriptFile' charset='utf-8'></script>";
		}
		
		function add_script($script){
			$this->header['script'][]=$script;
		}
		
		function add_style_sheet($css){
			$this->header['styleSheets'][]="<link rel='stylesheet' href='$css' type='text/css' media='screen' charset='utf-8'>";
		}
		
		function add_style($style){
			$this->header['style'][]=$style;
		}
		
		function set_header($header){
			$this->header=$header;
		}
		
		function set_body($body){
			$this->body=$body;
		}
		
		abstract function render();
		
		///---------------------------------------------------------------
		static $instance=null;
				
		static function set(Clue_UI_Skin $skin){
		    self::$instance=$skin;
		}
		
		static function load($application, $options=null){
			switch(strtolower($application)){
				case 'joomla':
					self::$instance=new Clue_UI_JoomlaSkin($options);
					break;
				case 'simple':
				default:
					self::$instance=new Clue_UI_SimpleSkin($options);
			}
				
			return self::$instance;
		}
	}
	
	class Clue_UI_SkinBuffer{
		function __get($name){
			if(isset($this->$name))
				return $this->$name;
			else
				return false;
		}
	}
	
	function skin(){
		return Clue_UI_Skin::$instance;
	}
}
?>
