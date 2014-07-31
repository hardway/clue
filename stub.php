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
    if(!CLI && !defined('APP_BASE')) define('APP_BASE', trim(dirname($_SERVER['SCRIPT_NAME']), '/'));
    if(!CLI && !defined('APP_URL')) define('APP_URL', 'http://'.$_SERVER['SERVER_NAME'].APP_BASE);

    // 全局函数
    function url_path($path){
        $path=str_replace(' ', '%20', $path);
        return str_replace(APP_ROOT, APP_BASE, $path);
    }

    function url_for($controller, $action='index', $params=array(), $base=APP_BASE){
        global $app;
        $url=$app['router']->reform($controller, $action, $params);
        return $base.$url;
    }

    function url_for_ssl($controller, $action='index', $params=array()){
        global $config;

        $ssl=$config['ssl'];
        $base=$ssl ? preg_replace('/^http:/', 'https:', APP_BASE) : APP_BASE;
        return url_for($controller, $action, $params, $base);
    }

    /**
     * 定位Asset
     * TODO: 根据dev/prod等不同的配置，也可以定位至不同的处理URL（即时生成或者预编译）
     * @param $asset string
     * @return $url 文件URL
     */
    function asset($asset){
        global $_SITE_PATH_MAPPING;
        $path="asset/".trim($asset, '/ ');

        foreach(\Clue\get_site_path() as $c){
            if(file_exists($c.'/'.$path)){
                $url=APP_BASE.'/'.$_SITE_PATH_MAPPING[$c].'/'.$path;
                return preg_replace('|/+|','/', $url)."?".filemtime("$c/$path");
            }
        }

        // Always return url, let web server handle it
        return APP_BASE.'/asset/'.$asset;
    }

    require_once __DIR__.'/autoload.php';
    require_once __DIR__."/application.php";
    require_once __DIR__."/tool.php";
?>
