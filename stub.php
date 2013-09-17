<?php
	/**
	 * Definitions before stub
	 *  APP_BASE	base url of the application
	 * 	APP_ROOT	folder of the application code
	 *
	 *  DIR_SOURCE	folder of source code (model, view, control)
	 *	DIR_SKIN	folder of the skin (change this to switch 'template')
	 *  DIR_CACHE	folder of cache contents
	 *  DIR_DATA	folder of application data
	 */

    // Common Definations
    if(!defined('CLI')) define('CLI', php_sapi_name()=="cli");
    if(!defined('DS'))  define("DS", DIRECTORY_SEPARATOR);  // directory separator
    if(!defined('NS'))  define('NS', "\\");                 // namespace separator

    # 服务器地址
    if(!defined('APP_SERVER') && !CLI) define('APP_SERVER', $_SERVER['HTTP_HOST']);
    if(!defined('APP_NAME')) define('APP_NAME', 'MyApp');

    # Disk路径
    if(!defined('APP_ROOT')){
        if(CLI){
            // 推断APP ROOT所在目录
            // 假设config.php在且仅在APP ROOT目录下
            $root=getcwd();
            while(is_dir($root) && !is_file("$root/config.php")){
                $root=dirname($root);
            }
            define('APP_ROOT', realpath($root));
        }
        else{
             define('APP_ROOT', dirname($_SERVER['SCRIPT_FILENAME']));
        }
    }

    # URL路径
    if(!defined('APP_BASE')) define('APP_BASE', preg_replace('|[\\\/]+|', '/', dirname($_SERVER['SCRIPT_NAME'])));

    # 常用路径
    if(!defined('DIR_SOURCE')) define('DIR_SOURCE', APP_ROOT.'/source');
    if(!defined('DIR_ASSET')) define('DIR_ASSET', APP_ROOT.'/asset');
    if(!defined('DIR_LOG')) define('DIR_LOG', APP_ROOT.'/log');
    if(!defined('DIR_CACHE')) define('DIR_CACHE', APP_ROOT.'/cache');
    if(!defined('DIR_DATA')) define('DIR_DATA', APP_ROOT.'/data');

    if(!CLI){
        if(!is_dir(DIR_CACHE)) mkdir(DIR_CACHE, 0775, true);
    }

    require_once __DIR__."/core.php";
    spl_autoload_register("Clue\\autoload_load");

    require_once __DIR__."/application.php";
    require_once __DIR__."/tool.php";
    require_once __DIR__."/asset.php";

?>
