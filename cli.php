<?php  
    class Clue_CLI{
        static $consoleEncoding;
        
        static function init($consoleEncoding, $internalEncoding="UTF-8"){
            self::$consoleEncoding=$consoleEncoding;
            
            mb_internal_encoding($internalEncoding);
            mb_http_output($consoleEncoding);
            ob_start('mb_output_handler');
        }
        
        static function confirm($question, $default='N'){
            echo $question." "; self::flush();
            
            $confirmation=trim(fgetc(STDIN));
            if(empty($confirmation)) $confirmation=$default;
            
            return $confirmation=='Y' || $confirmation=='y';
        }
        
        static function flush(){
            ob_flush();
        }
    }
?>
