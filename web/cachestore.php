<?php
    namespace Clue\Web;

    /* Storage layout
        CACHE
            \_ A867                                 Hash Prefix
                \_ A86798348c8j8x230k99             Hash
                    \_ meta                         unserizlied data
                    |_ 20130413113412               Content version

        META=array(
            'url'=>'http://www.google.com',
            'revisions'=>array(
                'yyyymmddhhmmss', 'yyyymmddhhmmss', ...
            )
        )
    */
    class CacheStore{
        private $cache_dir;
        private $cache_ttl;

        function __construct($cache_dir, $cache_ttl=86400){
            // Make sure the cache directory exists
            if(!is_dir($cache_dir)){
                @mkdir($cache_dir, 0775, true);
                if(!is_dir($cache_dir)){
                    throw new \Exception("Cache directory didn't exist and can't be created: $cache_dir");
                }
            }
            $this->cache_dir=$cache_dir;
            $this->cache_ttl=$cache_ttl;
        }

        private function _cache_folder($url){
            $hash=md5($url);

            return sprintf("%s/%s/%s", $this->cache_dir, substr($hash, 0, 4), $hash);
        }

        function destroy($url){
            $folder=$this->_cache_folder($url);
            if(!file_exists($folder)) return true;

            foreach(scandir($folder) as $f){
                if(is_file("$folder/$f")) @unlink("$folder/$f");
            }
            return rmdir($folder);
        }

        function get($url){
            $folder=$this->_cache_folder($url);

            if(!is_dir($folder) || !file_exists("$folder/content")) return false;

            $outdated=filemtime("$folder/content")+$this->cache_ttl < time();
            if($outdated) return false;

            $meta=json_decode(file_get_contents("$folder/meta"), true);
            $gzcontent=file_get_contents("$folder/content");
            $content=gzinflate(substr($gzcontent,10,-8));

            return  [$content, $meta];
        }

        function put($url, $content, $meta){
            $folder=$this->_cache_folder($url);

            if(!is_dir($folder)) mkdir($folder, 0775, true);

            //TODO: 如果content已经存在，并且outdated, 保存revision
            file_put_contents("$folder/content", gzencode($content));
            file_put_contents("$folder/meta", json_encode($meta));

            return true;
        }
    }
