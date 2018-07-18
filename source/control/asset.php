<?php
namespace Clue\Source\Control;

class Asset extends \Clue\Controller{
	function __catch_params(){
        $name=implode("/", func_get_args());

        $files=@$this->app['config']['asset'][$name] ?: [];
        if(is_string($files)) $files=[$files];

		$asset=new \Clue\Asset($files);
		$asset->dump();
	}
}
