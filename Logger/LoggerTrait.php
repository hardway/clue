<?php
namespace Clue\Logger;

trait LoggerTrait{
    protected $log_handler=null;
    protected $log_channel=null;

    // Logger Awareness
    function enable_log($handler=null, $level_limit=\Clue\Logger::ANY){
        if(empty($handler)){
            $handler=new \Clue\Logger\SyslogHandler;
        }
        // 字符串，代表使用文件
        elseif(is_string($handler)){
            // TODO: 支持协议字符串，比如: db://, mailto://, etc
            $handler=new \Clue\Logger\FileHandler($handler);
        }

        $this->log_handler=$handler;
        $this->log_handler->limit_log_level($level_limit);

        if(!$this->log_channel){
            // 默认使用类名作为Channel
            $this->log_channel=__CLASS__;
        }
    }

    function disable_log(){
        $this->log_handler=null;
    }

    function emergency($message, array $ctx=[]) {$this->log('emergency', $message, $ctx); }
    function critical($message, array $ctx=[])  {$this->log('critical', $message, $ctx); }
    function error($message, array $ctx=[])     {$this->log('error', $message, $ctx); }
    function alert($message, array $ctx=[])     {$this->log('alert', $message, $ctx); }
    function warning($message, array $ctx=[])   {$this->log('warning', $message, $ctx); }
    function notice($message, array $ctx=[])    {$this->log('notice', $message, $ctx); }
    function info($message, array $ctx=[])      {$this->log('info', $message, $ctx); }
    function debug($message, array $ctx=[])     {$this->log('debug', $message, $ctx); }

    function log($level, $message, $context=[]){
        if(empty($this->log_handler)) return;

        // 复杂对象转换为string
        if(is_array($message) || is_object($message)){
            $message=var_export($message, true);
        }

        $data=['channel'=>$this->log_channel, 'level'=>$level, 'message'=>$message];
        $data=array_merge($data, $context);

        // 如果Level不合适，直接跳过
        if($this->log_handler->filter_log($data)){
            return;
        }

        // 附加Timestamp
        $timestamp=microtime(true);
        $timestamp=sprintf("%s.%03d", date("Y-m-d H:i:s", $timestamp), 1000*($timestamp - floor($timestamp)));
        $data['timestamp']=$timestamp;

        // 附加Backtrace
        if(@$context['backtrace']!=false){
            $data['backtrace']=self::_get_backtrace(@$context['backtrace'] ?: 0);
        }

        // 附加内存统计
        if(@$context['memory']!=false){
            $data['memory']=[
                'usage'=>memory_get_usage(true),
                'peak'=>memory_get_peak_usage(true)
            ];
        }

        // 附加进程等信息
        if(@$context['process']==true){
            $data['process']=[
                'id'=>posix_getpid()
            ];
        }

        // 附加HTTP信息
        if(@$context['http']!=false && php_sapi_name()!='cli'){
            $data['http']=[
                'url'=>@$_SERVER['REQUEST_URI'],
                'method'=>@$_SERVER['REQUEST_METHOD'],
                'ip'=>@$_SERVER['REMOTE_ADDR'],
                'browser'=>@$_SERVER['HTTP_USER_AGENT'],
                'referrer'=>@$_SERVER['HTTP_REFERER'],
            ];
        }

        $this->log_handler->write($data);
    }

    /**
     * @param $level 如果是<=0则返回全部trace
     */
    static function _get_backtrace($depth=null){
        $trace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        $trace=$depth ? array_slice($trace, 2, $depth) : array_slice($trace, 2);
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
