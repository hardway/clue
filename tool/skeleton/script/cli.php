<?php
include dirname(__DIR__)."/stub.php";

use Clue\CLI as CLI;

foreach(glob(__DIR__.'/command/*.php') as $f) @include $f;

$cmd=new Clue\CLI\Command(APP_NAME);
$cmd->add_global('verbose', 'Verbose');
$cmd->add_global('debug', 'Debug');
$cmd->add_global('dry', 'Dry Run');

try{
    $cmd->handle($argv);
}
catch(Exception $e){
    Clue\CLI::banner($e->getMessage(), 'red');
    error_log($e->getTraceAsString());
}


/**
 * 测试邮件发送功能
 *
 * @param $recipient 接受用户地址
 * @param $subject 主题
 * @param $debug 是否显示调试信息
 */
function cli_test_email($recipient, $subject='Test Email', $debug=false){
    if($debug){
        @define("DEBUG_EMAIL", true);
    }

    $body=print_r($_SERVER, true);
    Email::send_mail($recipient, $subject, $body);
    error_log("Email Sent");
}
