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
    public function emergency($message, array $context = array())
    {
        $this->log('emergency', $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     */
    public function critical($message, array $context = array())
    {
        $this->log('critical', $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     */
    public function error($message, array $context = array())
    {
        $this->log('error', $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     */
    public function alert($message, array $context = array())
    {
        $this->log('alert', $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     */
    public function warning($message, array $context = array())
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Normal but significant events.
     */
    public function notice($message, array $context = array())
    {
        $this->log('notice', $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     */
    public function info($message, array $context = array())
    {
        $this->log('info', $message, $context);
    }

    /**
     * Detailed debug information.
     */
    public function debug($message, array $context = array())
    {
        $this->log('debug', $message, $context);
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

        // 附加log来源
        if(isset($context['backtrace'])){
            $caller=self::_get_backtrace($context['backtrace']);
            $message.=$caller ? sprintf(" (%s)", $caller) : "";
            $data['caller']=$caller;
        }

        self::$log_handler->write($data);
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
