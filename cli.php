<?php
/**
 * REF: http://www.termsys.demon.co.uk/vtansi.htm
 */
namespace Clue{
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

            $confirmation=trim(fgetc(STDIN));
            if(empty($confirmation)) $confirmation=$default;

            return $confirmation=='Y' || $confirmation=='y';
        }

        static function flush(){
            flush(); @ob_flush();
        }

        static function log(){
            fputs(STDERR, vsprintf(func_get_args()[0], array_slice(func_get_args(), 1)));
        }

        static function warning($str){
            self::ansi("yellow");
            fputs(STDERR, vsprintf(func_get_args()[0], array_slice(func_get_args(), 1)));
            self::ansi();
        }

        static function success($str){
            self::ansi("green");
            fputs(STDERR, vsprintf(func_get_args()[0], array_slice(func_get_args(), 1)));
            self::ansi();
        }

        static function error($str){
            self::ansi("red");
            fputs(STDERR, vsprintf(func_get_args()[0], array_slice(func_get_args(), 1)));
            self::ansi();
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
            $RESET="\033[0;0m";
            $ANSI=[
                'RED'=>"\033[31m",
                'YELLOW'=>"\033[33m",
                'GREEN'=>"\033[32m",
            ];

            $code=strtoupper($code);

            fputs(STDERR, isset($ANSI[$code]) ? $ANSI[$code] : $RESET);
        }
    }
}
