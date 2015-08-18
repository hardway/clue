<?php
namespace Clue\Logger;

class Syslog implements Logger{
    function write($data){
    	array_map('error_log', $this->format_text($data));
    }

    protected function format_text($data){
    	$lines=[];

    	$text_format="%-22s | %-9s | %s";
    	$text_format_detail="%25s %-9s | %s";

    	$lines[]=sprintf($text_format, $data['timestamp'], strtoupper($data['level']), $data['message']);

        if(isset($data['backtrace'])){
            $lines[]=sprintf($text_format, $data['timestamp'], 'BACKTRACE', $data['backtrace']);
        }

        if(isset($data['http'])){
            $lines[]=sprintf($text_format_detail, 'HTTP', 'URL', $data['http']['url']);
            $lines[]=sprintf($text_format_detail, 'HTTP', 'METHOD', $data['http']['method']);
            $lines[]=sprintf($text_format_detail, 'HTTP', 'IP', $data['http']['ip']);
            $lines[]=sprintf($text_format_detail, 'HTTP', 'BROWSER', $data['http']['browser']);
            $lines[]=sprintf($text_format_detail, 'HTTP', 'REFERRER', $data['http']['referrer']);
        }

        if(isset($data['memory'])){
            $lines[]=sprintf($text_format_detail, "MEMORY", 'USAGE', $data['memory']['usage']);
            $lines[]=sprintf($text_format_detail, "MEMORY", 'PEAK', $data['memory']['peak']);
        }

        return $lines;
    }
}
