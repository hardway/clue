<?php
require_once dirname(__DIR__).'/stub.php';

class SampleData{
    use \Clue\Traits\Logger;
}

class Test_Mail_Manual extends PHPUnit_Framework_TestCase{
    protected function setUp(){
    }

    function use_logger($logger){
        $sample=new SampleData();
        $sample->enable_log($logger);

        $sample->notice("This is syslog");
        $sample->debug("debug");
        $sample->info("info");
        $sample->warning("warning");
        $sample->error("error");
        $sample->alert("alert");
        $sample->critical("critical");
        $sample->emergency("crash");

        $sample->notice("This is syslog", ['backtrace'=>true]);
        $sample->notice("This is syslog", ['memory'=>true]);
        $sample->notice("This is syslog", ['http'=>true]);
    }

    function test_hipchat(){
        $this->markTestSkipped();

        $this->use_logger(new Clue\Logger\HipChat('NewRelic', 'WeF1MQcfmBviy1YlQXjEb43pacKic1fhzcbRcIZm', 'PHP'));
        exit();
    }

    function test_syslog(){
        $this->use_logger(new Clue\Logger\Syslog);
    }

    function test_file_log(){
        $this->use_logger("/tmp/test.log");
        echo "File Log Content: \n";
        echo file_get_contents("/tmp/test.log");
        echo "\n\n";
    }

    function test_db_log(){
        // $this->use_logger(new \Clue\Logger\DB(['type'=>'mysql', 'host'=>'localhost', 'db'=>'test', 'username'=>'root'], 'log'));
    }

    // function test_gelf(){
    //     // $this->markTestSkipped();

    //     $this->use_logger(new Clue\Logger\GELF('devops.sign4x.com'));
    // }

    // function test_email_log(){
    //     $this->markTestSkipped();

    //     $this->use_logger(new \Clue\Logger\EMail('hou.danwu@qq.com'));
    // }
}
