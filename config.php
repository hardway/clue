<?php  
    require_once 'clue/core.php';

    class Clue_Config extends Clue_Registry{				
        function __construct($ary=null){
            if(is_string($ary)){
                $file=$ary;
                if(file_exists($file))
                    $this->_store=include $file;
                else
                    throw new Exception("Config file not found: $file");
            }
            else
                parent::__construct($ary);
        }

        /**
        * Merge config with another Clue_Config object
        * @return nothing
        */
        function merge(Clue_Config $cfg){
            function recursive_merge(&$base, &$addon){
                foreach($addon as $k=>&$v){
                    if(isset($base[$k]) && is_array($base[$k]) && is_array($v)){
                        recursive_merge($base[$k], $v);
                    }
                    else
                        $base[$k]=$v;
                }
            }

            recursive_merge($this->_store, $cfg->_store);
        }
    }
?>
