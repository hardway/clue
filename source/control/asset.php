<?php
namespace Clue\Source\Control;

class Asset extends \Clue\Controller{
	function __catch_params(){
		$asset=new \Clue\Asset();

		$name=implode("/", func_get_args());

		$files=@$this->app['config']['asset'][$name] ?: [];
		if(is_string($files)) $files=[$files];
		// 支持的路径写法
		// foo.js ==> SITE/asset/foo.js
		// /resource/foo/bar.css ==> SITE/resource/foo/bar.css
		$files=array_map(function($file){
			if($file[0]!='/') $file="asset/$file";

			return trim($file, '/');
		}, $files);

		if(is_array($files)) while($f=array_shift($files)){
			// TODO: 支持**
			// 支持通配符
			if(strpos($f, "*")!==false){
				foreach(array_reverse(\Clue\site_file_glob($f)) as $_){
					array_unshift($files, $_);
				}
				continue;
			}

			if(!file_exists($f)) $f=\Clue\site_file($f);
			if(!file_exists($f)) continue; // 找不到资源情况下不会导致错误
			$asset->add($f);
		}

		$asset->dump();
	}
}
