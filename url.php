<?php
    # URL路径
    if(!CLI && !defined('APP_BASE')) define('APP_BASE', '/'.trim(dirname($_SERVER['SCRIPT_NAME']), '/'));
    if(!CLI && !defined('APP_URL')){
        $app_scheme=isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : "http";

        // 不使用常规端口的情况
        $server_port=intval($_SERVER['SERVER_PORT']);
        $app_port=in_array($server_port, array(80, 443)) ? '' : ":$server_port";

        // TODO: 对于非常规的情况，其实在config中自定义APP_URL省很多事
        @define('APP_URL', $app_scheme.'://'.$_SERVER['SERVER_NAME'].$app_port.APP_BASE);
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

        $url=APP_URL.$app['router']->reform($controller, $action, $params);

        return url_normalize($url);
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
        if(empty($current)) return $url;

        $parts=parse_url(trim($url));

        // Another host
        if(isset($parts['host'])) return $url;
        if(isset($parts['scheme'])) return $url;

        $current=parse_url($current);

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
        $path="asset/".trim($asset, '/ ');
        $base=APP_URL;

        // 如果定义了CDN，则从CDN列表中随机抽取
        global $app;
        static $cdns=null;
        if($cdns || (isset($app['cdn']) && is_array($app['cdn']))){
            if($cdns===null) $cdns=$app['cdn'];

            $base=$cdns[array_rand($cdns)];
        }

        $mapping=\Clue\get_site_path_mapping();
        foreach(\Clue\get_site_path() as $c){
            $filepath=$c.'/'.$path;
            if(file_exists($filepath)){
                return url_path($filepath, $base);
            }
        }

        // Always return url, let web server handle it
        return url_normalize($base.'/asset/'.$asset);
    }
