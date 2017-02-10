<?php
namespace Clue{
    class Asset{
        public $type;
        public $files;
        public $last_modified;

        function __construct($files=null){
            $this->files=array();
            if($files) $this->add($files);
        }

        function add($files){
            if(!is_array($files)) $files=array($files);

            foreach($files as $f){
                if(!in_array($f, $this->files)){
                    $this->files[]=$f;
                    $this->last_modified=max($this->last_modified, filemtime($f));
                }
            }
        }

        function compile(){
            $ext=array();

            // Combine asset file contents
            $content="";
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
                include_once __DIR__.'/vendor/lessc.php';
                $less=new \lessc();
                $content.=$less->compile($less_content);
            }

            if(count($ext)==1){
                $this->type=array_keys($ext);
                $this->type=$this->type[0];
            }

            return $content;
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
            $cmd=sprintf("java -jar %s --type js -o %s %s", $yui, $min_file, $raw_file);
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
            \Clue\Tool::http_auto_cache($this->last_modified, md5(implode(",", $this->files)));

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
