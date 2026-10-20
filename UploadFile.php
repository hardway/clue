<?php
namespace Clue;

class UploadFile{
    function __construct($name){
        if(!isset($_FILES[$name])) panic("Upload file $name not found");

        foreach(['name', 'type', 'tmp_name', 'size', 'error'] as $f){
            $this->$f=$_FILES[$name][$f];
        }

        $this->ext=pathinfo($this->name, PATHINFO_EXTENSION);
    }

    function is_image(){
        // TODO: 支持更多类型检测
        return preg_match('/^image/', $this->type);
    }

    function is_valid(){
        // TODO: 使用选项检测文件MIME类型

        if(empty($this->tmp_name)) return false;
        if(intval($this->size)==0) return false;
        if($this->error) return false;

        return true;
    }

    // 只保留文件名部分，去掉目录成分（防止 ../ 路径穿越），其他文件名保持不变
    static function safe_name($name){
        $name=basename(str_replace(["\0", '\\'], ['', '/'], (string)$name));
        return ($name === '' || $name === '.' || $name === '..') ? 'upload' : $name;
    }

    function save($path){
        $saveas= is_dir($path) ? $path."/".self::safe_name($this->name) : $path;
        $folder=dirname($saveas);
        if(!is_dir($folder)){
            error_log("INFO: creating folder: $folder");
            mkdir($folder, 0775, true);
        }
        return move_uploaded_file($this->tmp_name, $saveas);
    }
}
