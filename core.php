<?php
namespace Clue{
    // 辅助函数, copied from YYT
    function time_used(){
        $currTime = microtime(true);
        return number_format(($currTime - BEGIN_TIME), 4);
    }

    function memory_used(){
        $format = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
        $pos = 0;
        $currMemory = memory_get_usage();
        $size = $currMemory - BEGIN_MEMORY;
        while ($size >= 1024) {
            $size /= 1024;
            $pos++;
        }
        return round($size, 2).' '.$format[$pos];
    }

    /**
     * Bootstrap code, facility to load subsystem here.
     */
    $_CLASS_PATH=array();

    function add_include_path($path){
        set_include_path(get_include_path().PATH_SEPARATOR.$path);
    }

    function add_class_path($path){
        global $_CLASS_PATH;

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
        $class=str_replace('_', '/', $class);
        $class=strtolower($class);

        if(substr_compare($class, 'clue/', 0, 5)==0){
            // Special treat for Clue\ classes. For they might reside in a phar file.
            $class=substr($class, 5);
            if(file_exists(__DIR__.'/'.$class.".php")){
                require_once __DIR__.'/'.$class.".php";
                return;
            }
        }
        else{
            foreach($_CLASS_PATH as $path){
                if(file_exists($path.'/'.$class.".php")){
                    require_once $path.'/'.$class.".php";
                    return;
                }
            }
        }
    }

    #第三方库应该放在lib目录
    add_class_path(APP_ROOT."/lib");
    add_include_path(APP_ROOT."/lib");

    #应用相关的代码
    add_class_path(DIR_SOURCE."/model");
    add_class_path(DIR_SOURCE."/class");
    add_class_path(DIR_SOURCE."/include");
    add_include_path(DIR_SOURCE."/include");

    spl_autoload_register("Clue\autoload_load");
}
?>
