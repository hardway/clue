<?php
namespace Clue\Logger;

// 将Handler / Formatter混合在一起
// TODO: 测试旧应用的兼容性

abstract class LoggerHandler{
	/**
	 * 必须写入的字段
	 * level, message, timestamp
	 *
	 * 可选写入字段
	 * channel, 和各种自定义
	 */
    abstract function write($data);

    abstract function format($data);

    // 低于这个level的，可以无需write
    protected $log_level_limit;
    function limit_log_level($level_limit){
        $this->log_level_limit=$level_limit;
    }

    /**
     * 根据level判断是否需要过滤
     * @return true=跳过这条日志
     */
    function filter_log($data){
        $level=\Clue\Logger::log_level($data['level']);
        return $level > $this->log_level_limit;
    }

    // 最常用的3种Format
    public function format_text($data){
        $lines=[];

        $message=$data['message'];

        $lines[]=sprintf("[%-22s] %s.%s: %s",
            $data['timestamp'],
            $data['channel'],
            strtoupper($data['level']),
            $message
        );


        // 输出特定的追踪数据，并做特殊格式化
        $text_format_detail="%s.%s: %s";

        if(is_array(@$data['backtrace'])){
            $lines[]=$this->indent("[Backtrace]\n".$this->format_backtrace($data['backtrace']));
        }

        if(is_array(@$data['http'])){
            $lines[]=sprintf($text_format_detail, 'HTTP', 'URL', $data['http']['url']);
            $lines[]=sprintf($text_format_detail, 'HTTP', 'METHOD', $data['http']['method']);
            $lines[]=sprintf($text_format_detail, 'HTTP', 'IP', $data['http']['ip']);
            $lines[]=sprintf($text_format_detail, 'HTTP', 'BROWSER', $data['http']['browser']);
            $lines[]=sprintf($text_format_detail, 'HTTP', 'REFERRER', $data['http']['referrer']);
        }

        if(is_array(@$data['process'])){
            $lines[]=$this->indent(sprintf($text_format_detail, "PROCESS", "ID", $data['process']['id']));
        }

        if(is_array(@$data['memory'])){
            $lines[]=$this->indent(sprintf($text_format_detail, "MEMORY", 'USAGE', $data['memory']['usage']));
            $lines[]=$this->indent(sprintf($text_format_detail, "MEMORY", 'PEAK', $data['memory']['peak']));
        }


        // 输出其他上下文日志数据
        $fields_already_formatted=['timestamp','channel','level','message','backtrace','memory','process','http'];
        foreach($fields_already_formatted as $f) unset($data[$f]);

        if(!empty($data)){
            foreach($data as $s=>$d){
                $lines[]=$this->indent("[$s]\n".$this->format_var($d));
            }
        }

        return implode("\n", $lines);
    }

    public function format_json($data){
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }


    /**
     * 格式化缩进
     */
    protected static function indent($text){
        return implode("\n", array_map(function($line){ return "    ".$line; }, explode("\n", $text)));
    }

    /**
     * 辅助函数，格式化Backtrace
     */
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

    /**
     * 辅助函数，格式化Object
     */
    public function format_var($var, $ttl=4){

        $text="";

        if($var instanceof Closure) $var="Closure Function";

        if(is_array($var) || is_object($var)){
            if($ttl==0) return "...";


            $text="";
            foreach($var as $k=>$v){
                $text.=sprintf("%s = ",$k);
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
