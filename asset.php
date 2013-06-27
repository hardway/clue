<?php
namespace Clue{
	class Asset{
		public $type;
		public $files;

		function __construct($files){
			$this->files=array();
			$this->add($files);
		}

		function add($files){
			if(!is_array($files)) $files=array($files);

			while($f=array_shift($files)){
				if(strpos($f, "*")!==false){
					foreach(array_reverse(glob($f)) as $_){
						array_unshift($files, $_);
					}
					continue;
				}

				if(!file_exists($f)) $f=DIR_ASSET.'/'.$f;
				if(!file_exists($f)) continue;

				if(!in_array($f, $this->files)){
					$this->files[]=$f;
				}
			}
		}

		function compile(){
			$ext=array();

			// Combine asset file contents
			$content="";
			foreach($this->files as $f){
				$pi=pathinfo($f);
				@$ext[$pi['extension']]++;

				$content.=@file_get_contents($f);
			}

			if(count($ext)==1){
				$this->type=array_keys($ext);
				$this->type=$this->type[0];
			}

			// TODO: cache

			return $content;
		}

		function dump(){
			$content=$this->compile();

			// Output contents according to asset type
			header("Cache-Control: must-revalidate");
			switch($this->type){
				case "css":
					header("Content-Type: text/css");
					break;
				case 'js':
					header("Content-Type: application/x-javascript");
					break;
				default:
					header("Content-Type: text/plain");
					break;
			}

			header('Content-Length: '.strlen($content));
			echo $content;
		}
	}
}

namespace{
    function asset($asset=null){
    	$path=DIR_ASSET."/$asset";

    	if(defined("THEME") && file_exists(APP_ROOT.'/'.THEME."/asset/$asset")){
    		$path=APP_ROOT.'/'.THEME."/asset/$asset";
    	}

		$url=str_replace("\\", '/', str_replace(APP_ROOT, '', $path));
		return $url.(file_exists($path) ? '?'.filemtime($path) : "");
    }
}
?>
