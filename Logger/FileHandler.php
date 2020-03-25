<?php
namespace Clue\Logger;

class FileHandler extends SyslogHandler{
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
        $this->filename=$path;
        $this->file=null;
    }

    function __destruct(){
        if($this->file){
            fclose($this->file);
        }

        $this->file=null;
    }

    function write($data){
        if(!$this->file){
            $this->file=fopen($this->filename, 'a');
            if(!$this->file){
                error_log("Can't open log file: $this->filename");
            }
        }

        if(!$this->file) return parent::write($data);

        fputs($this->file, $this->format($data)."\n");
    }
}
