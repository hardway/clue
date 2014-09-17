<?php
namespace Clue;

class Guard{
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

	public $display_level;	# Display in webpage
	public $stop_level;	# Stop program execution when overlimit

	public $log_level;		# Log it when overlimit
	public $email_level;	# Email it when overlimit

	public $log_file;

	private $errors=array();

	public function __construct($option=array()){
		$config=array(
			'log_level'=>"WARNING",
			'email_level'=>'ERROR',
			'display_level'=>'ERROR',
			'stop_level'=>0,	// Non Stoppping on error

			'mail_to'=>null,
			'mail_from'=>null,

			'log_file'=>APP_ROOT."/log/".date("Ymd").".log"
		);

		if(is_array($option)){
			$config=array_merge($config, $option);
		}

		$this->log_level=is_numeric($config['log_level']) ? $config['log_level'] : self::$ERROR_LEVEL[$config['log_level']];
		$this->email_level=is_numeric($config['email_level']) ? $config['email_level'] : self::$ERROR_LEVEL[$config['email_level']];
		$this->display_level=is_numeric($config['display_level']) ? $config['display_level'] : self::$ERROR_LEVEL[$config['display_level']];
		$this->stop_level=is_numeric($config['stop_level']) ? $config['stop_level'] : self::$ERROR_LEVEL[$config['stop_level']];

		$this->mail_to=$config['mail_to'];
		$this->mail_from=$config['mail_from'];

		$this->log_file=$config['log_file'];

		$error_threshold=max($this->log_level, $this->email_level, $this->display_level, $this->stop_level);
		$error_reporting=0;
		foreach(self::$PHP_ERROR_MAP as $lvl=>$_){
			if($lvl <= $error_threshold) $error_reporting=$error_reporting | $lvl;
		}
		error_reporting($error_reporting);

        set_exception_handler(array($this, "on_exception"));
        set_error_handler(array($this, "on_error"));
        register_shutdown_function(array($this, "summarize"));
	}

	public function summarize(){
		$fatal_error=error_get_last();
		if(is_array($fatal_error)){
			$this->errors[]=array(
				'level'=>self::$ERROR_LEVEL[self::$PHP_ERROR_MAP[$fatal_error['type']]],
				'type'=>self::$PHP_ERROR_MAP[$fatal_error['type']],
				'message'=>$fatal_error['message'],
				'trace'=>array(array('file'=>$fatal_error['file'], 'line'=>$fatal_error['line'])),
			);
		}

		$to_log=array();
		$to_display=array();
		$to_email=array();

		foreach($this->errors as $err){
			if($err['level'] <= $this->display_level){
				$to_display[]=$this->error_html($err);
				$show_environment_html=true;
			}

			if($err['level'] <= $this->log_level){
				$to_log[]=$this->error_text($err);
				$show_environment_text=true;
			}

			if($err['level'] <= $this->email_level){
				$to_email[]=$this->error_text($err);
				$show_environment_text=true;
			}
		}

		// 包含全部Session/Get/Post/Cookie信息
		if($show_environment_html || $show_environment_text){
			$env=$this->filter_context_vars($GLOBALS);
			if($show_environment_text){
				$env_text="Environment: \n"."-------------\n".print_r($env, true);
			}
			if($show_environment_html){
				$env_html ="<div style='padding:1em; background:#666; color:#FFF; cursor:pointer;' onclick='clue_guard_toggle(\"clue-guard-ul-env\");'>Environment</div>";
				$env_html.="<ul id='clue-guard-ul-env' style='background:#FFF; border:1px solid #666; margin:0; padding:1em; display:none;'>";
				$env_html.=$this->var_to_html($env);
				$env_html.="</ul>";
			}
		}

		if(count($to_display)>0){
			echo "
				<script>
					function clue_guard_toggle(id){
						var el = document.getElementById(id);
						el.style.display = el.style.display === 'none' ? '' : 'none';
					}
				</script>
			";
			echo "<div style='font:1em monospace;'>".implode("", $to_display).$env_html."</div>";
		}

		if(!empty($this->log_file) && count($to_log)>0){
			$f=fopen($this->log_file, "a");
			if($f){
				$r=fwrite($f, implode("\n\n", $to_log)."\n$env_text");
				fclose($f);
			}
		}
		if(!empty($this->mail_to) && count($to_email)>0){
			global $app;
			$m=new Mail;
			$m->send(
				count($to_email)." error occured recently.",
				"<pre>".implode("\n\n", $to_email)."\n$env_text"."</pre>",
				$this->mail_to, $this->mail_from
			);
		}

		$this->errors=array();
	}

	function log($message, $level='INFO'){
		$trace=debug_backtrace();
		if(isset($trace[0])){
			$trace=$trace[0]['file'].":".$trace[0]['line'];
		}

		if(!empty($this->log_file) && $f=fopen($this->log_file, 'a')){
			$message=is_string($message) ? $message : print_r($message, true);
			fwrite($f, sprintf("%s\t%s\t%s\n\t\t%s\n", date("Y-m-d H:i:s.u"), $level, $trace, $message));
			fclose($f);
		}
	}

	function debug($message){
		echo "<div style='padding: 5px; border-bottom: 1px solid #CCC; position: relative'>";
		$trace=debug_backtrace();
		if(isset($trace[0])){
			echo "<div style='color: #999; position: absolute; right: 5px; top: 3px;'>".$trace[0]['file'].":".$trace[0]['line']."</div>";
		}
		echo "<div>";

		echo $message;

		$args=func_get_args();
		for($i=1; $i<count($args); $i++){
			echo $this->var_to_html($args[$i]);
		}
		echo "</div>";
		echo "<div class='clear'></div></div>";
	}

	function indent($text){
		return implode("\n", array_map(function($line){ return "    ".$line; }, explode("\n", $text)));
	}

	# With folding javascript
	function var_to_html($var, $ttl=4){
		if($ttl==0) return "...";

		$text="";

		if($var instanceof Closure) $var="Closure Function";
		elseif(is_object($var)) $var=(array)$var;

		if(is_array($var)){
			foreach($var as $k=>$v){
				$text.="$k = ";
				$text.="<ul>".$this->var_to_html($v, $ttl-1)."</ul>";
			}
		}
		else{
			$text.="<li style='list-style: none;'>".gettype($var);
			if(is_string($var))
				$text.="(".strlen($var)."): <span style='color: #911;'>\"".$var."\"</span></li>";
			else if(is_numeric($var)){
				$text.=": <span style='color: #191; font-weight: bold;'>".$var."</span></li>";
			}
			else{
				$text.=": $var</li>";
			}
		}

		return $text;
	}

	function error_text($err){
		$text=array();
		$text[]=$err['type']. ": ". $err['message'];
		$text[]="";

		$text[]="Backtracing (last call first):";
		$text[]="------------------------------";
		if(is_array($err['trace'])) foreach($err['trace'] as $t){
			$text[]="{$t['class']}{$t['type']}{$t['function']}()" . ((isset($t['file']) || isset($t['line'])) ? "\t({$t['file']}:{$t['line']})":"");

			if(is_array($t['args'])) foreach($t['args'] as $idx=>$a){
				$text[]=$this->indent(($idx+1).": ".print_r($a, true));
			}
		}
		$text[]="";

		return implode("\r\n", $text);
	}

	function error_html($err){
		if(isset($err['trace'][0]['file']))
			$error_position=$err['trace'][0]['file'].':'.$err['trace'][0]['line'];
		else
			$error_position=$err['trace'][0]['class'].$err['trace'][0]['type'].$err['trace'][0]['function'].'('.')';

		$uid="clue-guard-err-".uniqid();
		$html="
			<div style='padding:1em; background:#911; color:#fff; border-bottom:1px solid #CCC; cursor:pointer;' onclick='clue_guard_toggle(\"$uid\");'>
				<div style='float:right;'>$error_position</div>
				<strong>{$err['type']}</strong>: {$err['message']}
			</div>
		";

		$html.="<ul id='$uid' style='background:#EEE; margin:0; padding:1em; display:none;'><a name='$uid'></a>";
		if(is_array($err['trace'])) foreach($err['trace'] as $t){
			$uid="clue-guard-arg-".uniqid();
			$html.="<li style='list-style:none; cursor:pointer;' onclick='clue_guard_toggle(\"$uid\")'>";

			if(isset($t['file']) || isset($t['line'])) $html.="<strong>{$t['file']}:{$t['line']}</strong> &gt;&gt; ";
			$html.="{$t['class']}{$t['type']}{$t['function']}";
			if(is_array($t['args'])) $html.="(".count($t['args'])." args)";

			if(is_array($t['args'])){
				$html.="<table id='$uid' border='0' cellspacing='0' cellpadding='3' style='display:none; margin-left:5em; border-left:1px dashed #CCC;'>";
				foreach($t['args'] as $idx=>$a){
					$html.="<tr><td valign='top'><b>".($idx+1)."</b></td><td valign='top'>".$this->var_to_html($a)."</td></tr>";
				}
				$html.="</table>";
			}
			$html.="</li>";
		}
		$html.="</ul>";

		return $html;
	}

	function filter_context_vars($vars){
		# Filter Context
		$context=array();
		if(!empty($vars['_GET'])) $context['_GET']=$vars['_GET'];
		if(!empty($vars['_POST'])) $context['_POST']=$vars['_POST'];
		if(!empty($vars['_COOKIE'])) $context['_COOKIE']=$vars['_COOKIE'];
		if(!empty($vars['_SERVER'])) $context['_SERVER']=$vars['_SERVER'];

		return $context;
	}

	function on_exception($e){
		$trace=$e->getTrace();
		array_unshift($trace, ["file"=>$e->getFile(), 'line'=>$e->getLine()]);
		return $this->on_error(E_ERROR, "Exception: ".$e->getMessage(), $e->getFile(), $e->getLine(), $GLOBALS, $trace);
	}

	function on_error($errno, $errstr, $errfile=null, $errline=null, array $errcontext=null, array $errtrace=array()){
		// if error has been supressed with an @
	    if (error_reporting() == 0) return;

		$errlevel=self::$ERROR_LEVEL[self::$PHP_ERROR_MAP[$errno]];

		// Skip minus errors
		if($errlevel > max($this->display_level, $this->stop_level, $this->log_level, $this->email_level)) return;

		if(empty($errtrace)) $errtrace=debug_backtrace();
		# Unset $errcontext for this function ($GLOBALS is too big to display)

		$errtrace=array_values(array_filter($errtrace, function($t){
			return !(!isset($t['file']) && isset($t['class']) && $t['class']==__CLASS__ && in_array($t['function'], ['on_error', 'on_exception']));
		}));

		# Filter Context
		$context=array();
		if(!empty($errcontext['_GET'])) $context['_GET']=$errcontext['_GET'];
		if(!empty($errcontext['_POST'])) $context['_POST']=$errcontext['_POST'];
		if(!empty($errcontext['_COOKIE'])) $context['_COOKIE']=$errcontext['_COOKIE'];
		if(!empty($errcontext['_SERVER'])) $context['_SERVER']=$errcontext['_SERVER'];

		$this->errors[]=array(
			'level'=>$errlevel,
			'type'=>self::$PHP_ERROR_MAP[$errno],
			'message'=>$errstr,
			'trace'=>$errtrace,
			'context'=>$context
		);

		if($errlevel <= $this->stop_level) exit("ERROR STOP");

		return true;
	}
}
