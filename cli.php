<?php
/**
 * REF: http://www.termsys.demon.co.uk/vtansi.htm
 * REF: https://en.wikipedia.org/wiki/ANSI_escape_code#Colors_and_Styles
 */
namespace Clue{
    // 避免cgi模式下缺少定义
    if(!defined('STDERR')) define('STDERR', fopen('php://stderr', 'w'));
    if(!defined('STDIN'))  define('STDIN',  fopen('php://stdin', 'r'));
    defined('TERM') || define('TERM', $_SERVER['TERM'] ?? '');

    class CLI{

        /** @throws \RuntimeException 非 CLI 模式下交互方法 */
        private static function _ensure_cli(): void{
            if(php_sapi_name() !== 'cli'){
                throw new \RuntimeException(
                    'Interactive methods (input/confirm/password) require CLI mode.'
                );
            }
        }

        static function init($consoleEncoding="UTF-8", $internalEncoding="UTF-8"){
            if(php_sapi_name()!='cli') exit("Can only be invoked through console.");

            if(strcasecmp($consoleEncoding, $internalEncoding)!=0){
                mb_internal_encoding($internalEncoding);
                mb_http_output($consoleEncoding);
                ob_start('mb_output_handler');
            }
        }

        static function input($question){
            self::_ensure_cli();

            echo $question." "; self::flush();
            return trim(fgets(STDIN));
        }

        static function confirm($question, $default='N'){
            self::_ensure_cli();

            echo $question." "; self::flush();

            $confirmation=trim(fgets(STDIN));
            if(empty($confirmation)) $confirmation=$default;

            return $confirmation=='Y' || $confirmation=='y';
        }

        /**
         * 提示用户输入密码
         */
        static function password($prompt='Password: '){
            self::_ensure_cli();

            fprintf(STDERR, "%s ", $prompt);

            try {
                exec('stty -echo');
                $pwd = trim(fgets(STDIN));
            } finally {
                exec('stty echo');
            }

            fprintf(STDERR, "\n");

            return $pwd;
        }

        static function flush(){
            flush(); @ob_flush();
        }

        static function log(string $format, ...$args){
            fputs(STDERR, vsprintf($format, $args));
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

            if($color){
                fputs(STDERR, self::ansi($color."_banner"));
            }
            fputs(STDERR, "\n".(TERM=='screen' ? str_repeat(" ", strlen($text)) : "")."\n");
            fputs(STDERR, $text);
            fputs(STDERR, "\n".(TERM=='screen' ? str_repeat(" ", strlen($text)) : "\x1b[K"));
            fputs(STDERR, self::ansi(['RESET']));

            fputs(STDERR, "\n");
        }

        /** 带颜色的格式化输出 */
        private static function colorize(string $color, string $format, ...$args): void{
            fputs(STDERR, self::ansi($color));
            fputs(STDERR, vsprintf($format, $args));
            fputs(STDERR, self::ansi());
        }

        static function warning(string $format, ...$args){ self::colorize('yellow', $format, ...$args); }
        static function info(string $format, ...$args){ self::colorize('cyan', $format, ...$args); }
        static function success(string $format, ...$args){ self::colorize('green', $format, ...$args); }
        static function error(string $format, ...$args){ self::colorize('red', $format, ...$args); }

        static function restore_cursor($name){
            fputs(STDERR, "\x1b8");
        }

        static function save_cursor($name){
            fputs(STDERR, "\x1b7");
        }

        /**
         * 进度条
         *
         * @param int    $done  已完成数
         * @param int    $total 总数
         * @param string $info  附加信息
         * @param int    $width 进度条宽度
         */
        static function progress($done, $total, $info="", $width=50){
            if($total <= 0) return;

            $perc = floor(($done * 100) / $total);
            $bar = floor(($width * $perc) / 100);

            fprintf(STDERR, "%s%% [%s>%s] %s/%s %s\r",
                str_pad($perc, 3, " ", STR_PAD_LEFT),
                str_repeat("=", $bar),
                str_repeat(" ", $width - $bar),
                $done, $total, $info
            );

            self::flush();
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

        private const ANSI_MAP = [
            'RESET'        => "\033[0;0m",
            'RESET_COLOR'  => "\033[39;49m",

            'BLACK'        => "\033[30m",
            'RED'          => "\033[31m",
            'GREEN'        => "\033[32m",
            'YELLOW'       => "\033[33m",
            'BLUE'         => "\033[34m",
            'MAGENTA'      => "\033[35m",
            'CYAN'         => "\033[36m",
            'WHITE'        => "\033[37m",

            'BLACK_BANNER' => "\033[37;40;1m",
            'RED_BANNER'   => "\033[37;41;1m",
            'GREEN_BANNER' => "\033[37;42;1m",
            'YELLOW_BANNER'=> "\033[37;43;1m",
            'BLUE_BANNER'  => "\033[37;44;1m",
            'MAGENTA_BANNER'=>"\033[37;45;1m",
            'CYAN_BANNER'  => "\033[37;46;1m",
            'WHITE_BANNER' => "\033[30;47;1m",

            'ERASE_LINE'   => "\x1b[1K\x1b[999D",
            'ERASE_SCREEN' => "\x1b[2J\x1b[H",
        ];

        static function ansi($code=""){
            if(is_array($code)){
                $ansi="";
                foreach($code as $c){
                    $ansi.=self::ansi($c);
                }
                return $ansi;
            }

            $code=strtoupper($code);

            return self::ANSI_MAP[$code] ?? self::ANSI_MAP['RESET'];
        }
    }
}
