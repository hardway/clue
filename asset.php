<?php
	class Clue_Asset{
		static public $ASSET_BASE=".";
		
		public $name;
		public $type;
		public $paths;
		
		function __construct($name, $paths){
			$this->name=$name;
			$this->type=substr($name, strrpos($name, ".")+1);
			$this->paths=$paths;
		}
		
		function compile($output=null){
			// Combine asset file contents
			$content="";
			foreach($this->paths as $path){
				$content.=@file_get_contents(self::$ASSET_BASE."/".$path);
			}
			
			if($output){
				//TODO: save content to file
			}
			else
				return $content;
		}
		
		function render(){
			// Output contents according to asset type
			header("Cache-Control: must-revalidate");
			switch($this->type){
				case "css":
					header("Content-Type: text/css");
					break;
				default:
					header("Content-Type: text/plain");
					break;
			}
			
			$content=$this->compile();
			
			header('Content-Length: '.strlen($content));
			echo $content;
		}
	}
?>
