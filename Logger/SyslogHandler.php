<?php
namespace Clue\Logger;

class SyslogHandler extends LoggerHandler{
    function write($data){
        $lines=explode("\n", $this->format($data));

    	array_map('error_log', $lines);
    }

    public function format($data){
        return $this->format_text($data);
    }
}
