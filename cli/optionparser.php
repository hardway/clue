<?php
/**
 * Option Parser inspired by Python:optparse and Ruby:optionparser
 *
 * @link http://pymotw.com/2/optparse/
 * @link http://ruby-doc.org/stdlib-1.9.2/libdoc/optparse/rdoc/OptionParser.html
 * @link https://github.com/fidian/OptionParser
 * @link https://github.com/c9s/php-GetOptionKit
 */

namespace Clue\CLI{
    class OptionParser{
        protected $flagDef;

        function __construct(){
            $this->options=array();
        }

        function add_option($option){
            $option=array_merge([
                'short'=>null,
                'long'=>null,
                'help'=>'',
                'type'=>'flag'
            ], $option);

            // Trim '-' and '--'
            $option['short']=trim($option['short'], '- ');
            $option['long']=trim($option['long'], '- ');

            // Complete option help
            $arg='    '.
                ($option['short'] ? "-{$option['short']}, " : '    ') .
                ($option['long'] ? "--{$option['long']}" : '')
            ;

            $padding=str_repeat(' ', 40-strlen($arg));
            $option['help']=$arg . $padding . $option['help'];

            // Correct option flag
            $option['short']=explode(" ", $option['short'])[0];
            $option['long']=explode(" ", $option['long'])[0];

            if(!isset($option['default'])){
                switch($option['type']){
                    case 'flag':
                        $option['default']=false;
                        break;

                    case 'string':
                        $option['default']=null;
                        break;

                    case 'list':
                        $option['default']=[];
                        break;
                }
            }

            $this->options[]=$option;
        }

        function _shift_string(array &$args){
            $s=array_shift($args);
            return $s;
        }

        function _shift_list(array &$args){
            $list=[];

            while($args){
                $a=array_shift($args);
                if($a[0]=='-'){
                    array_unshift($args, $a);
                    break;
                }

                $list[]=$a;
            }

            return $list;
        }

        function parse(array $args=null){
            if($args===null){
                $args=array_slice($GLOBALS['argv'], 1);
            }

            $results=[];
            foreach($this->options as $o){
                $results[$o['name']]=$o['default'];
            }

            $commands=[];

            while(count($args)>0){
                $a=array_shift($args);

                if($a[0]=='-'){
                    foreach($this->options as $o){
                        if($a[1]=='-' && $o['long']){
                            $pattern='--'.$o['long'].'=?';
                        }
                        elseif($o['short']){
                            $pattern='-'.$o['short'];
                        }
                        else continue;

                        if(preg_match('/'.$pattern.'(.*)/', $a, $m)){
                            if($o['type']=='flag'){
                                $results[$o['name']]+=1;

                                if($a[1]!='-'){ // Set shrink short flags
                                    if(strlen($m[1])>0) foreach(str_split($m[1]) as $c){
                                        foreach($this->options as $o){
                                            if($o['type']=='flag' && $o['short']==$c){
                                                $results[$o['name']]+=1;
                                            }
                                        }
                                    }
                                }
                            }
                            elseif($o['type']=='string'){
                                $results[$o['name']]=$m[1] ?: $this->_shift_string($args);
                            }
                            elseif($o['type']=='list'){
                                $results[$o['name']]=array_merge($results[$o['name']], $this->_shift_list($args));
                            }
                            break;
                        }
                    }
                }
                else{
                    $commands[]=$a;
                }
            }

            return array($results, $commands);
        }

        function get_usage($banner=null){
            $banner=$banner ?: "Usage of ".$GLOBALS['argv'][0].":";

            $usage=[$banner];

            foreach($this->options as $o){
                $usage[]=$o['help'];
            }

            $usage[]="\n";

            return implode("\n", $usage);
        }
    }
}
?>
