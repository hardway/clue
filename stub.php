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
    if(!defined('DS'))  define("DS", DIRECTORY_SEPARATOR);	// directory separator
    if(!defined('NS'))  define('NS', "\\");					// namespace separator

    if(!defined('APP_ROOT')) define('APP_ROOT', realpath(dirname($_SERVER['SCRIPT_FILENAME'])));
    if(!defined('APP_BASE')) define('APP_BASE', preg_replace('|[\\\/]+|', '/', dirname($_SERVER['SCRIPT_NAME'])));

    if(!defined('DIR_SITE')) define('DIR_SITE', realpath(APP_ROOT));
    if(!defined('DIR_SOURCE')) define('DIR_SOURCE', realpath(APP_ROOT.'/source'));

    define('DIR_SKIN_DEFAULT', realpath(APP_ROOT.'/skin'));
    if(!defined('DIR_SKIN')) define('DIR_SKIN', DIR_SKIN_DEFAULT);


    if(!defined('DIR_CACHE')) define('DIR_CACHE', realpath(APP_ROOT.'/cache'));
    if(!defined('DIR_DATA')) define('DIR_DATA', realpath(APP_ROOT.'/data'));

    require_once __DIR__."/core.php";
    require_once __DIR__."/application.php";
    require_once __DIR__."/tool.php";
    require_once __DIR__."/asset.php";

    spl_autoload_register("Clue\\autoload_load");
?>
