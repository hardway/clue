<?php
	/**
	 * Definitions before stub
     *  APP_NAME        应用名，比如MyApp，在log/cache或者profile时会用到
	 *  APP_BASE	    base url of the application
	 * 	APP_ROOT	    folder of the application code
	 */

    // Common Definations
    if(!defined('CLI')) define('CLI', php_sapi_name()=="cli");
    if(!defined('DS'))  define("DS", DIRECTORY_SEPARATOR);  // directory separator
    if(!defined('NS'))  define('NS', "\\");                 // namespace separator

    // 自动推断Definition
    # Disk路径
    if(!defined('APP_ROOT')){
        if(CLI){
            // 推断APP ROOT所在目录
            // 假设stub.php在且仅在APP ROOT目录下
            $root=getcwd();
            while(is_dir($root) && $root!=DIRECTORY_SEPARATOR && !is_file("$root/stub.php")){
                $root=dirname($root);
            }
            define('APP_ROOT', realpath($root));
        }
        else{
             define('APP_ROOT', dirname($_SERVER['SCRIPT_FILENAME']));
        }
    }

    # 应用名称
    if(!defined('APP_NAME')) define('APP_NAME', basename(APP_ROOT));

    # 应用环境DEV/TEST/PRODUCTION
    # 默认为Production，后缀为php
    if(!defined("APP_ENV")){
        if(isset($_SERVER['APP_ENV']))
            define('APP_ENV', $_SERVER['APP_ENV']);
        elseif(isset($_SERVER['HTTP_HOST'])){
            define('APP_ENV', substr($_SERVER['HTTP_HOST'], strrpos($_SERVER['HTTP_HOST'], '.')+1));
        }
        else
            define("APP_ENV", 'production');
    }

    require_once __DIR__.'/autoload.php';
    require_once __DIR__."/application.php";
    require_once __DIR__."/tool.php";
?>
