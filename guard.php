<?php
namespace Clue;

@define('CLUE_GUARD_MAX_PARAM', 1024);

use \Clue\Logger;
use \Clue\Logger\SyslogHandler;
use \Clue\Logger\EmailHandler;
use \Clue\Logger\WebHandler;

class Guard{
    use Traits\Events;

    protected $errors=array();
    protected $channels=[];
    public $summarized=false;

    public function __construct(array $option=array()){
        $default_config=array(
            'log_level'=>"WARNING",     # File Log threshold
            'email_level'=>'ERROR',     # Email log threshold
            'display_level'=>'ERROR',
            'stop_level'=>0,            // 发生错误后立刻停止

            'mail_to'=>null,
            'mail_from'=>null,
            'mail_host'=>'127.0.0.1',
            'mail_port'=>25,
            'mail_username'=>null,
            'mail_password'=>null,

            'log_file'=>'syslog',
            // 可以是文件地址
            // 'log_file'=>APP_ROOT."/log/".date("Ymd").".log"
        );

        // TODO: config兼容性转换
        $config=array_merge($default_config, $option);

        // 输出方向
        $this->channels=[];
        $this->channels['display']=[
            'logger'=>new Logger(null, php_sapi_name()=='cli'
                ? new SyslogHandler()  // TODO: 支持ConsoleHandler,输出banner
                : new WebHandler()
            ),
            'level'=>$this->log_level($config['display_level']),
            'errors'=>[],
        ];

        if($config['mail_to']){
            $mailer=new \Clue\Mail\Sender(
                $config['mail_host'],
                $config['mail_port'],
                $config['mail_username'],
                $config['mail_password'],
                $config['mail_from'] ?: null
            );

            $this->mail_logger=new Logger(null, new EmailHandler($config['mail_to'], $mailer));

            $this->channels['email']=[
                'logger'=>$this->mail_logger,
                'level'=>$this->log_level($config['email_level']),
                'errors'=>[],
            ];
        }

        if($config['log_file']){
            $path=$config['log_file'];
            if($path=='syslog') $path=null;
            $this->channels['file']=[
                'logger'=>new Logger(null, $path),
                'level'=>$this->log_level($config['log_level']),
                'errors'=>[],
            ];
        }

        $this->stop_level=$this->log_level($config['stop_level']);

        // 只需要error report能够监控到的错误级别
        $this->error_threshold=max(array_map(function($c){return $c['level'];}, $this->channels)+[$this->stop_level]);

        $error_reporting=0;
        // 根据设定的LogLevel设定Error Reporting，避免不必要的错误捕获
        foreach(self::$PHP_ERROR_MAP as $lvl=>$err){
            if($this->log_level($err) <= $this->error_threshold){
                $error_reporting=$error_reporting | $lvl;
            }
        }
        error_reporting($error_reporting);


        // 开始监控
        set_exception_handler(array($this, "handle_exception"));
        set_error_handler(array($this, "handle_error"));


        // 统一输出
        register_shutdown_function(array($this, "summarize"));
        $this->summarized=false;
    }

    public function summarize(){
        // 最后的机会捕捉到fatal error
        $fatal_error=error_get_last();
        if(is_array($fatal_error)){
            $type=$this->php_level($fatal_error['type']);
            $level=$this->log_level($type);

            if($level<=$this->error_threshold){
                $this->errors[]=array(
                    'level'=>$level,
                    'type'=>$type,
                    'message'=>$fatal_error['message'],
                    'backtrace'=>array(array('file'=>$fatal_error['file'], 'line'=>$fatal_error['line'])),
                );
            }
        }

        if(!$this->summarized){
            // 全局上下文
            $context=$this->_global_context();

            // 遍历所有日志输出
            foreach($this->channels as $type=>$channel){
                // 按照各个channel的threshold进行分拣和输出
                $errors=array_filter($this->errors, function($err) use($channel){
                    return $err['level'] <= $channel['level'];
                });

                if(empty($errors)) continue;

                // 记录Summary
                $level=$errors[0]['type'];
                $resource=@$context['_SERVER']['REQUEST_URI'] ?: $context['_SERVER']['SCRIPT_FILENAME'];
                $message=count($errors)." error occured recently at \"$resource\"";

                $context['first_error']=$errors[0]['type'].' '.$errors[0]['message'];
                $context['first_trace']=$errors[0]['backtrace'][0]['file'].':'.$errors[0]['backtrace'][0]['line'];
                $context['errors']=$errors;

                $channel['logger']->log($level, $message, $context);
            }
        }

        // 清理
        $this->errors=[];
    }

    function log(){
        // TODO
    }

    /**
     * 直接在HTML输出中显示内容，类似xdebug的var_dump
     */
    function debug($var){
        $trace=debug_backtrace();
        $t=$trace[0];
        $location=isset($t['file']) ? $t['file'].":".$t['line'] : $item['t']['class'].$item['t']['type'].$item['t']['function'].'('.')';

        $this->channels['display']['logger']->debug($var, ['location'=>$location]);
        return;
    }

    /**
     * 直接向开发人员发送邮件报告
     */
    function developer($subject, $body){
        // TODO
    }

    /**
     * 记录统计数据
     */
    function metric($message, $data){
        // TODO
    }

    /**
     * 字符型的 error type 转换为数值型的 log level
     */
    private function log_level($level){
        return \Clue\Logger::log_level($level);
    }

    static $PHP_ERROR_MAP=array(
        E_NOTICE=>"NOTICE",             # 8
        E_USER_NOTICE=>'NOTICE',        # 1024
        E_STRICT=>'NOTICE',             # 2048
        E_DEPRECATED=>'NOTICE',         # 8192
        E_USER_DEPRECATED=>'NOTICE',    # 16384

        E_WARNING=>'WARNING',           # 2
        E_CORE_WARNING=>'WARNING',      # 32
        E_COMPILE_WARNING=>'WARNING',   # 128
        E_USER_WARNING=>'WARNING',      # 512

        E_ERROR=>'ERROR',               # 1
        E_CORE_ERROR=>'ERROR',          # 16
        E_PARSE=>'ERROR',               # 4
        E_COMPILE_ERROR=>'ERROR',       # 64
        E_USER_ERROR=>'ERROR',          # 256
        E_RECOVERABLE_ERROR=>'ERROR'    # 4096
    );

    /**
     * 将PHP错误归一化为5日志级别
     */
    private function php_level($error){
        return @self::$PHP_ERROR_MAP[$error] ?: $error;
    }

    // 包含全部Session/Get/Post/Cookie信息
    private function _global_context(){
        $context=[];

        if($_GET) $context['_GET']=$_GET;
        if($_POST) $context['_POST']=$_POST;
        if($_COOKIE) $context['_COOKIE']=$_COOKIE;
        if($_SERVER) $context['_SERVER']=$_SERVER;
        if($_FILES) $context['_FILES']=$_FILES;
        if(isset($_SESSION)) $context['_SESSION']=$_SESSION;
        // unset($context['GLOBALS']);

        return $context;
    }

    /**
     * Exception作为E_ERROR级别的错误
     */
    function handle_exception($e){
        $trace=$e->getTrace();
        array_unshift($trace, ["file"=>$e->getFile(), 'line'=>$e->getLine()]);
        return $this->handle_error(E_ERROR, "Exception: ".$e->getMessage(), $e->getFile(), $e->getLine(), $GLOBALS, $trace);
    }

    /**
     * 记录发生的Error
     */
    function handle_error($errno, $errstr, $errfile=null, $errline=null, array $errcontext=null, array $errtrace=array()){
        // if error has been supressed with an @
        if (!(error_reporting() & $errno)) return false;

        $type=$this->php_level($errno);
        $level=$this->log_level($type);
        if($level > $this->error_threshold) return true;

        if(empty($errtrace)) $errtrace=debug_backtrace();
        # Unset $errcontext for this function ($GLOBALS is too big to display)

        $errtrace=array_values(array_filter($errtrace, function($t){
            return !(!isset($t['file']) && isset($t['class']) && $t['class']==__CLASS__ && in_array($t['function'], ['handle_error', 'handle_exception']));
        }));

        // 过滤大型参数
        foreach($errtrace as &$et){
            if(!is_array($et['args'])) continue;
            $et['args']=array_map(function($a){
                if(CLUE_GUARD_MAX_PARAM>0 && strlen(json_encode($a)) > CLUE_GUARD_MAX_PARAM){
                    return  "ARGUMENT_TOO_LARGE_TO_SHOW";
                }
                return $a;
            }, $et['args']);
        }; unset($et);

        $this->errors[]=array(
            'level'=>$level,
            'type'=>$type,
            'message'=>$errstr,
            'backtrace'=>$errtrace
        );

        $this->fire_event('error', ['code'=>$errno, 'message'=>$errstr]);

        if($level <= $this->stop_level){
            // E-STOP时不显示function参数
            $syslog=new Logger();
            $syslog->emergency(
                sprintf("EMERGENCY STOP: %s %s", $this->php_level($errno), $errstr),
                ['backtrace'=>$errtrace]
            );
            exit();
        }

        return true;
    }
}
