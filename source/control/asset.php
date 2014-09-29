<?php
namespace Clue\Source\Control;

class Asset extends \Clue\Controller{
    function __catch_params($name){
        $asset=new \Clue\Asset();

        $files=@$this->app['config']['asset'][$name];
        if(is_string($files)) $files=[$files];

        if(is_array($files)) while($f=array_shift($files)){
            # 支持通配符
            if(strpos($f, "*")!==false){
                foreach(array_reverse(\Clue\site_file_glob("asset/$f")) as $_){
                    array_unshift($files, $_);
                }
                continue;
            }

            if(!file_exists($f)) $f=\Clue\site_file('asset/'.$f);
            if(!file_exists($f)) continue; // 找不到资源情况下不会导致错误

            $asset->add($f);
        }

        $asset->dump();
    }
}
