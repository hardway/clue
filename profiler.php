<?php
/**
 * For save profile output before process interrupted, use:
 *
 * declare(ticks = 1); pcntl_signal(SIGINT, function(){exit;});
 *
 */
namespace Clue;

class Profiler{
    function __construct($source='unknown'){
    	$this->xhprof=extension_loaded("xhprof");

    	$this->start($source);

        register_shutdown_function(array($this, 'stop'));
    }

    function __destruct(){

    }

    function start($source='unknown'){
    	$this->start_native();

	    if($this->xhprof) $this->start_xhprof($source);
    }

    function stop(){
        $this->stop_native();

    	if($this->xhprof) $this->stop_xhprof();

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

    function start_xhprof($source='unknown'){
		\xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY);

        $this->xhprof_run=date("mdHis").uniqid();
        $this->xhprof_source=$source;
        $this->xhprof_dir=ini_get("xhprof.output_dir");
        $this->xhprof_file=sprintf("%s/%s.%s.xhprof", $this->xhprof_dir, $this->xhprof_run, $this->xhprof_source);

        $om=umask(0);
        if(!is_dir($this->xhprof_dir)) @mkdir($this->xhprof_dir, 0777, true);
        umask($om);
    }

    function stop_xhprof(){
        $data = \xhprof_disable();

		file_put_contents($this->xhprof_file, serialize($data));
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
