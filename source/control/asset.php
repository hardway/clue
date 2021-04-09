<?php
namespace Clue\Source\Control;

class Asset extends \Clue\Controller{
    function clue(){
        if($this->layout=='css'){
            // TODO: 更好的实现
            $asset=new \Clue\Asset();
            $asset->add(CLUE_ROOT.'/tool/skeleton/asset/css/clue.less');
            $asset->dump();
        }
        else{
            $this->__catch_params($this->view);
        }
    }

	function __catch_params(){
        $name=implode("/", array_filter(func_get_args()));

        // 使用映射的文件集合，或者直接使用该文件
        $files=@$this->app['config']['asset'][$name] ?: $name;
        if(is_string($files)) $files=[$files];

		$asset=new \Clue\Asset($files);
		$asset->dump();
	}
}
