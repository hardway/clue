<?php
namespace Clue\Traits;

trait Logger{
    static protected $log_handler=null;

    // Logger Awareness
    static function enable_log($logger=null){
        if(is_string($logger)){
            $logger=new \Clue\Logger\File($logger);
        }

        self::setLogger($logger);
    }

    static function disable_log(){
        self::setLogger(null);
    }

    static function setLogger($logger){
        self::$log_handler=$logger;
    }

    /**
     * System is unusable.
     */
    static function emergency($message, array $context = array()){
        self::log('emergency', $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     */
    static function critical($message, array $context = array()){
        self::log('critical', $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     */
    static function error($message, array $context = array()){
        self::log('error', $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     */
    static function alert($message, array $context = array()){
        self::log('alert', $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     */
    static function warning($message, array $context = array()){
        self::log('warning', $message, $context);
    }

    /**
     * Normal but significant events.
     */
    static function notice($message, array $context = array()){
        self::log('notice', $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     */
    static function info($message, array $context = array()){
        self::log('info', $message, $context);
    }

    /**
     * Detailed debug information.
     */
    static function debug($message, array $context = array()){
        self::log('debug', $message, $context);
    }

    static function log($level, $message, $context=[]){
        if(empty(self::$log_handler)) return;

        // 复杂对象转换为string
        if(is_array($message) || is_object($message)){
            $message=var_export($message, true);
        }

        $data=['level'=>$level, 'message'=>$message];

        // 附加Timestamp
        $timestamp=microtime(true);
        $timestamp=sprintf("%s.%03d", date("Y-m-d H:i:s", $timestamp), 1000*($timestamp - floor($timestamp)));
        $data['timestamp']=$timestamp;

        // 附加Backtrace
        $data['backtrace']=self::_get_backtrace(2);

        // 附加内存统计
        $data['memory']=[
            'usage'=>memory_get_usage(true),
            'peak'=>memory_get_peak_usage(true)
        ];

        // 附加HTTP信息
        $data['context']=[
            'url'=>@$_SERVER['REQUEST_URI'],
            'method'=>@$_SERVER['REQUEST_METHOD'],
            'ip'=>@$_SERVER['REMOTE_ADDR'],
            'browser'=>@$_SERVER['HTTP_USER_AGENT'],
            'referrer'=>@$_SERVER['HTTP_REFERER'],
        ];

        self::$log_handler->write($data);
    }

    /**
     * @param $level 如果是<=0则返回全部trace
     */
    static function _get_backtrace($depth=null){
        $trace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        $trace=is_numeric($depth) ? array_slice($trace, 2, $depth) : array_slice($trace, 2);
        return $trace;

        return implode("\n\t", array_map(function($t){
            $pos=$t['class'].$t['type'].$t['function']."()";
            if(isset($t['file'])){
                $pos=$t['file'].':'.$t['line'] .' '. $pos;
            }
            return $pos;
        }, $trace));
    }
}
?>
