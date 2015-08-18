<?php
namespace Clue\Logger;

class File implements Logger{
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
        $message=sprintf("%-22s | %-9s | %s\n", $data['timestamp'], strtoupper($data['level']), $data['message']);

        fputs($this->file, $message);

        if(isset($data['caller'])){
            $message=sprintf("%-22s | %-9s | %s\n", $data['timestamp'], 'CALLER', $data['caller']);
            fputs($this->file, $message);
        }
    }
}
