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
					foreach(array_reverse(glob(DIR_ASSET.'/'.$f)) as $_){
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
			// REST API arguments
			$apiArgs = array(
				'compilation_level'=>'SIMPLE_OPTIMIZATIONS',
				// 'compilation_level'=>'ADVANCED_OPTIMIZATIONS',
				'output_format' => 'text',
				'output_info' => 'compiled_code'
			);
			$args = 'js_code=' . urlencode($js);
			foreach($apiArgs as $key => $value) { $args .= '&' . $key .'='. urlencode($value);}

  			// API call using cURL
  			$call = curl_init();
  			curl_setopt_array($call, array(
  				CURLOPT_URL => 'http://closure-compiler.appspot.com/compile',
  				CURLOPT_POST => 1,
  				CURLOPT_POSTFIELDS => $args,
  				CURLOPT_RETURNTRANSFER => 1,
  				CURLOPT_HEADER => 0,
  				CURLOPT_FOLLOWLOCATION => 0
  			));
  			$jscomp = curl_exec($call);
  			curl_close($call);

  			return $jscomp;
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
