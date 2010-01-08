<?php  
	require_once 'clue/log.php';
	
	class Clue_Log_Database implements IClue_Log{
		static private $CREATE_LOG_TABLE="
			create table %s_log(
				id int not null auto_increment primary key,
				app varchar(32),
				message varchar(512),
				code int,
				file varchar(256),
				line int,
				url varchar(512),
				trace varchar(4096),
				x1 varchar(1024),
				x2 varchar(1024),
				timestamp timestamp default current_timestamp
			) engine=MyISAM
		";
		
		private $db;
		
		function __construct($config){
			$config['db']='database_log';	// TODO
			$this->db=Clue_Database::create('mysql', $config);
			
			// Make sure tables are created.
			foreach(array('exception', 'error', 'warning', 'notice', 'debug') as $t){
				if(!$this->db->has_table("{$t}_log")){
					$this->db->exec(sprintf(self::$CREATE_LOG_TABLE, $t));
				}
			}
		}
		
		function log($app, $message, $level=self::NOTICE, $context=array()){
			$table=strtolower($level)."_log";
			
			$app=$this->db->quote($app);
			$message=$this->db->quote($message);
			$code=isset($context['code']) ? intval($context['code']) : 'null';
			$file=isset($context['file']) ? $this->db->quote($context['file']) : 'null';
			$line=isset($context['line']) ? intval($context['line']) : 'null';
			$url=isset($context['url']) ? $this->db->quote($context['url']) : 'null';
			$trace=isset($context['trace']) ? $this->db->quote(substr($context['trace'], 0, 4000)) : 'null';
			
			$this->db->exec("
				insert into $table(app, message, code, file, line, trace, url)
				values($app, $message, $code, $file, $line, $trace, $url)
			");
		}
		
		function log_notice($app, $message, $context=array()){
			$this->log($app, $message, self::NOTICE, $context);
		}
		
		function log_debug($app, $message, $context=array()){
			$this->log($app, $message, self::DEBUG, $context);
		}
		
		function log_warning($app, $message, $context=array()){
			$this->log($app, $message, self::WARNING, $context);
		}
		
		function log_error($app, $message, $code=0, $context=array()){
			if(is_null($context)) $context=array();
			$context['code']=$code;
			
			$this->log($app, $message, self::ERROR, $context);
		}
		
		function log_exception($app, Exception $exception, $context=array()){
			$table="exception_log";	
			
			$app=$this->db->quote($app);
			$message=$this->db->quote($exception->getMessage());
			$code=$this->db->escape($exception->getCode());
			$file=$this->db->quote($exception->getFile());
			$line=$this->db->escape($exception->getLine());
			$trace=$this->db->quote(substr($exception->getTraceAsString(), 0, 4000));
			$url=isset($context['url']) ? $this->db->quote($context['url']) : 'null';
			
			$x1=$this->db->quote(get_class($exception));
			$extra=array_values(array_diff(
				get_class_methods(get_class($exception)),
				get_class_methods("Exception")
			));

			$x2=null;
			if(count($extra)>0){
				$x2=call_user_func(array($exception, $extra[0]));
			}
			$x2=$this->db->quote($x2);

			$this->db->exec("
				insert into $table(app, message, code, file, line, trace, url, x1, x2)
				values($app, $message, $code, $file, $line, $trace, $url, $x1, $x2)
			");
		}
	}
?>
