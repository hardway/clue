<?php
    # URL路径
    if(!CLI && !defined('APP_BASE')) define('APP_BASE', trim(dirname($_SERVER['SCRIPT_NAME']), '/'));
    if(!CLI && !defined('APP_URL')){
        $app_scheme=isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : "http";
        define('APP_URL', $app_scheme.'://'.$_SERVER['SERVER_NAME'].APP_BASE);
    }

    // 全局函数
    function url_path($path){
        // $path=str_replace(' ', '%20', $path);
        $path=implode("/", array_map('rawurlencode', explode("/", $path)));
        return str_replace(APP_ROOT, APP_URL, $path);
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
    function url_slug($title, $limit=10){
        // 拆分为单词
        preg_match_all('/[a-z0-9\-_]+/i', $title, $m);
        $words=array_map('strtolower', array_filter($m[0], 'strlen'));

        // 防止name超限
        if(count($words) > $limit){
            $words=array_splice($words, 0, $limit);
        }

        // 合并
        $slug=implode("-", $words);
        $slug=preg_replace('/-+/', '-', $slug);

        return $slug;
    }

    /**
     * URL跳转链接
     */
    function url_follow($url, $current){
        if(empty($url)) return $current;

        $parts=parse_url(trim($url));

        // Another host
        if(isset($parts['host'])) return $url;
        if(isset($parts['scheme'])) return $url;

        $current=parse_url($current ?: $this->referer);

        $path=isset($current['path']) ? explode("/",  $current['path']) : array("");
        if(isset($parts['path'])){
            // Jump to root if path begins with '/'
            if(strpos($parts['path'],'/')===0) $path=array();

            // Remove tip file
            if(count($path)>1) array_pop($path);

            // Normalize path
            foreach(explode("/", $parts['path']) as $p){
                if($p=="."){
                    continue;
                }
                elseif($p=='..'){
                    if(count($path)>1) array_pop($path);
                    continue;
                }
                else{
                    array_push($path, $p);
                }
            }
        }

        // Build url
        $result=array();
        $result[]=$current['scheme'].'://';
        $result[]=$current['host'];
        $result[]=isset($current['port']) ? $current['port'] : "";
        $result[]=implode("/", $path);
        $result[]=isset($parts['query']) ? '?'.$parts['query'] : "";
        $result[]=isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return implode("", $result);
    }

    /**
     * 定位Asset
     * TODO: 根据dev/prod等不同的配置，也可以定位至不同的处理URL（即时生成或者预编译）
     * @param $asset string
     * @return $url 文件URL
     */
    function asset($asset){
        $mapping=\Clue\get_site_path_mapping();
        $path="asset/".trim($asset, '/ ');

        foreach(\Clue\get_site_path() as $c){
            if(file_exists($c.'/'.$path)){
                $url=APP_BASE.'/'.$mapping[$c].'/'.$path;
                return preg_replace('|/+|','/', $url)."?".filemtime("$c/$path");
            }
        }

        // Always return url, let web server handle it
        return APP_BASE.'/asset/'.$asset;
    }
