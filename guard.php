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

	public function __construct($config=array()){
		$default=array(
			'log_level'=>"WARNING",
			'email_level'=>'ERROR',
			'display_level'=>'ERROR',
			'stop_level'=>0,	// Non Stoppping on error

			'mail_to'=>null,
			'mail_from'=>null,

			'log_file'=>"log/".date("Ymd").".log"
		);

		$config=array_merge($default, $config);

		$this->log_level=is_numeric($config['log_level']) ? $config['log_level'] : self::$ERROR_LEVEL[$config['log_level']];
		$this->email_level=is_numeric($config['email_level']) ? $config['email_level'] : self::$ERROR_LEVEL[$config['email_level']];
		$this->display_level=is_numeric($config['display_level']) ? $config['display_level'] : self::$ERROR_LEVEL[$config['display_level']];
		$this->stop_level=is_numeric($config['stop_level']) ? $config['stop_level'] : self::$ERROR_LEVEL[$config['stop_level']];

		$this->mail_to=$config['mail_to'];
		$this->mail_from=$config['mail_from'];

		$this->log_file=$config['log_file'];

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
				'context'=>$this->filter_context_vars($GLOBALS)
			);
		}

		$to_log=array();
		$to_display=array();
		$to_email=array();

		foreach($this->errors as $err){
			if($err['level'] <= $this->display_level){
				$to_display[]=$this->error_html($err);
			}

			if($err['level'] <= $this->log_level){
				$to_log[]=$this->error_text($err);
			}

			if($err['level'] <= $this->email_level){
				$to_email[]=$this->error_text($err);
			}
		}

		if(count($to_display)>0){
			echo "
				<script>
					function toggle(id){
						var el = document.getElementById(id);
						el.style.display = el.style.display === 'none' ? '' : 'none';
					}
				</script>
			";
			echo implode("", $to_display);
		}
		if(!empty($this->log_file) && count($to_log)>0){
			$f=fopen($this->log_file, "a");
			if($f){
				fwrite($f, implode("\n\n", $to_log));
				fclose($f);
			}
		}
		if(!empty($this->mail_to) && count($to_email)>0){
			Email::send_mail("Developer", $this->mail_to, "Error Report", $this->mail_from, 
				count($to_email)." error occured recently.", "<pre>".implode("\n\n", $to_email))."</pre>";
		}

		$this->errors=array();
	}

	function log($message, $level='INFO'){
		if(!empty($this->log_file) && $f=fopen($this->log_file, 'a')){
			$message=is_string($message) ? $message : print_r($message, true);
			fwrite($f, sprintf("%s\t%s\t%s\n", date("Y-m-d H:i:s.u"), $level, $message));
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
	function var_to_html($var){
		$text="";

		if($var instanceof Closure) $var="Closure Function";
		elseif(is_object($var)) $var=(array)$var;

		if(is_array($var)){
			foreach($var as $k=>$v){
				$text.="$k = ";
				$text.="<ul>".$this->var_to_html($v)."</ul>";
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

		$text[]="Environment: ";
		$text[]="-------------";
		$text[]=print_r($err['context'], true);

		return implode("\r\n", $text);
	}

	function error_html($err){
		$html="<div style='text-align: left; padding: 1em;'>";
		$html.="<h2 style='margin: 0;padding: 1em;font-size: 1em;font-weight: normal;background: #911;color: #fff;'><strong>{$err['type']}</strong>: {$err['message']}</h2>";

		$html.="<ul style='background: #EEE; margin: 0; padding: 1em;'>";
		if(is_array($err['trace'])) foreach($err['trace'] as $t){
			$html.="<li style='list-style: none;'>";
			if(isset($t['file']) || isset($t['line'])){
				$html.="<strong>{$t['file']}:{$t['line']}</strong> &gt;&gt; ";
			}

			$uid="ol_".md5(serialize($t).rand());
			$html.="{$t['class']}{$t['type']}{$t['function']}(".(is_array($t['args']) ? "<a style='cursor: pointer;' onclick='toggle(\"$uid\");'>".count($t['args'])." arguments</a>":"").")";
			if(is_array($t['args'])){
				$html.="<ol id='$uid' style='display: none;'>";
				foreach($t['args'] as $idx=>$a){
					$html.="<li><ul style='margin: 0; padding: 0;'>".$this->var_to_html($a)."</ul></li>";
				}
				$html.="</ol>";
			}
			$html.="</li>";
		}
		$html.="</ul>";

		$uid="ul_".md5(serialize($err['context']).rand());
		$html.="<h2 style='margin: 0;padding: 1em;font-size: 1em;font-weight: normal;background: #666;'><a onclick='toggle(\"$uid\");' style='cursor: pointer; color: #FFF;'>Environment</a></h2>";
		$html.="<ul id='$uid' style='background: #FFF; border: 1px solid #666; margin: 0; padding: 1em; display: none;'>";
		$html.=$this->var_to_html($err['context']);
		$html.="</ul>";

		$html.="</div>";

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
		return $this->on_error(E_ERROR, $e->getMessage(), $e->getFile(), $e->getLine(), $GLOBALS, $e->getTrace());
	}
	
	function on_error($errno, $errstr, $errfile=null, $errline=null, array $errcontext=null, array $errtrace=array()){
		// if error has been supressed with an @
	    if (error_reporting() == 0) return;

		$errlevel=self::$ERROR_LEVEL[self::$PHP_ERROR_MAP[$errno]];

		if(empty($errtrace)) $errtrace=debug_backtrace();
		# Unset $errcontext for this function ($GLOBALS is too big to display)
		for($i=0; $i<count($errtrace); $i++){
			if(@$errtrace[$i]['class']=='Guard' && @$errtrace[$i]['function']=='on_error'){
				unset($errtrace[$i]['args'][4]);
			}
		}

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
