<?php
namespace Clue\Logger;

class File extends Syslog{
    protected $option=[
        'backtrace'=>true,
        'context'=>false,
    ];

    function __construct($path){
        $dir=dirname($path);

        // 自动创建文件夹
        if(!is_dir($dir)){
            $umask=umask(0002);
            mkdir($dir, 0775, true);
            umask($umask);
        }

        $this->file=fopen($path, 'a');
        if(!$this->file) panic("Can't open log file: $path");
    }

    function __destruct(){
        if($this->file){
            fclose($this->file);
        }

        $this->file=null;
    }

    public function format($data){
        $text_format="%-22s | %-9s | %s";
        $text_format_detail="%25s %-9s | %s";

        $subject=sprintf("%s %s %s",
            $data['timestamp'],
            $data['level'] ? "| {$data['level']} |" : "",
            substr($data['message'], 0, 160)
        );

        $body="";
        if($this->option['backtrace'] && isset($data['backtrace'])){
            $body.="\n[Backtrace]\n".$this->format_backtrace($data['backtrace'])."\n";
        }

        unset($data['timestamp'], $data['level'], $data['message'], $data['backtrace']);

        foreach($data as $s=>$d){
            $body.="\n[".ucfirst($s)."]\n=======================\n".$this->format_var($d)."\n";
        }

        return $subject."\n\n".$this->indent(trim($body))."\n\n";
    }

    function write($data){
        fputs($this->file, $this->format($data));
    }
}
