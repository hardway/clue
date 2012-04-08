<?php  
namespace Clue{
    class Clue_CLI_Flag{
        protected $flagDef;
        
        function __construct(){
            $flagDef=array();
        }
        
        function add_switch($name, $value, $usage){
            $this->flagDef[$name]=array(
                'default'=>$value,
                'usage'=>$usage
            );
        }
        
        function add_string($name, $value, $usage){
            $this->flagDef[$name]=array(
                'default'=>$value,
                'usage'=>$usage
            );
        }
        
        function parse(){
            global $argv;
            
            $args=$argv;
            array_shift($args);
            
            $this->args=array();
            $this->flags=array();
            
            while(count($args)>0){
                $a=array_shift($args);
                
                if(strpos($a, '-')===0){
                    if(preg_match('/\-+([^-=]+)=([^=]+)/i', $a, $m)){
                        $name=$m[1];
                        $value=$m[2];
                    }
                    else{
                        $name=ltrim($a, '-');
                        if(count($args)>0 && strpos($args[0], '-')!==0){
                            $value=array_shift($args);
                        }
                        else{
                            $value=$this->flagDef[$name]['default'];
                        }
                    }
                    $this->flags[$name]=$value;
                }
                else{
                    $this->args[]=$a;
                }
            }
        }
        
        function print_defaults(){
            foreach($this->flagDef as $name=>$o){
                printf("\t%s%s\t%s\t\t%s\n", strlen($name)==1 ? '-' : '--', $name, $o['default'], $o['usage']);
            }
        }
    }
}
?>
