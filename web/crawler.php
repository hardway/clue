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
    use \Clue\Traits\Events;

    function __construct(array $options=array()){
        $default_options=[
            'cache_dir'=>'/tmp/crawler',
            'cache_ttl'=>86400*30,
            'agent'=>"Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.37",
            'cookie'=>getcwd().'/cookie',
            'debug_file'=>null,             // 最近下载内容保存为临时文件，方便调试
        ];

        $this->options=$options=$options+$default_options;

        $this->client=new Client();
        $this->client->set_agent($this->options['agent']);
        $this->client->enable_cache($options['cache_dir'], $options['cache_ttl']);

        if($this->options['cookie'])
            $this->client->enable_cookie($this->options['cookie']);

        $this->client->cache_hit=true;  // 避免首次访问发生delay

        $this->last_delay=time();
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
    function queue($type, $url, $ctx=[]){
        $task=$ctx;
        $task['TYPE']=$type;
        $task['URL']=$url;
        $task['DEPTH']=$depth=@$task['DEPTH'] ?: 0;

        if(!isset($this->pending[$depth])) $this->pending[$depth]=[];
        array_push($this->pending[$depth], $task);
    }

    function insert($context, $url){
        $context=is_array($context) ? $context : ['TYPE'=>$context, 'DEPTH'=>$this->pending ? min(array_keys($this->pending)) : 0];

        $this->queue($context, $url);
    }

    function traffic_control($delay=null){
        // 缓存命中将忽略流量控制
        if($this->client->cache_hit) return;

        $delay=$delay ?: $this->delay + $this->last_delay - time();
        if($delay>0){
            $this->log("Traffic Delay: %ds ", $delay);
            sleep($delay);
            $this->last_delay=time();
        }
    }

    function download_page($url){
        $retry=0;

        $html=$this->client->get($url);

        $this->traffic_control();
        $status=$this->client->status;
        while(preg_match('/^[45]\d\d/', $status) && $retry<$this->retry_download){
            $this->log("HTTP $status | Retry...");

            $this->client->destroy_cache($url);

            $retry++;
            $html=$this->client->get($url);
            $this->traffic_control(5);
        }

        // 保存临时文件，方便调试
        if($this->options['debug_file']) @file_put_contents($this->options['debug_file'], $html);

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
        call_user_func_array("\Clue\CLI::log", func_get_args());
    }

    function warning(){
        \Clue\CLI::ansi("yellow");
        fputs(STDERR, '[WARN] '.vsprintf(func_get_args()[0], array_slice(func_get_args(), 1))."\n");
        \Clue\CLI::ansi();
    }

    function error(){
        \Clue\CLI::ansi("yellow");
        fputs(STDERR, '[ERROR] '.vsprintf(func_get_args()[0], array_slice(func_get_args(), 1))."\n");
        \Clue\CLI::ansi();

        exit();
    }

    // 检查地址是否合规，并且遵守robots.txt
    function validate_url($url){
        if(strlen(trim($url))==0) return false;

        $info=parse_url($url);
        if(!in_array(@$info['scheme'], array('http', 'https'))) return false;

        // TODO: 检测robots.txt

        return true;
    }

    function submit($type, $data){
        $handler="process_".$type;
        if(method_exists($this, $handler)){
            return $this->$handler($data);
        }
        else{
            $this->log("Submit [$type]:\n%s\n", print_r($data, true));
        }
    }

    function crawl(){
        while(!empty($this->pending)){
            $depth=max(array_keys($this->pending));
            $t=array_shift($this->pending[$depth]);
            if(empty($this->pending[$depth])) unset($this->pending[$depth]);

            if(!$this->validate_url($t['URL'])){
                $this->warning("Invalid url: %s", $t['URL']);
                continue;
            }

            // 检查是否访问过
            $hash=isset($t['ID']) ? $t['TYPE'].'/'.$t['ID'] : $t['URL'];
            if(in_array($hash, $this->visited)) continue;

            $this->log("[%s] %s ", $t['TYPE'], $t['URL']);
            $content=$this->download_page($t['URL']);
            call_user_func(["\Clue\CLI", $this->client->status==200 ? 'success' : 'warning'], $this->client->status."\n");

            if($this->client->status==200){
                $this->fire_event('item_crawled', $t);
            }
            else{
                $this->fire_event('item_failed', $t);
            }

            $action="crawl_".$t['TYPE'];

            $data=$this->$action($t['URL'], $content, $t);
            if($data!==false){
                // 返回false表示失败，允许重新访问
                $this->visited[]=$hash;
            }

            if(is_array($data)){
                if(!isset($data['url'])) $data['url']=$t['URL'];
                $this->submit($t['TYPE'], $data);
            }
        }
    }

    // Util functions
    /////////////////////////////////////////////

    /**
     * 剔除前后端的多余文字
     */
    function trim_text($text, $str=null){
        $lines=explode("\n", $text);
        $lines=array_map('trim', $lines);
        $text=implode("\n", $lines);
        $text=preg_replace('/\n{2,}/', "\n\n", $text);

        if(is_string($str)) $str=[$str];
        if($str) foreach($str as $pattern){
            $text=preg_replace('/^\s*'.$pattern.'\s*/i', '', $text);
            $text=preg_replace('/\s*'.$pattern.'\s*$/i', '', $text);
        }

        return trim($text);
    }

}
