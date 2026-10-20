<?php
namespace Clue\Source\Control;

class Asset extends \Clue\Controller{
    // 禁止通过URL直接输出的可执行脚本扩展名
    const BLOCKED_EXT=['php', 'phtml', 'php3', 'php4', 'php5', 'phar'];

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

        // 优先使用配置中映射的文件集合（配置由开发者维护，可信）
        $mapped=@$this->app['config']['asset'][$name];
        if($mapped){
            $files=is_string($mapped) ? [$mapped] : $mapped;
        }
        else{
            // 直接使用URL中的文件名：禁止 .. 穿越，并禁止输出 PHP 源码（其他文件保持原有行为）
            if(strpos($name, '..')!==false
                || strpos($name, "\0")!==false
                || in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::BLOCKED_EXT, true)){
                return $this->app->http_error(404, "Asset not found: $name");
            }
            $files=[$name];
        }

		$asset=new \Clue\Asset($files);
		$asset->dump();
	}
}
