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
				# 支持通配符
				if(strpos($f, "*")!==false){
					foreach(array_reverse(site_file_glob("asset/$f")) as $_){
						array_unshift($files, $_);
					}
					continue;
				}

				if(!file_exists($f)) $f=site_file('asset/'.$f);
				if(!file_exists($f)) continue; // 找不到资源情况下不会导致错误

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

			return $content;
		}

		function compress($content, $type){
			if($type=='css')
				return $this->compress_css($content);
			elseif($type=='js')
				return $this->compress_js($content);
			elseif($type=='jpg'){ // Only the first file will be supported
				return $this->compress_jpeg($this->files[0]);
			}
			else
				return $content;
		}

		function compress_jpeg($file){
			$im=new \Imagick($file);
			$im->setImageFormat("jpg");
			$im->setImageCompressionQuality(85);
			$im->stripImage();

			return $im->getImageBlob();
		}

		function compress_js($js){
			// use YUI-compressor
			$yui=getenv("YUI_COMPRESSOR");
			if(empty($yui)) throw new \Exception("Please specify location of yui compressor in environment variable YUI_COMPRESSOR");

			$raw_file=tempnam(sys_get_temp_dir(), 'clue');
			$min_file=tempnam(sys_get_temp_dir(), 'clue');

			file_put_contents($raw_file, $js);
			$cmd=sprintf("java -jar %s --type js -o %s %s", $yui, $min_file, $raw_file);
			passthru($cmd);
			$min=file_get_contents($min_file);

			unlink($min_file);
			unlink($raw_file);
			return $min;
		}

		function compress_css($css){
			$css=preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!','',$css);
			$css=preg_replace('/(\n|\r)+/', "\n", $css);
			$css=preg_replace('/\t|\s{2+}/', " ", $css);
			$css=preg_replace('/\s*(\{|\}|;|:|>)\s*/', '$1', $css);

			return $css;
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
					header("Content-Type: application/javascript");
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
