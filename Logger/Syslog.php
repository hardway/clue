<?php
namespace Clue\Logger;

class Syslog implements Logger{
    function write($data){
        error_log(sprintf("%-22s | %-9s | %s", $data['timestamp'], strtoupper($data['level']), $data['message']));

        if(isset($data['caller'])){
            error_log(sprintf("%-22s | %-9s | %s", $data['timestamp'], 'CALLER', $data['caller']));
        }
    }
}
