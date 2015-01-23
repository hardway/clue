<?php
namespace Clue\Logger;
class Syslog{
    function write($data){
        $message=sprintf("%-24s | %-8s | %s", $data['time'], strtoupper($data['level']), $data['message']);

        error_log($message);
    }

    function log($message, $level='info'){
        $this->write(['message'=>$message, 'time'=>$this->timestamp(), 'level'=>$level]);
    }

    function timestamp(){
        $timestamp=microtime(true);
        $timestamp=sprintf("%s.%03d", date("Y-m-d H:i:s", $timestamp), 1000*($timestamp - floor($timestamp)));

        return $timestamp;
    }

    function error($message){ $this->log($message, 'error'); }
    function info($message){ $this->log($message, 'info'); }
    function debug($message){ $this->log($message, 'debug'); }
    function warning($message){ $this->log($message, 'warning'); }
    function critical($message){ $this->log($message, 'critical'); }
    function alert($message){ $this->log($message, 'alert'); }
    function crash($message){ $this->log($message, 'crash'); }
}
