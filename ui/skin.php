<?php 
	class Clue_UI_Skin{
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
		
		public $template;
		public $template_path;
		public $appbase;
		
		function __construct($options=array()){
			$this->template=$options['template'];
			$this->path=$options['template_root'] . DS . $this->template;
			$this->appbase=$options['appbase'];	// TODO;
		}
		
		function getHeader(){
			return $this->header;
		}
		
		function setTitle($title){
			$this->header['title']=array("<title>$title</title>");
		}
		
		function addScripts($script){
			$this->header['scripts'][]="<script type='text/javascript' src='$script' charset='utf-8'></script>";
		}
		
		function addStyleSheet($css){
			$this->header['styleSheets'][]="<link rel='stylesheet' href='$css' type='text/css' media='screen' charset='utf-8'>";
		}
		
		function setHeader($header){
			$this->header=$header;
		}		
		
		///---------------------------------------------------------------
		static $instance=null;
				
		static function load($application, $options=null){
			if(strtolower($application)=="joomla"){
				self::$instance=new Clue_UI_JoomlaSkin($options);
			}
			else
				exit("No skin loader for $application");
				
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
?>
