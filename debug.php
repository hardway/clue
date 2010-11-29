<?php  
	require_once __DIR__.'/core.php';
	
	class Clue_Debug{
	    static public $cli=false;
	    
	    static private $htmlStyleDumped=false;
	    static private $errorLevel=array(
            E_NOTICE=>"E_NOTICE",
            E_USER_NOTICE=>'E_USER_NOTICE',
            E_STRICT=>'E_STRICT',
            E_DEPRECATED=>'E_DEPRECATED',
            E_USER_DEPRECATED=>'E_USER_DEPRECATED',
            
            E_WARNING=>'E_WARNING',
            E_CORE_WARNING=>'E_CORE_WARNING',
            E_USER_WARNING=>'E_USER_WARNING',
            
            E_ERROR=>'E_ERROR',
            E_CORE_ERROR=>'E_CORE_ERROR',
            E_USER_ERROR=>'E_USER_ERROR',
            
            E_RECOVERABLE_ERROR=>'E_RECOVERABLE_ERROR'
	    );
	    
		static function view_exception($e){
		    self::dump_exception($e);
		}
		
		static function view_error($errno, $errstr, $errfile=null, $errline=null, array $errcontext=null){
		    if(!self::$cli){
		        self::dump_html_style();
				$tracing=debug_backtrace();
				$pos=0; $trace=array();
		        foreach($tracing as $stack){
					$file=isset($stack['file']) ? $stack['file'] : "";
					$line=isset($stack['line']) ? $stack['line'] : "";
					if(empty($file) && empty($line)) continue;
					if($file==$errfile && $line==$errline) continue;
					
					$trace[]="#$pos $file:$line";
					$pos++;
				}
				
                echo "<div class='dump'><fieldset><legend>".self::$errorLevel[$errno]."</legend>";
                echo "<div class='error'>";
                echo "<div class='title'><div class='message'>$errstr</div>";
                echo "<div class='position'>$errfile:$errline</div></div>";
                echo "<pre class='trace'><strong>Backtrace:</strong>\n".implode("\n", $trace)."</pre>";
                echo "</div>";
                echo "</fieldset></div>";
	        }
		}
		
		static function dump_html_style(){
		    if(!self::$htmlStyleDumped){
    		    echo "
    		        <style type='text/css' media='screen'>
                        .dump {background: #FFF; padding: 5px; font: 13px consolas, monospace;}
                        .dump fieldset {border: 1px solid #666;  padding: 15px 5px 5px 5px;}
                        .dump legend {background: #E7ECF0; color: #333; font-weight: bold; padding: 5px;}
                        .dump .error {padding: 5px;}
    		            .dump .error .title { height: 1.5em; background: #900; color: #FFF; padding: 5px; margin: 5px 0; font-weight: bold;}
    		            .dump .error a {color: #FF9;}
    		            .dump .error .message {float: left;}
    		            .dump .error .position {float: right;}
    		            .dump .error .trace {margin: 0; font: 13px consolas, monospace; background: #FFD; padding: 15px; white-space: pre;}
    		        </style>
    		    ";
    		    self::$htmlStyleDumped=true;
		    }
		}
		
		static function dump_exception($e){
		    if(!self::$cli){
		        self::dump_html_style();
		        
		        echo "<div class='dump'><fieldset><legend>".get_class($e)."</legend>";
    		    echo "<div class='error'>";
    		    echo "<div class='title'><div class='message'>[".$e->getCode()."] ".$e->getMessage()."</div>";
    		    echo "<div class='position'>".$e->getFile().":".$e->getLine()."</div></div>";
    		    echo "<pre class='trace'><strong>Backtrace:</strong>\n".$e->getTraceAsString()."</pre>";
    		    echo "</div>";
    		    echo "</fieldset></div>";
			}
		}
	}
?>
