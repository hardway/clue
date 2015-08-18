<?php
namespace Clue\Logger;

class File extends Syslog{
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

    function write($data){
        foreach($this->format_text($data) as $line){
            fputs($this->file, $line."\n");
        }
    }
}
