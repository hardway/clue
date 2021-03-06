<?php
/**
 * REF: http://www.termsys.demon.co.uk/vtansi.htm
 * REF: https://en.wikipedia.org/wiki/ANSI_escape_code#Colors_and_Styles
 */
namespace Clue{
    // 避免cgi模式下STDERR缺少定义
    if(!defined('STDERR')) define('STDERR', fopen('php://stderr', 'w'));
    @define("TERM", $_SERVER['TERM']);

    class CLI{
        static $_SAVEPOINT=[];

        static function init($consoleEncoding="UTF-8", $internalEncoding="UTF-8"){
            if(php_sapi_name()!='cli') exit("Can only be invoked through console.");

            if(strcasecmp($consoleEncoding, $internalEncoding)!=0){
                mb_internal_encoding($internalEncoding);
                mb_http_output($consoleEncoding);
                ob_start('mb_output_handler');
            }
        }

        static function input($question){
            echo $question." "; self::flush();
            return trim(fgets(STDIN));
        }

        static function confirm($question, $default='N'){
            echo $question." "; self::flush();

            $confirmation=trim(fgets(STDIN));
            if(empty($confirmation)) $confirmation=$default;

            return $confirmation=='Y' || $confirmation=='y';
        }

        /**
         * 提示用户输入密码
         */
        static function password($prompt='Password: '){
            printf("%s ", $prompt);

            system("stty -echo");
            $pwd=trim(fgets(STDIN));
            system("stty echo");

            printf("\n");

            return $pwd;
        }

        static function flush(){
            flush(); @ob_flush();
        }

        static function log(){
            fputs(STDERR, vsprintf(func_get_args()[0], array_slice(func_get_args(), 1)));
        }

        static function text($text, $color=null){
        	if($color) fputs(STDERR, self::ansi($color));
            fputs(STDERR, $text);
        	if($color) fputs(STDERR, self::ansi());
        }

        static function banner($text, $color=null){
            $text="  $text  ";

            // $cols = exec('tput cols');
            // $rows = exec('tput lines');

            fputs(STDERR, self::ansi($color."_banner"));
            fputs(STDERR, "\n".(TERM=='screen' ? str_repeat(" ", strlen($text)) : "")."\n");
        	fputs(STDERR, $text);
            fputs(STDERR, "\n".(TERM=='screen' ? str_repeat(" ", strlen($text)) : "\x1b[K"));
            fputs(STDERR, self::ansi(['RESET']));

            fputs(STDERR, "\n");
        }

        static function warning($str){
            fputs(STDERR, self::ansi("yellow"));
            fputs(STDERR, vsprintf(func_get_args()[0], array_slice(func_get_args(), 1)));
            fputs(STDERR, self::ansi());
        }

        static function info($str){
            fputs(STDERR, self::ansi("cyan"));
            fputs(STDERR, vsprintf(func_get_args()[0], array_slice(func_get_args(), 1)));
            fputs(STDERR, self::ansi());
        }

        static function success($str){
            fputs(STDERR, self::ansi("green"));
            fputs(STDERR, vsprintf(func_get_args()[0], array_slice(func_get_args(), 1)));
            fputs(STDERR, self::ansi());
        }

        static function error($str){
            fputs(STDERR, self::ansi("red"));
            fputs(STDERR, vsprintf(func_get_args()[0], array_slice(func_get_args(), 1)));
            fputs(STDERR, self::ansi());
        }

        static function restore_cursor($name){
            fputs(STDERR, "\x1b8");
        }

        static function save_cursor($name){
            fputs(STDERR, "\x1b7");
        }

        static function erase_line(){
            fputs(STDERR, "\x1b[1K\x1b[999D");
        }

        static function erase_screen(){
            fputs(STDERR, "\x1b[2J\x1b[H");
        }

        static function alert($str){
            self::error($str);
        }

        static function ansi($code=""){
        	if(is_array($code)){
                $ansi="";
        		foreach($code as $c){
        			$ansi.=self::ansi($c);
        		}
        		return $ansi;
        	}

            $RESET="\033[0;0m";
            $ANSI=[
            	'RESET'=>"\033[0;0m",
            	'RESET_COLOR'=>"\033[39;49m",

                'BLACK'=>"\033[30m",
                'RED'=>"\033[31m",
                'GREEN'=>"\033[32m",
                'YELLOW'=>"\033[33m",
                'BLUE'=>"\033[34m",
                'MAGENTA'=>"\033[35m",
                'CYAN'=>"\033[36m",
                'WHITE'=>"\033[37m",

                'BLACK_BANNER'=>"\033[37;40;1m",
                'RED_BANNER'=>"\033[37;41;1m",
                'GREEN_BANNER'=>"\033[37;42;1m",
                'YELLOW_BANNER'=>"\033[37;43;1m",
                'BLUE_BANNER'=>"\033[37;44;1m",
                'MAGENTA_BANNER'=>"\033[37;45;1m",
                'CYAN_BANNER'=>"\033[37;46;1m",
                'WHITE_BANNER'=>"\033[30;47;1m",

                'ERASE_LINE'=>"\x1b[1K\x1b[999D",
                'ERASE_SCREEN'=>"\x1b[2J\x1b[H",
            ];

            $code=strtoupper($code);
            $ansi=isset($ANSI[$code]) ? $ANSI[$code] : $RESET;

            return $ansi;
        }
    }
}
