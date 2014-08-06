<?php
namespace Clue;
class Profiler{
    function __construct(){
    	$this->xhprof=extension_loaded("xhprof");

    	$this->start();
    }

    function __destruct(){

    }

    function start(){
    	$this->start_native();

	    if($this->xhprof) $this->start_xhprof();
    }

    function stop($source='unknown'){
        $this->stop_native();

    	if($this->xhprof) $this->stop_xhprof($source);

        return $this->summary();
    }

    function start_native(){
	    $this->start_time=microtime(true);
	    $this->start_memory=memory_get_usage();
    }

    function stop_native(){
    	$this->stop_time=microtime(true);
    	$this->stop_memory=memory_get_usage();
    }

    function start_xhprof(){
		xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY);
    }

    function stop_xhprof($source){
		$data = xhprof_disable();

		$this->xhprof_run=date("mdHis").uniqid();
        $this->xhprof_source=$source;

        $dir=ini_get("xhprof.output_dir");
        if(!is_dir($dir)) @mkdir($dir, 0775, true);

		$file=sprintf("%s/%s.%s.xhprof", $dir, $this->xhprof_run, $this->xhprof_source);
		file_put_contents($file, serialize($data));
    }

    function summary(){
    	return array(
    		'time'=>$this->stop_time - $this->start_time,
    		'memory'=>$this->stop_memory - $this->start_memory,
    		'xhprof'=>$this->xhprof ? [
                        'run'       => $this->xhprof_run,
                        'source'    => $this->xhprof_source,
                        'url'       => "http://localhost/xhprof/xhprof_html/index.php?run=$this->xhprof_run&source=".$this->xhprof_source
                    ]: null
    	);
    }
}
?>
