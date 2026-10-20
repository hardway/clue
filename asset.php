<?php
namespace Clue{
    class Asset{
        public $type;
        public $files=[];
        public $last_modified=null;
        public $total_size=0;

        function __construct($files=null){
            $files=is_array($files) ? $files : [$files];

            // 支持的路径写法
            // foo.js ==> SITE/asset/foo.js
            // /resource/foo/bar.css ==> SITE/resource/foo/bar.css
            $files=array_map(function($file){
                if($file[0]!='/' && strpos($file, 'asset')===false) $file="asset/$file";
                return trim($file, '/');
            }, $files);

            if(is_array($files)) while($f=array_shift($files)){
                // TODO: 支持**
                // 支持通配符
                if(strpos($f, "*")!==false){
                    foreach(array_reverse(\Clue\site_file_glob($f)) as $_){
                        array_unshift($files, $_);
                    }
                    continue;
                }

                if(!file_exists($f)) $f=\Clue\site_file($f);
                if(!file_exists($f)) continue; // 找不到资源情况下不会导致错误
                $this->add($f);
            }
        }

        function add($files){
            if(!is_array($files)) $files=array($files);

            foreach($files as $f){
                if(!in_array($f, $this->files)){
                    $this->files[]=$f;
                    $this->last_modified=max($this->last_modified, filemtime($f));
                    $this->total_size+=filesize($f);
                }
            }
        }

        function compile(){
            $ext=array();

            // Combine asset file contents
            $content="";
            $less_content="";
            $translate_less=false;

            foreach($this->files as $f){
                $extension=pathinfo($f, PATHINFO_EXTENSION);

                if($extension=='less'){
                    $extension='css';
                    $translate_less=true;
                    $less_content.=@file_get_contents($f);
                }
                else{
                    $content.=@file_get_contents($f);
                }

                @$ext[$extension]++;
            }

            if($translate_less){
                $content.=self::compile_less($less_content);
            }

            if(count($ext)==1){
                $this->type=array_keys($ext);
                $this->type=$this->type[0];
            }

            return $content;
        }

        /**
         * 编译 LESS，结果按 (lessc版本 + 源码内容) 的哈希缓存
         * 源码或 lessc 变化时哈希随之变化，因此不会返回过期结果
         */
        static function compile_less($less_content){
            $lessc_file=__DIR__.'/vendor/lessc.php';
            $key='clue_less_'.md5(@filemtime($lessc_file).'|'.$less_content);

            if(function_exists('apcu_fetch')){
                $hit=apcu_fetch($key, $ok);
                if($ok) return $hit;
            }

            $dir=sys_get_temp_dir().'/clue-less-cache';
            $file="$dir/$key.css";
            if(!function_exists('apcu_fetch') && is_file($file)) return file_get_contents($file);

            include_once $lessc_file;
            $less=new \lessc();
            $css=$less->compile($less_content);

            if(function_exists('apcu_store')){
                apcu_store($key, $css);
            }
            else{
                // 原子写入：先写临时文件再 rename，避免并发读到半成品
                if(!is_dir($dir)) @mkdir($dir, 0775, true);
                $tmp=@tempnam($dir, 'tmp');
                if($tmp !== false){
                    $written=file_put_contents($tmp, $css) !== false;
                    if(!($written && @rename($tmp, $file))) @unlink($tmp);
                }
            }
            return $css;
        }

        function compress($content, $type){
            if($type=='css')
                return $this->compress_css($content);
            elseif($type=='js')
                return $this->compress_js($content);
            elseif($type=='jpg'){ // Only the first file will be supported
                return $this->compress_jpeg($this->files[0]);
            }
            else
                return $content;
        }

        function compress_jpeg($file){
            $im=new \Imagick($file);
            $im->setImageFormat("jpg");
            $im->setImageCompressionQuality(85);
            $im->stripImage();

            return $im->getImageBlob();
        }

        function compress_js($js){
            // use YUI-compressor
            $yui=getenv("YUI_COMPRESSOR");
            if(empty($yui)) throw new \Exception("Please specify location of yui compressor in environment variable YUI_COMPRESSOR");

            $raw_file=tempnam(sys_get_temp_dir(), 'clue');
            $min_file=tempnam(sys_get_temp_dir(), 'clue');

            file_put_contents($raw_file, $js);
            $cmd=sprintf("java -jar %s --type js -o %s %s", escapeshellarg($yui), escapeshellarg($min_file), escapeshellarg($raw_file));
            passthru($cmd);
            $min=file_get_contents($min_file);

            unlink($min_file);
            unlink($raw_file);
            return $min;
        }

        function compress_css($css){
            $css=preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!','',$css);
            $css=preg_replace('/(\n|\r)+/', "\n", $css);
            $css=preg_replace('/\t|\s{2+}/', " ", $css);
            $css=preg_replace('/\s*(\{|\}|;|:|>)\s*/', '$1', $css);

            return $css;
        }

        function dump(){
            \Clue\Tool::http_auto_cache($this->last_modified, md5($this->last_modified.'.'.$this->total_size));

            $content=$this->compile();

            // Output contents according to asset type
            header("Cache-Control: must-revalidate");
            switch($this->type){
                case "css":
                    header("Content-Type: text/css");
                    break;
                case 'js':
                    header("Content-Type: application/javascript");
                    break;
                default:
                    header("Content-Type: text/plain");
                    break;
            }

            header('Content-Length: '.strlen($content));
            echo $content;
        }
    }
}
