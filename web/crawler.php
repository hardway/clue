<?php
/**
 * Usage:
 * class SomeCrawler() {
 *      function crawl_list($url, $html){
 *          $dom=new Clue\Web\Parser($html);
 *          ...
 *          $this->submit('item', ...);
 *          $this->queue('list', next url);
 *      }
 *      function process_list($data){
 *          // Process data or save somewhere (like database)
 *      }
 *
 *      function crawl_item($url){}
 *      function process_item($data){}
 * }
 *
 * $c=new SomeCrawler();
 * $c->queue('list', 'http://xxx.xxx/xxx');
 * $c->crawl();
 */

namespace Clue\Web;

class Crawler{
    function __construct(array $options=array()){
        $default_options=[
            'cache_dir'=>getcwd().'/cache',
            'cache_ttl'=>86400*30,
            'agent'=>"Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36",
            'cookie'=>getcwd().'/cookie',
        ];

        $this->options=$options=$options+$default_options;

        $this->client=new Client();
        $this->client->set_agent($this->options['agent']);
        $this->client->enable_cache($options['cache_dir'], $options['cache_ttl']);
        $this->client->enable_cookie($this->options['cookie']);

        $this->retry_download=5;

        $this->debug=isset($options['debug']) ? $options['debug'] : false;
        $this->delay=isset($options['delay']) ? $options['delay'] : 0;


        $this->pending=[];
        $this->visited=[];
    }

    /**
     * Queue Layout:
     * [
     *      0=> ['depth'=>0, ...],
     *          ['depth'=>0, ...]
     *      1=> ['depth'=>1, ...],
     *          ['depth'=>1, ...],
     *      2=> ['depth'=>2, ...],          <-- push
     *          ['depth'=>2, ...],
     *          ['depth'=>2, ...]           --> crawl
     * ]
     *
     *
     *
     * $context=[
     *      type
     *      depth       深度优先使用depth参数，否则默认为0（广度优先）
     *      id          用于判断是否访问过，缺省使用URL进行比对
     * ]
     */
    function queue($context, $url){
        $task=is_array($context) ? $context : ['TYPE'=>$context];
        $task['URL']=$url;
        $task['DEPTH'] = $depth = isset($task['DEPTH']) ? $task['DEPTH'] : 0;

        if(!isset($this->pending[$depth])) $this->pending[$depth]=[];
        array_push($this->pending[$depth], $task);
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
        error_log(vsprintf(func_get_args()[0], array_slice(func_get_args(), 1)));
    }
    function warn(){
        \Clue\CLI::warning('[WARN] '.vsprintf(func_get_args()[0], array_slice(func_get_args(), 1))."\n");
    }
    function error(){
        // error_log('[ERROR] '.vsprintf(func_get_args()[0], array_slice(func_get_args(), 1)));
        \Clue\CLI::error('[ERROR] '.vsprintf(func_get_args()[0], array_slice(func_get_args(), 1))."\n");
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
            return $this->$handler($data);
        }
        else{
            $this->log("Submit [$type]:\n".print_r($data, true).".\n");
        }
    }

    function crawl(){
        while(!empty($this->pending)){
            $depth=max(array_keys($this->pending));
            $t=array_shift($this->pending[$depth]);
            if(empty($this->pending[$depth])) unset($this->pending[$depth]);

            if(!$this->validate_url($t['URL'])){
                $this->warn("Invalid url: %s", $t['URL']);
                continue;
            }

            // 检查是否访问过
            $hash=isset($t['ID']) ? $t['TYPE'].'/'.$t['ID'] : $t['URL'];
            if(in_array($hash, $this->visited)) continue;

            $this->log("[%s] %s", $t['TYPE'], $t['URL']);

            $action="crawl_".$t['TYPE'];
            $content=$this->download_page($t['URL']);
            $data=$this->$action($t['URL'], $content, $t);

            $this->visited[]=$hash;

            if(is_array($data)){
                if(!isset($data['url'])) $data['url']=$t['URL'];
                $this->submit($t['TYPE'], $data);
            }
        }
    }
}
