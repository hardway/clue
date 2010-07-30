<?php  
    require_once 'clue/core.php';
    
    function cfg($name){
        static $cfg=null;
        if($cfg==null)
            $cfg=new Clue_Config(dirname(__FILE__).DIRECTORY_SEPARATOR."config.php");
            
        return $cfg->get($name);
    }
    
    function show_help(){
        $baseURL=cfg('base_url');
        $currentDir=dirname(__FILE__).DIRECTORY_SEPARATOR;
        echo <<<END
Usage:
    -l List all frameworks and their benchmark scripts
    -b Run specific bench, sould be regexp
    -f Specify which framework to run bench
    -h Help
    
Prerequsits:
    Setup your webserver, map url access between
        {$baseURL}
    and
        {$currentDir}
        
Example Apache Config:
    Alias /benchmark/ "{$currentDir}"
    <Directory "{$currentDir}">
        Options FollowSymLinks +ExecCGI
        AllowOverride All
        Allow from all
    </Directory>

END;
    }
    
    function benchmark($url){
        exec(cfg('ab_tool')." $url 2>&1", $output);
        foreach($output as $line){
            if(preg_match('/^Complete requests[^0-9]*(\d+)$/i', $line, $match)){
                return intval($match[1]);
            }
        }
        return 0;
    }
    
    function scan_frameworks($dir){
        $fws=array();
        
        foreach(scandir($dir) as $n){
            if($n=='.' || $n=='..') continue;
            $n=$dir.DIRECTORY_SEPARATOR.$n;
            if(is_dir($n)) $fws[]=$n;
        }
        return $fws;
    }
    
    function benchmark_framework($dir, $listOnly=false){
        global $opt;
        
        $framework=basename($dir);
        echo "[$framework]\n";
        
        foreach(scandir($dir) as $n){
            if($n=='.' || $n=='..') continue;
            if(preg_match('/\.bench\.(php|htm|html)$/i', $n)){
                if(isset($opt['b']) && !preg_match("/{$opt['b']}/i", $n)) continue;
                $url=cfg('base_url')."$framework/$n";
                
                if($listOnly){
                    echo "\t$url\n";
                }
                else{
                    echo "Benchmarking $url ";
                    $r=benchmark($url);
                    echo "\t$r requests.\n";
                }
            }
        }
        echo "\n";
    }
    
    if(!isset($argv)) die("This is a CLI program.");
    
    $opt=getopt("f:b:hl");
    if(isset($opt['h'])) exit(show_help());
    
    foreach(scan_frameworks(dirname(__FILE__)) as $fw){
        if(isset($opt['f']) && $opt['f']!=basename($fw)) continue;
        
        benchmark_framework($fw, isset($opt['l']));            
    }
    //benchmark();
?>
