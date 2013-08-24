<?php
namespace Clue;
trait Logger{
    protected $log_file=null;

    function enable_log($log_file='syslog'){
        $this->log_file=$log_file;
    }

    function disable_log(){
        $this->log_file=null;

    }

    function log($message){
        if($this->log_file===null) return;
        $type=file_exists($this->log_file) ? 3 : 0;
        error_log($message, $type, $this->log_file);
    }

    /*
    static function syslog($message){
        $bt=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS|!DEBUG_BACKTRACE_PROVIDE_OBJECT, 1);
        error_log(sprintf("%s\n [%s:%s]", $message, $bt[0]['file'], $bt[0]['line']));
    }

    protected $file;
    function __construct($option){
        $this->option=array_merge(array(
            'timestamp'=>true,
            'verbose'=>true,
            'backtrace'=>false
        ), $option);

        if(isset($this->option['file'])){
            if(!is_dir(dirname($this->option['file']))){
                mkdir(dirname($this->option['file']), 0775, true);
            }

            $this->file=$this->option['file'];
        }
    }

    function __destruct(){

    }

    function backtrace($toggle){
        $this->option['backtrace']=$toggle;
    }

    function log($message){
        if($this->option['timestamp']){
            $message=date("Y-m-d H:i:s")." ".$message;
        }

        $message.="\n";

        if($this->option['backtrace']){
            $trace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            while(count($trace)>0){
                $top=array_shift($trace);
                if(!preg_match('/Logger/', $top['class'])) break;

                $file=$top['file'];
                $line=$top['line'];
            }

            $message.=sprintf("%s BACKTRACE: %s:%d\n", str_repeat(' ', 19), $file, $line);
        }

        if($this->option['verbose']){
            echo $message;
        }

        if($this->file){
            error_log($message, 3, $this->file);
        }
    }
    */
}
?>
