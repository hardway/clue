<?php
    # URL路径
    if(!CLI && !defined('APP_BASE')) define('APP_BASE', trim(dirname($_SERVER['SCRIPT_NAME']), '/'));
    if(!CLI && !defined('APP_URL')) define('APP_URL', $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'].APP_BASE);

    // 全局函数
    function url_path($path){
        $path=str_replace(' ', '%20', $path);
        return str_replace(APP_ROOT, APP_BASE, $path);
    }

    // TODO: move these to url.php ?
    function url_for($controller, $action='index', $params=array()){
        global $app;
        $url=$app['router']->reform($controller, $action, $params);
        return APP_URL.$url;
    }

    function url_for_ssl($controller, $action='index', $params=array()){
        global $config;

        $url=url_for($controller, $action, $params);

        if($config['ssl']){
            $url=preg_replace('/^http:/', 'https:', $url);
        }

        return $url;
    }

    // 根据title和id生成slug
    function url_slug($title){
        // 拆分为单词
        preg_match_all('/[a-z0-9\-_]+/i', $title, $m);
        $words=array_map('strtolower', array_filter($m[0], 'strlen'));

        // 合并
        $slug=implode("-", $words);
        $slug=preg_replace('/-+/', '-', $slug);

        return $slug;
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
