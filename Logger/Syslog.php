<?php
namespace Clue\Logger;

class Syslog implements Logger{
    protected $option=[
        'backtrace'=>false,
        'context'=>false,
        'console'=>false
    ];

    function __construct(array $option=array()){
        $this->option=array_merge($this->option, $option);
    }

    function write($data){
        $lines=$this->format($data);

        if($this->option['console']){
            // 第一行红色显示
            $first_line=array_shift($lines);
            \Clue\CLI::banner($first_line."\n", "red");
        }

    	array_map('error_log', $lines);
    }

    static function indent($text){
        return implode("\n", array_map(function($line){ return "    ".$line; }, explode("\n", $text)));
    }

    public function format($data){
    	$lines=[];

    	$text_format="%-22s | %-9s | %s";
    	$text_format_detail="%25s %-9s | %s";

    	$lines[]=sprintf($text_format, $data['timestamp'], $data['message'], '['.$data['first_error'].'] ('.$data['first_trace'].')');

        if(is_array(@$data['diagnose'])) foreach($data['diagnose'] as $d){
            if($this->option['backtrace'] && isset($d['backtrace'])){
                $lines[]=sprintf("\n%s\n%s\n", 'BACKTRACE', $this->format_backtrace($d['backtrace']));
            }

            if($this->option['context'] && isset($d['http'])){
                $lines[]=sprintf($text_format_detail, 'HTTP', 'URL', $d['http']['url']);
                $lines[]=sprintf($text_format_detail, 'HTTP', 'METHOD', $d['http']['method']);
                $lines[]=sprintf($text_format_detail, 'HTTP', 'IP', $d['http']['ip']);
                $lines[]=sprintf($text_format_detail, 'HTTP', 'BROWSER', $d['http']['browser']);
                $lines[]=sprintf($text_format_detail, 'HTTP', 'REFERRER', $d['http']['referrer']);
            }

            if($this->option['context'] && isset($d['memory'])){
                $lines[]=sprintf($text_format_detail, "MEMORY", 'USAGE', $d['memory']['usage']);
                $lines[]=sprintf($text_format_detail, "MEMORY", 'PEAK', $d['memory']['peak']);
            }
        }

        return $lines;
    }

    public function format_backtrace($trace){
        if(!is_array($trace)) return "";

        $text=[];
        $width=0;
        $trace=array_map(function($t) use(&$width){
            $t['signature']=sprintf("%s%s%s()", @$t['class'], @$t['type'], @$t['function']);
            $t['location']=(isset($t['file']) || isset($t['line'])) ? "\t({$t['file']}:{$t['line']})":"";

            $width=max($width, strlen($t['signature']));

            return $t;
        }, $trace);

        foreach(array_reverse($trace) as $stack_level=>$t){
            // $text[]="";
            $text[]=str_repeat('-', 80);
            $text[]=sprintf("#%d %-{$width}s    %s", $stack_level, $t['signature'], $t['location']);

            if(isset($t['args'])) foreach($t['args'] as $idx=>$a){
                $arg_type=gettype($a);
                if($arg_type=='object') $arg_type=get_class($a);
                $arg_dump=json_encode($a, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

                $text[]=self::indent(($idx+1).": $arg_type ($arg_dump)");
            }
        }

        return implode("\r\n", $text);
    }

    public function format_var($var, $ttl=4){

        $text="";

        if($var instanceof Closure) $var="Closure Function";

        if(is_array($var) || is_object($var)){
            if($ttl==0) return "...";

            $width=max(array_map('strlen', array_keys($var)));

            $text="";
            foreach($var as $k=>$v){
                $text.=sprintf("%-{$width}s = ",$k);
                $text.=$this->format_var($v, $ttl-1);
                $text.="\n";
            }
            $text=$text ? "\n".self::indent(trim($text, "\n"))."\n" : " ";
            $text=(is_array($var)?"[":get_class($var).": {")."$text".(is_array($var)?"]":"}")."\n";
        }
        else{
            $text.=gettype($var);
            if(is_string($var))
                $text.="(".strlen($var)."): \"".$var."\"";
            elseif(is_bool($var)){
                $text.=": ".($var ? "true" : "false");
            }
            else{
                $text.=": $var";
            }
        }

        return $text;
    }
}
