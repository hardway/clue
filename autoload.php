<?php
// Class Path和Include Path设定
namespace Clue{
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

    #第三方库应该放在lib目录
    add_class_path(APP_ROOT."/lib");
    add_include_path(APP_ROOT."/lib");

    #应用相关的代码
    add_class_path(APP_ROOT."/source/model");
    add_class_path(APP_ROOT."/source/class");
    add_include_path(APP_ROOT."/source/include");

    spl_autoload_register("Clue\autoload_load");
}
