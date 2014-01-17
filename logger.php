<?php
namespace Clue;

trait Logger{
    static protected $log_file=null;

    static function enable_log($log_file='syslog'){
        self::$log_file=$log_file;
    }

    static function disable_log(){
        self::$log_file=null;
    }

    static function log($message){
        if(self::$log_file===null) return;
        $type=self::$log_file=='syslog' ? 0 : 3;

        // 复杂对象转换为string
        if(is_array($message) || is_object($message)){
            $message=var_export($message, true);
        }

        // 附加Timestamp
        $message=str_replace('{TIMESTAMP}', self::timestamp(), $message);

        // 附加log来源
        $caller=self::_get_caller();
        $message.=$caller ? sprintf(" (%s)", $caller) : "";

        if($type==3) $message.="\n";

        error_log($message, $type, self::$log_file);
    }

    static function timestamp(){
        $t=microtime(true);

        return sprintf("%s.%03d", date("Y-m-d H:i:s", $t), 1000*($t - floor($t)));
    }

    static function _get_caller($level=1){
        $trace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        if(count($trace)>$level){
            return sprintf("%s:%s", $trace[$level]['file'], $trace[$level]['line']);
        }
    }
}
?>
