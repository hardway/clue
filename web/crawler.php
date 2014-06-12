<?php
namespace Clue\Web;
class Crawler{
    function __construct(array $options=array()){
        $this->client=new Client();
        $this->client->enable_cache("cache", 86400*3650);

        $this->retry_download=5;

        $this->debug=isset($options['debug']) ? $options['debug'] : false;
        $this->delay=isset($options['delay']) ? $options['delay'] : 0;

        $this->pending=[];
        $this->visited=[];
    }

    function queue($type, $url, $priority=0){
        $task=['type'=>$type, 'url'=>$url];

        if($priority>0){
            array_unshift($this->pending, $task);
        }
        else{
            array_push($this->pending, $task);
        }
    }

    function download_page($url){
        $retry=0;

        $html=$this->client->get($url);
        while(strlen($html)<100 && $retry<$this->retry_download){
            echo "Retry...";
            $retry++;
            $this->client->open($url, true);
            $html=$this->client->get($url);
            echo "\n";
        }

        // Traffic Control
        if(!$this->client->cache_hit && $this->delay){
            $this->log("Traffic Delay: %ds\n", $this->delay);
            sleep($this->delay);
        }

        return $html;
    }

    function download_image($url, $dest){
        $retry=0;

        exec("wget -qO \"$dest\" \"$url\"");

        // Traffic Control
        if($this->delay){
            $this->log("Traffic Delay: %ds\n", $this->delay);
            sleep($this->delay);
        }

        return file_exists($dest) ? $dest : false;
    }

    function log(){
        if($this->debug){
            $args = func_get_args();
            $fmt=array_shift($args);
            fputs(STDERR, vsprintf($fmt, $args));
        }
    }
    function warn(){
        error_log('[WARN] '.vsprintf(func_get_args()[0], array_slice(func_get_args(), 1)));
    }
    function error(){
        $fmt=array_shift($args);
        error_log('[ERROR] '.vsprintf(func_get_args()[0], array_slice(func_get_args(), 1)));
        exit();
    }

    // 检查地址是否合规，并且遵守robots.txt
    function validate_url($url){
        if(strlen(trim($url))==0) return false;

        $info=parse_url($url);
        if(!in_array($info['scheme'], array('http', 'https'))) return false;

        // TODO: 检测robots.txt

        return true;
    }

    function submit($type, $data){
        $handler="process_".$type;
        if(method_exists($this, $handler)){
            $this->$handler($data);
        }
        else{
            $this->log("Submit [$type]:\n".print_r($data, true).".\n");
        }
    }

    function crawl(){
        while($t=array_shift($this->pending)){
            if(!$this->validate_url($t['url'])){
                $this->warn("Invalid url: %s", $t['url']);
                continue;
            }

            if(in_array($t['url'], $this->visited)) continue;

            $this->log("[%s] %s\n", $t['type'], $t['url']);

            $action="crawl_".$t['type'];
            $content=$this->download_page($t['url']);
            $data=$this->$action($t['url'], $content);
            $this->visited[]=$t['url'];

            if(is_array($data)){
                if(!isset($data['url'])) $data['url']=$t['url'];
                $this->submit($t['type'], $data);
            }
        }
    }
}
