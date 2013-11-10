<?php
	/**
	 * Definitions before stub
	 *  APP_BASE	base url of the application
	 * 	APP_ROOT	folder of the application code
	 *
	 *  DIR_SOURCE	folder of source code (model, view, control)
	 *	DIR_SKIN	folder of the skin (change this to switch 'template')
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
            while(is_dir($root) && $root!=DIRECTORY_SEPARATOR && !is_file("$root/config.php")){
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
    if(!defined('DIR_DATA')) define('DIR_DATA', APP_ROOT.'/data');

    // 全局函数
    function url_path($path){
        return preg_replace('|[\\\/]+|', '/', str_replace(APP_ROOT, APP_BASE, $path));
    }

    function url_for($controller, $action='index', $params=array()){
        global $app;
        $url=APP_BASE.$app['router']->reform($controller, $action, $params);
        $url=preg_replace('/\/+/', '/', $url);

        return "http://".APP_SERVER.$url;
    }

    function url_for_ssl(){
        global $app;

        $url=call_user_func_array("url_for", func_get_args());

        if($app['config']['ssl'])
            $url=preg_replace('/^http:/', 'https:', $url);

        return $url;
    }

    /**
     * 根据SITE/THEME定义，返回所需要的文件路径
     * 如果SITE/THEME为找到，返回APP_ROOT下的文件
     */
    function site_file($path){
        if(strpos($path, APP_ROOT)===0) return $path;   // 绝对路径，直接返回

        $path=trim($path, '/');

        if(defined("THEME") && THEME && file_exists(APP_ROOT.'/'.THEME."/$path")){
            return APP_ROOT.'/'.THEME."/$path";
        }
        else
            return APP_ROOT."/$path";
    }

    /**
     * 类似glob()函数，但是同时返回site和default下的文件
     */
    function site_glob($pattern){
        $files=array();
        foreach(glob(APP_ROOT.'/'.$pattern) as $path){
            $files[basename($path)]=$path;
        }
        foreach(glob(APP_ROOT.'/'.THEME.'/'.$pattern) as $path){
            $files[basename($path)]=$path;
        }

        ksort($files);

        return array_values($files);
    }

    function asset_path($asset){
        $path=DIR_ASSET."/$asset";

        if(defined("THEME") && THEME && file_exists(APP_ROOT.'/'.THEME."/asset/$asset")){
            $path=APP_ROOT.'/'.THEME."/asset/$asset";
        }

        return $path;
    }

    function asset($asset){
        $path=asset_path($asset);

        return url_path($path).(file_exists($path) ? '?'.filemtime($path) : "");
    }

    require_once __DIR__."/core.php";
    spl_autoload_register("Clue\\autoload_load");

    require_once __DIR__."/application.php";
    require_once __DIR__."/tool.php";
    require_once __DIR__."/asset.php";

?>
