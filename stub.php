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

    # 整个项目文件路径
    if(!defined('APP_ROOT')) define('APP_ROOT', dirname($_SERVER['SCRIPT_FILENAME']));
    # 项目访问URL根
    if(!defined('APP_BASE')) define('APP_BASE', preg_replace('|[\\\/]+|', '/', dirname($_SERVER['SCRIPT_NAME'])));

    if(!defined('DIR_SOURCE')) define('DIR_SOURCE', APP_ROOT.'/source');
    if(!defined('DIR_ASSET')) define('DIR_ASSET', APP_ROOT.'/asset');
    if(!defined('DIR_LOG')) define('DIR_LOG', APP_ROOT.'/log');
    if(!defined('DIR_CACHE')) define('DIR_CACHE', APP_ROOT.'/cache');
    if(!defined('DIR_DATA')) define('DIR_DATA', APP_ROOT.'/data');

    if(!CLI){
        if(!is_dir(DIR_CACHE)) mkdir(DIR_CACHE, 0775, true);
    }

    require_once __DIR__."/core.php";
    require_once __DIR__."/application.php";
    require_once __DIR__."/tool.php";
    require_once __DIR__."/asset.php";

    spl_autoload_register("Clue\\autoload_load");
?>
