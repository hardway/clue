<?php
    namespace Clue;

    # URL路径
    if(php_sapi_name() !== 'cli' && !defined('APP_BASE')){
        define('APP_BASE', '/'.trim(dirname(str_replace($_SERVER['DOCUMENT_ROOT'], '', $_SERVER['SCRIPT_FILENAME'])), '/.'));
    }
    if(php_sapi_name() !== 'cli' && !defined('APP_URL')){
        $scheme=@$_SERVER['REQUEST_SCHEME'] ?: "http";
        @define('APP_URL', $scheme.'://'.$_SERVER['HTTP_HOST'].APP_BASE);
    }

    /**
     * 将绝对路径转化为对应可访问网址
     */
    function url_path($path, $app_url=null){
        $app_url=$app_url ?: APP_URL;
        $mtime=file_exists($path) ? filemtime($path) : null;

        $mapping=\Clue\get_site_path_mapping();

        foreach($mapping as $folder=>$map){
            if(strpos($path, $folder)===0){
                $path=substr($path, strlen($folder));
                $path=implode("/", array_map('rawurlencode', explode("/", $path)));

                $url=rtrim($app_url, '/').path_normalize('/'.$map.'/'.$path);

                if($mtime) $url.="?".$mtime;
                return $url;
            }
        }

        return $app_url;
    }

    /**
     * 路径规范化
     */
    function path_normalize($path){
        $is_root=$path[0]=='/';

        $p=[];
        foreach(explode("/", $path) as $f){
            if($f=='.' || empty($f)) continue;
            elseif($f=='..'){
                array_pop($p);
            }
            else{
                array_push($p, $f);
            }
        }
        return ($is_root ? '/' : "") . implode("/", $p);
    }

    /**
     * 网址规范化
     * @param url 字符串，或者已经痛parse_url解开的数组，用于合成新的url
     */
    function url_normalize($url){
        $u=is_array($url) ? $url : parse_url($url);

        $url="";

        // 默认使用//作为动态scheme
        $url.=isset($u['scheme']) ? $u['scheme'].'://' : "//";
        if(isset($u['user'])){
            $url.=$u['user'];
            if(isset($u['pass'])){
                $url.=":".$u['pass'];
            }
            $url.="@";
        }

        if(isset($u['host'])) $url.=$u['host'];
        if(isset($u['port'])) $url.=":".$u['port'];
        if(isset($u['path'])) $url.=path_normalize($u['path']);
        if(isset($u['query'])) $url.="?".$u['query'];
        if(isset($u['fragment'])) $url.="#".$u['fragment'];

        return $url;
    }

    /**
     * 重设URL中的查询参数
     * @param $query 查询参数
     */
    function url_query($url, array $query){
        $u=parse_url($url);
        parse_str(@$u['query'], $q);

        foreach($query as $k=>$v){
            $q[$k]=$v;
        }

        $u['query']=http_build_query($q);

        return url_normalize($u);
    }

    function url_for($controller, $action='index', $params=array()){
        global $app;

        if(!isset($app['router'])){
            throw new \RuntimeException('Router not configured for url_for');
        }

        $url=APP_URL.$app['router']->reform($controller, $action, $params);

        return url_normalize($url);
    }

    function url_for_ssl($controller, $action='index', $params=array()){
        global $config;

        $url=url_for($controller, $action, $params);

        if(!empty($config['ssl'])){
            $url=str_replace('http://', 'https://', $url);
        }
        return $url;
    }

    // 根据title和id生成slug
    function url_slug($title, $limit=10){
        // 拆分为单词
        preg_match_all('/[a-z0-9\-_]+/i', $title, $m);
        $words=array_map('strtolower', array_filter($m[0], 'strlen'));

        if(count($words) > $limit){
            $words=array_slice($words, 0, $limit);
        }

        // 合并
        $slug=implode("-", $words);
        $slug=preg_replace('/-+/', '-', $slug);

        return $slug;
    }

    /**
     * 根据parts重新组合url
     * parts的规格来自于parse_url()的结果
     */
    function url_build($parts){
        $result=array();
        $result[]=$parts['scheme'].'://';
        $result[]=$parts['host'];
        $result[]=isset($parts['port']) ? ':'.$parts['port'] : "";
        $result[]=$parts['path'];
        $result[]=isset($parts['query']) ? '?'.$parts['query'] : "";
        $result[]=isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return implode("", $result);
    }

    /**
     * URL跳转链接
     */
    function url_follow($url, $current){
        if(empty($url)) return $current;
        if(empty($current)) return $url;

        $parts=parse_url(trim($url));

        // 完整的绝对 URL，直接返回
        if(isset($parts['host']) && isset($parts['scheme'])) return $url;

        $current=parse_url($current);

        $path=isset($current['path']) ? explode("/", $current['path']) : [""];
        if(isset($parts['path'])){
            // 以 / 开头则跳转到根
            if($parts['path'][0] === '/'){
                $path=[];
            }
            else{
                // 去掉当前路径末尾的文件名
                if(count($path) > 1) array_pop($path);
            }

            // 用 path_normalize 规范化
            $combined=implode("/", $path).'/'.$parts['path'];
            $parts['path']=path_normalize($combined);
        }

        return url_build(array_merge($current, $parts));
    }

    /**
     * 定位Asset
     * TODO: 根据dev/prod等不同的配置，也可以定位至不同的处理URL（即时生成或者预编译）
     * @param $asset string
     * @return $url 文件URL
     */
    function asset($asset){
        $path="asset/".trim($asset, '/ ');
        $base=APP_URL;

        global $app;
        static $cdns=null;
        if($cdns || (isset($app['cdn']) && is_array($app['cdn']))){
            if($cdns===null) $cdns=$app['cdn'];

            $base=$cdns[array_rand($cdns)];
        }

        foreach(\Clue\get_site_path() as $c){
            $filepath=$c.'/'.$path;
            if(file_exists($filepath)){
                return url_path($filepath, $base);
            }
        }

        $fragment="";

        if(isset($app['config']['asset'][$asset])){
            $bundle=new \Clue\Asset($app['config']['asset'][$asset]);
            $fragment="?t=$bundle->last_modified.$bundle->total_size";
        }

        return url_normalize($base.'/asset/'.$asset.$fragment);
    }
