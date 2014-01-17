<?php
namespace Clue{
    class CLI{
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

        static function warning($str){
            self::ansi("yellow");
            echo $str;
            self::ansi();
        }

        static function success($str){
            self::ansi("green");
            echo $str;
            self::ansi();
        }

        static function alert($str){
            self::ansi("red");
            echo $str;
            self::ansi();
        }

        static function ansi($code=""){
            switch(strtoupper($code)){
                case 'RED':
                    echo "\033[31m";
                    break;

                case 'YELLOW':
                    echo "\033[33m";
                    break;

                case 'GREEN':
                    echo "\033[32m";
                    break;

                default:
                    echo "\033[0;0m";
                    break;
            }
        }
    }
}
