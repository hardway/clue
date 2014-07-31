<?php
    # URL路径
    if(!CLI && !defined('APP_BASE')) define('APP_BASE', trim(dirname($_SERVER['SCRIPT_NAME']), '/'));
    if(!CLI && !defined('APP_URL')) define('APP_URL', 'http://'.$_SERVER['SERVER_NAME'].APP_BASE);

    // 全局函数
    function url_path($path){
        $path=str_replace(' ', '%20', $path);
        return str_replace(APP_ROOT, APP_BASE, $path);
    }

    // TODO: move these to url.php ?
    function url_for($controller, $action='index', $params=array(), $base=APP_BASE){
        global $app;
        $url=$app['router']->reform($controller, $action, $params);
        return $base.$url;
    }

    function abs_url_for($controller, $action='index', $params=array()){
        return APP_URL.url_for($controller, $action, $params);
    }

    function url_for_ssl($controller, $action='index', $params=array()){
        global $config;

        $ssl=$config['ssl'];
        $base=$ssl ? preg_replace('/^http:/', 'https:', APP_BASE) : APP_BASE;
        return url_for($controller, $action, $params, $base);
    }

    function abs_url_for_ssl($controller, $action='index', $params=array()){
        return APP_URL.url_for($controller, $action, $params);
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
