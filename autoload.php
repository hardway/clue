<?php
// Class Path和Include Path设定
namespace Clue{

    /**
     * Site path is LIFO stack
     */
    function add_site_path($path, $mapping=''){
        global $_SITE_PATH, $_SITE_PATH_MAPPING;

        $path=realpath($path);
        if($path && !in_array($path, $_SITE_PATH)){
            array_unshift($_SITE_PATH, $path);
            $_SITE_PATH_MAPPING[$path]=$mapping;
        }
    }

    function get_site_path(){
        global $_SITE_PATH;
        return $_SITE_PATH;
    }

    /**
     * Bootstrap code, facility to load subsystem here.
     */
    function add_include_path($path){
        set_include_path($path.PATH_SEPARATOR.get_include_path());
    }

    function add_class_path($path){
        global $_CLASS_PATH;

        if($_CLASS_PATH==null) $_CLASS_PATH=array();

        // Normalize path
        $path=realpath($path);

        if($path!==false && !in_array($path, $_CLASS_PATH)){
            $_CLASS_PATH[]=$path;
        }
    }

    function get_class_path(){
        global $_CLASS_PATH;
        return $_CLASS_PATH;
    }

    function autoload_load($class){
        global $_CLASS_PATH;

        $class=str_replace(NS, '/', $class);

        if(preg_match('/^clue\//i', $class)){
            // Special treat for Clue\ classes. For they might reside in a phar file.
            $class=substr($class, 5);

            foreach(array($class, strtolower($class)) as $cls){
                if(file_exists(__DIR__.'/'.$cls.".php")){
                    require_once __DIR__.'/'.$cls.".php";
                    return;
                }
            }
        }
        else{
            foreach($_CLASS_PATH as $path){
                foreach([$class, strtolower($class), str_replace('_', '/', $class), str_replace('_', '/', strtolower($class))] as $cls){
                    if(file_exists($path.'/'.$cls.".php")){
                        require_once $path.'/'.$cls.".php";
                        return;
                    }
                }
            }
        }
    }

    /**
     * 根据SITE_PATH定义，返回所需要的文件路径
     *
     * @return $path 文件路径
     */
    function site_file($path){
        if(strpos($path, APP_ROOT)===0) return $path;   // 已经是绝对路径，直接返回

        $path=trim($path, '/ ');

        $candidates=array_map(function($d) use($path){return $d.'/'.$path;}, get_site_path());
        foreach($candidates as $f){
            if(file_exists($f)) return $f;
        }

        return null;
    }

    /**
     * 类似site_file()和glob()合体
     */
    function site_file_glob($pattern){
        $files=array();

        $pattern=trim($pattern, '/ ');
        $candidates=array_map(function($d) use($pattern){return $d.'/'.$pattern;}, get_site_path());
        foreach($candidates as $pattern){
            foreach(glob($pattern) as $path){
                $name=basename($path);
                if(!isset($files[$name])) $files[$name]=$path;
            }
        }

        ksort($files);

        return array_values($files);
    }


    $_SITE_PATH=[];
    $_SITE_PATH_MAPPING=[];
    $_CLASS_PATH=[];

    add_site_path(APP_ROOT);
    add_site_path('./');

    #第三方库应该放在lib目录
    add_class_path(APP_ROOT."/lib");
    add_include_path(APP_ROOT."/lib");

    #应用相关的代码
    add_class_path(APP_ROOT."/source/model");
    add_class_path(APP_ROOT."/source/class");
    add_include_path(APP_ROOT."/source/include");

    spl_autoload_register("Clue\autoload_load");
}
