<?php
namespace Clue{
    /**
     * Bootstrap code, facility to load subsystem here.
     */
    $_CLASS_PATH=array();

    function ary2obj($array) {
        if(!is_array($array)) {
            return $array;
        }

        $object = new stdClass();
        if (is_array($array) && count($array) > 0) {
          foreach ($array as $name=>$value) {
             $name = strtolower(trim($name));
             if (!empty($name)) {
                $object->$name = ary2obj($value);
             }
          }
          return $object;
        }
        else {
          return FALSE;
        }
    }

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

    add_class_path(DIR_SOURCE."/model");
    add_class_path(DIR_SOURCE."/class");
    add_class_path(DIR_SOURCE."/include");
    add_include_path(DIR_SOURCE."/include");

    spl_autoload_register("Clue\autoload_load");
}
?>
