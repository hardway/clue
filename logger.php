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

        $caller=$this->_get_caller();
        $message.=$caller ? sprintf(" (%s)", $caller) : "";

        error_log($message, $type, $this->log_file);
    }

    function _get_caller($level=1){
        $trace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        if(count($trace)>$level){
            return sprintf("%s:%s", $trace[$level]['file'], $trace[$level]['line']);
        }
    }
}
?>
