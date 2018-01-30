<?php
namespace Clue;

class Guard{
    use Traits\Events;

	# Converts php error level to Guard log level
	static $PHP_ERROR_MAP=array(
	    E_NOTICE=>"NOTICE",				# 8
	    E_USER_NOTICE=>'NOTICE',		# 1024
	    E_STRICT=>'NOTICE',				# 2048
	    E_DEPRECATED=>'NOTICE',			# 8192
	    E_USER_DEPRECATED=>'NOTICE',	# 16384

	    E_WARNING=>'WARNING',			# 2
	    E_CORE_WARNING=>'WARNING',		# 32
	    E_COMPILE_WARNING=>'WARNING',	# 128
	    E_USER_WARNING=>'WARNING',		# 512

	    E_ERROR=>'ERROR',				# 1
	    E_CORE_ERROR=>'ERROR',			# 16
	    E_PARSE=>'ERROR',				# 4
	    E_COMPILE_ERROR=>'ERROR',		# 64
	    E_USER_ERROR=>'ERROR',			# 256
	    E_RECOVERABLE_ERROR=>'ERROR'	# 4096
	);

	static $ERROR_LEVEL=array(
		"ERROR"=>1,
		'WARNING'=>2,
		"NOTICE"=>3,
		"INFO"=>4,
		"DEBUG"=>5
	);

	protected $errors=array();
	protected $channels=[];
    public $summarized=false;

	public function __construct(array $option=array()){
		$default_config=array(
			'log_level'=>"WARNING",		# File Log threshold
			'email_level'=>'ERROR',		# Email log threshold
			'display_level'=>'ERROR',
			'stop_level'=>0,			// 发生错误后立刻停止


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

		$this->syslog=new \Clue\Logger\Syslog();

		$this->channels=[];
		$this->channels['display']=[
			'logger'=>new \Clue\Logger\HTML,
			'environment'=>true,
			'level'=>$this->log_level($config['display_level']),
			'errors'=>[],
		];

		if($config['mail_to']){
			$mail_from=$config['mail_from'] ?: null;
			$mailer=new \Clue\Mail\Sender($config['mail_host'], $config['mail_port'], $config['mail_username'], $config['mail_password'], $mail_from);

			$this->channels['email']=[
				'logger'=>new \Clue\Logger\Email($config['mail_to'], $mailer),
				'format'=>'text',
				'environment'=>true,
				'level'=>$this->log_level($config['email_level']),
				'errors'=>[],
			];
		}

		if($config['log_file']){
			if($config['log_file']=='syslog'){
				$this->channels['file']=[
					'logger'=>$this->syslog,
					'format'=>'text',
					'environment'=>true,
					'level'=>$this->log_level($config['log_level']),
					'errors'=>[],
				];
			}
			else{
				$this->channels['file']=[
					'logger'=>new \Clue\Logger\File($config['log_file']),
					'format'=>'text',
					'environment'=>true,
					'level'=>$this->log_level($config['log_level']),
					'errors'=>[],
				];
			}
		}

		$this->stop_level=$this->log_level($config['stop_level']);

		// 只需要error report能够监控到的错误级别
		$this->error_threshold=max(array_map(function($c){return $c['level'];}, $this->channels)+[$this->stop_level]);

		$error_reporting=0;
		foreach(self::$PHP_ERROR_MAP as $lvl=>$err){
			if(self::$ERROR_LEVEL[$err] <= $this->error_threshold) $error_reporting=$error_reporting | $lvl;
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
			$level=self::$ERROR_LEVEL[self::$PHP_ERROR_MAP[$fatal_error['type']]];
			if($level<=$this->error_threshold){
				$this->errors[]=array(
					'level'=>$level,
					'type'=>self::$PHP_ERROR_MAP[$fatal_error['type']],
					'message'=>$fatal_error['message'],
					'backtrace'=>array(array('file'=>$fatal_error['file'], 'line'=>$fatal_error['line'])),
				);
			}
		}

        if(!$this->summarized){
    		$context=[
    			'_SERVER'=>$_SERVER,
    			'_GET'=>@$_GET,
    			'_POST'=>@$_POST,
    			'_COOKIE'=>@$_COOKIE,
    			'_FILES'=>@$_FILES,
    			'_SESSION'=>@$_SESSION,
    		];
    		$context=$this->filter_context($context);

            // 按照各个channel的threshold进行分拣和输出
            foreach($this->channels as $type=>$channel){
                $errors=array_filter($this->errors, function($err) use($channel){
                    return $err['level'] <= $channel['level'];
                });

                if(empty($errors)) continue;

        		$resource=@$context['_SERVER']['REQUEST_URI'] ?: $context['_SERVER']['SCRIPT_FILENAME'];

    			$output=[
    				'message'=>count($errors)." error occured recently at \"$resource\"",
    				'timestamp'=>date("Y-m-d H:i:s"),
                    'first_error'=>$errors[0]['type'].' '.$errors[0]['message'],
                    'first_trace'=>$errors[0]['backtrace'][0]['file'].':'.$errors[0]['backtrace'][0]['line']
    			];

    			if(isset($channel['format'])){
    				$diagnose=[];

    				foreach($errors as $err){
    					// TODO: format_error()

    					$text=[];
    					$text[]=$err['type']. ": ". $err['message'];
    					$text[]="";
    					$text[]="Backtracing:";
    					$text[]=$channel['logger']->format_backtrace($err['backtrace']);
    					$text[]="";
    					$diagnose[]=implode("\n", $text);
    				}

    				$output['diagnose']=implode("\n", $diagnose);
    			}
    			else{
    				$output['diagnose']=$errors;
    			}

    			$output['context']=$context;

    			$channel['logger']->write($output);
    		}
        }

		// 清理
		$this->errors=[];
	}

	function log(){
		// TODO
	}

	/**
	 * 直接在HTML输出中显示内容
	 */
	function debug($message){
		$trace=debug_backtrace();
		$t=$trace[0];
		$location=isset($t['file']) ? $t['file'].":".$t['line'] : $item['t']['class'].$item['t']['type'].$item['t']['function'].'('.')';

		$logger=$this->channels['display']['logger'];

		$item=['type'=>"DEBUG", 'message'=>$logger->format_var($message), 'location'=>$location];
		$logger->write_log($item);

		return;
	}

	function trace($message){
		$item=['type'=>"DEBUG", 'message'=>$message, 'backtrace'=>debug_backtrace()];
		$this->channels['display']['logger']->write_log($item);
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

	private function log_level($level){
		return is_numeric($level) ? $level : self::$ERROR_LEVEL[strtoupper($level)];
	}

	private function indent($text){
		return implode("\n", array_map(function($line){ return "    ".$line; }, explode("\n", $text)));
	}

	// 包含全部Session/Get/Post/Cookie信息
	private function filter_context($context){
		if(empty($context['_GET'])) unset($context['_GET']);
		if(empty($context['_POST'])) unset($context['_POST']);
		if(empty($context['_COOKIE'])) unset($context['_COOKIE']);
		if(empty($context['_SERVER'])) unset($context['_SERVER']);
		if(empty($context['_FILES'])) unset($context['_FILES']);
		if(empty($context['_SESSION'])) unset($context['_SESSION']);
		unset($context['GLOBALS']);

		return $context;
	}

	static function var_to_text($var, $ttl=4){
		if($ttl==0) return "...";

		$text="";

		if($var instanceof Closure) $var="Closure Function";

		if(is_array($var) || is_object($var)){
			$width=max(array_map('strlen', array_keys($var)));

			$text="";
			foreach($var as $k=>$v){
				$text.=sprintf("%-{$width}s = ",$k);
				$text.=self::var_to_text($v, $ttl-1);
				$text.="\n";
			}
			$text=$text ? "\n".self::indent(trim($text, "\n"))."\n" : " ";
			$text=(is_array($var)?"[":get_class($var).": {")."$text".(is_array($var)?"]":"}")."\n";
		}
		else{
			$text.=gettype($var);
			if(is_string($var))
				$text.="(".strlen($var)."): \"".$var."\"";
			elseif(is_bool($var)){
				$text.=": ".($var ? "true" : "false");
			}
			else{
				$text.=": $var";
			}
		}

		return $text;
	}

	/**
	 * Exception作为E_ERROR级别的错误
	 */
	function handle_exception($e){
		$trace=$e->getTrace();
		array_unshift($trace, ["file"=>$e->getFile(), 'line'=>$e->getLine()]);
		return $this->handle_error(E_ERROR, "Exception: ".$e->getMessage(), $e->getFile(), $e->getLine(), $GLOBALS, $trace);
	}

	function handle_error($errno, $errstr, $errfile=null, $errline=null, array $errcontext=null, array $errtrace=array()){
		// if error has been supressed with an @
	    if ((error_reporting() & $errno) == 0) return;

		$errlevel=self::$ERROR_LEVEL[self::$PHP_ERROR_MAP[$errno]];

		if(empty($errtrace)) $errtrace=debug_backtrace();
		# Unset $errcontext for this function ($GLOBALS is too big to display)

		$errtrace=array_values(array_filter($errtrace, function($t){
			return !(!isset($t['file']) && isset($t['class']) && $t['class']==__CLASS__ && in_array($t['function'], ['handle_error', 'handle_exception']));
		}));

		$this->errors[]=array(
			'level'=>$errlevel,
			'type'=>self::$PHP_ERROR_MAP[$errno],
			'message'=>$errstr,
			'backtrace'=>$errtrace,
			'context'=>$this->filter_context($errcontext)
		);

        $this->fire_event('error', ['code'=>$errno, 'message'=>$errstr]);

        if($errlevel <= $this->stop_level){
            // E-STOP时不显示function参数
            exit(sprintf("EMERGENCY STOP: %s %s\n%s\n", self::$PHP_ERROR_MAP[$errno], $errstr, $this->syslog->format_backtrace($errtrace)));
        }

		return true;
	}
}
