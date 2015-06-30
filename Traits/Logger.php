<?php
namespace Clue\Traits;

trait Logger{
    static protected $log_file=null;

    static function enable_log($log_file=null){
        self::$log_file=$log_file ?: 'syslog';

        if($log_file){
            // 确保文件夹已经存在
            $dir=dirname($log_file);
            if(!is_dir($dir)){
                mkdir($dir, 0775, true);
            }
            if(!is_dir($dir)){
                error_log("Log folder doesn't exist, and can't be created either.");
            }
        }
    }

    static function disable_log(){
        self::$log_file=null;
    }

    static function log($message, $options=[]){
        if(self::$log_file===null) return;
        $type=self::$log_file=='syslog' ? 0 : 3;

        // 复杂对象转换为string
        if(is_array($message) || is_object($message)){
            $message=var_export($message, true);
        }

        // 附加Timestamp
        $timestamp=microtime(true);
        $timestamp=sprintf("%s.%03d", date("Y-m-d H:i:s", $timestamp), 1000*($timestamp - floor($timestamp)));
        $message=str_replace('{TIMESTAMP}', $timestamp, $message);

        // 附加log来源
        $caller=self::_get_backtrace($options['backtrace']);
        $message.=$caller ? sprintf(" (%s)", $caller) : "";

        if($type==3) $message.="\n";

        error_log($message, $type, self::$log_file);
    }

    /**
     * @param $level 如果是full则返回全部trace
     */
    static function _get_backtrace($level=1){
        $trace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        $trace=$level=='full' ? array_slice($trace, 1) : array_slice($trace, $level, 1);
        return implode("\n\t", array_map(function($t){
            return @$t['file'].':'.@$t['line'];
        }, $trace));
    }
}
?>
