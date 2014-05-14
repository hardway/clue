<?php
	/**
	 * Definitions before stub
     *  APP_SERVER  主机名，类似shop4x.net，用于url_for系列函数
     *  APP_NAME    应用名，比如MyApp，在log/cache或者profile时会用到
	 *  APP_BASE	base url of the application
	 * 	APP_ROOT	folder of the application code
     *  SITE        如果有附加WEBSITE主题
	 */

    // Common Definations
    if(!defined('CLI')) define('CLI', php_sapi_name()=="cli");
    if(!defined('DS'))  define("DS", DIRECTORY_SEPARATOR);  // directory separator
    if(!defined('NS'))  define('NS', "\\");                 // namespace separator

    // 自动推断核心definition

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

    // 全局函数
    function url_path($path){
        $path=str_replace(' ', '%20', $path);
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
     * 根据SITE定义，返回所需要的文件路径
     * 如果SITE为找到，返回APP_ROOT下的文件
     *
     * @return $path 文件路径
     */
    function site_file($path){
        if(strpos($path, APP_ROOT)===0) return $path;   // 已经是绝对路径，直接返回

        $path=trim($path, '/');

        if(defined("SITE") && SITE && file_exists(APP_ROOT.'/'.SITE."/$path")){
            return APP_ROOT.'/'.SITE."/$path";
        }
        else
            return APP_ROOT."/$path";
    }

    /**
     * 类似site_file()和glob()合体，同时返回site和default下的匹配文件
     */
    function site_file_glob($pattern){
        $files=array();
        foreach(glob(APP_ROOT.'/'.$pattern) as $path){
            $files[basename($path)]=$path;
        }

        if(defined('SITE')) foreach(glob(APP_ROOT.'/'.SITE.'/'.$pattern) as $path){
            $files[basename($path)]=$path;
        }

        ksort($files);

        return array_values($files);
    }

    /**
     * 根据SITE定位Asset
     * TODO: 根据dev/prod等不同的配置，也可以定位至不同的处理URL（即时生成或者预编译）
     * @param $asset string
     * @return $url 文件URL
     */
    function site_asset($asset){
        $path=site_file('asset/'.$asset);

        return url_path($path).(file_exists($path) ? '?'.filemtime($path) : "");
    }

    /**
     * DEPRECATE, use site_asset and site_file instead
     */
    function asset($asset){ return site_asset($asset); }
    function asset_path($asset){return site_file("asset/".$asset); }

    require_once __DIR__.'/autoload.php';
    require_once __DIR__."/application.php";
    require_once __DIR__."/tool.php";
?>
