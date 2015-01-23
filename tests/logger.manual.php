<?php
require_once dirname(__DIR__).'/stub.php';

class Test_Mail_Manual extends PHPUnit_Framework_TestCase{
    protected function setUp(){
    }

    function send_sample_data($logger){
        $logger->log("This is syslog");
        $logger->debug("debug");
        $logger->info("info");
        $logger->warning("warning");
        $logger->error("error");
        $logger->alert("alert");
        $logger->critical("critical");
        $logger->crash("crash");

        $logger->write(['time'=>'1980-1-1', 'level'=>'WTF', 'message'=>str_repeat(md5(time()), 50)]);
    }

    function test_syslog(){
        $this->send_sample_data(new Clue\Logger\Syslog);
    }

    function test_file_log(){

    }

    function test_db_log(){
        $this->send_sample_data(new \Clue\Logger\DB(['type'=>'mysql', 'host'=>'localhost', 'db'=>'test', 'username'=>'root'], 'log'));
    }

    function test_email_log(){
        // $this->markTestSkipped();

        $this->send_sample_data(new \Clue\Logger\EMail('hou.danwu@gmail.com'));
    }
}
