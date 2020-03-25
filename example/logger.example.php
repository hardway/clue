<?php
require_once dirname(__DIR__).'/stub.php';

// REF: https://stackify.com/php-monolog-tutorial/

use \Clue\Logger;

class SampleClass{
    use \Clue\Logger\LoggerTrait;

    function foo($bar){
        $this->info(" -INFO- ".$bar);
        $this->error(" -ERROR- ".$bar);
    }
}

$cmd=new Clue\CLI\Command("Logger Example", 'example');
$cmd->add_global('verbose', 'Verbose');
$cmd->add_global('debug', 'Debug');
$cmd->add_global('dry', 'Dry Run');
$cmd->handle($argv);

/**
 * 日志级别
 */
function example_loglevel(){
    $log1=new Clue\Logger();

    $log1->notice("This is syslog");
    $log1->debug("Without channel name");

    usleep(1000*rand(1,9));

    $log2=new Clue\Logger("LogLevelExample", null, Logger::NOTICE);
    error_log("There will not be info log below");
    $log2->info("info");
    $log2->debug("debug");
    error_log("There will not be info log above");

    $log2->warning("warning", ['foo'=>['bar'=>2]]);
    $log2->error("error");

    usleep(1000*rand(1,9));

    $log1->alert("alert");
    $log1->critical("critical");
    $log1->emergency("crash");
}

/**
 * Trait特性
 */
function example_trait(){
    $sample=new SampleClass();
    $sample->foo("SHOULD NOT BE LOGGED");
    $sample->enable_log();
    $sample->foo("Only line logged");
    $sample->disable_log();
    $sample->foo("SHOULD NOT BE LOGGED AGAIN");
    $sample->enable_log(null, Logger::ERROR);
    $sample->foo("Only error messsage now");
}

/**
 * File输出
 */
function example_file(){
    $file="/tmp/test.log";
    @unlink($file);

    $log=new Clue\Logger("FileExample", $file);
    $log->info("Hello");
    $log->notice("This is syslog", ['memory'=>1]);

    echo "File Log Content: \n";
    echo file_get_contents("/tmp/test.log");
    echo "\n\n";
}

/**
 * 附加其他内容的Processor
 */
function example_processor(){
    $log=new Clue\Logger();
    $log->notice("This is syslog", ['memory'=>1]);
    $log->debug("without channel name", ['backtrace'=>1]);
    $log->info("No http context attached", ['process'=>1, 'memory'=>1]);
}

/**
 * 存入数据库
 */
function example_database(){
    panic("TODO");

    // TODO: 支持 mysql://root:@localhost/test/log
    $log=new Clue\Logger(__FUNCTION__, new \Clue\Logger\DB([
        'type'=>'mysql', 'host'=>'localhost', 'db'=>'test', 'username'=>'root'
    ], 'log'));
}

/**
 * 通过外部API发送日志
 */
function example_gelf(){
    panic("TODO");

    $this->use_logger(new Clue\Logger\GELF('devops.sign4x.com'));
}

/**
 * 发送邮件
 * @param $email 邮件地址
 */
function example_email($email){
    $log=new Clue\Logger(null, new \Clue\Logger\EMailHandler($email), Logger::ERROR);
    // $log=new Logger();
    $log->info("info");
    $log->warning("warning");
    $log->error("error", ['backtrace'=>true]);
}
