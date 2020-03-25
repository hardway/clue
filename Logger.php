<?php
namespace Clue;

class Logger{
    use \Clue\Logger\LoggerTrait;

    const ERROR=1;
    const CRITICAL=1;
    const EMERGENCY=1;
    const WARNING=2;
    const ALERT=2;
    const NOTICE=3;
    const INFO=4;
    const DEBUG=5;

    const ANY=65535;

    function __construct($name=null, $handler=null, $level_limit=self::ANY){
        $this->enable_log($handler, $level_limit);
        $this->log_channel=$name;
    }

    static function log_level($type){
        return is_numeric($type) ? $type : constant("\\Clue\\Logger::".strtoupper($type));
    }
}
