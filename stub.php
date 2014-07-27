<?php
	/**
	 * Definitions before stub
     *  APP_NAME        应用名，比如MyApp，在log/cache或者profile时会用到
	 *  APP_BASE	    base url of the application
	 * 	APP_ROOT	    folder of the application code
     *  SITE_DEFAULT
     *  SITE            如果有附加WEBSITE主题
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

    # 应用名称
    if(!defined('APP_NAME')) define('APP_NAME', basename(APP_ROOT));

    # URL路径
    if(!CLI){
        if(!defined('APP_BASE')) define('APP_BASE', trim('http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['SCRIPT_NAME']), '/'));
    }

    # SITE
    if(!defined("SITE_DEFAULT")) define('SITE_DEFAULT', APP_ROOT.'/site-default');

    // 全局函数
    function url_path($path){
        $path=str_replace(' ', '%20', $path);
        return str_replace(APP_ROOT, APP_BASE, $path);
    }

    function url_for($controller, $action='index', $params=array(), $base=APP_BASE){
        global $app;
        $url=$app['router']->reform($controller, $action, $params);
        $url=preg_replace('/\/+/', '/', $url);

        return $base.$url;
    }

    function url_for_ssl($controller, $action='index', $params=array()){
        global $config;

        $ssl=$config['ssl'];
        $base=$ssl ? preg_replace('/^http:/', 'https:', APP_BASE) : APP_BASE;
        return url_for($controller, $action, $params, $base);
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
        foreach(glob(APP_ROOT.'/site-default/'.$pattern) as $path){
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

    function asset($asset){
        $asset=trim($asset,'/ ');

        $path=APP_ROOT.'/'.SITE.'/asset/'.$asset;
        $default_path=APP_ROOT.'/site-default/asset/'.$asset;

        if(file_exists($path)){
            return '/asset/'.$asset."?".filemtime($path);
        }
        elseif(file_exists($default_path)){
            return '/default/asset/'.$asset."?".filemtime($default_path);
        }

        // Always return url, let web server handle it
        return '/asset/'.$asset;
    }

    /**
     * DEPRECATE, use site_asset and site_file instead
     */
    function asset_path($asset){return site_file("asset/".$asset); }

    require_once __DIR__.'/autoload.php';
    require_once __DIR__."/application.php";
    require_once __DIR__."/tool.php";
?>
