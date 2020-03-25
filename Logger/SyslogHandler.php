<?php
namespace Clue\Logger;

class SyslogHandler extends LoggerHandler{
    function write($data){
        $lines=explode("\n", $this->format($data));

        // if(php_sapi_name()=='cli'){
        //     // 第一行红色显示
        //     $first_line=array_shift($lines);
        //     \Clue\CLI::banner($first_line."\n", "red");
        // }

    	array_map('error_log', $lines);
    }

    public function format($data){
        return $this->format_text($data);
    }
}
