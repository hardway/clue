<?php
    require_once dirname(__DIR__).'/stub.php';

    if(!class_exists("PHPUnit_Framework_TestCase")){
        class PHPUnit_Framework_TestCase {}

        if(isset($_GET['status'])){
            header("HTTP/1.0 {$_GET['status']} TEST");
            exit();
        }
    }

    class DebugCrawler extends Clue\Web\Crawler{
        function crawl_503($url, $html){
            var_dump($url, $html);
            exit();
        }

        function process_503($data){
            var_dump($data);
        }
    }

    class Test_Cralwer extends PHPUnit_Framework_TestCase{
        static $SVC;

        static function setUpBeforeClass(){
            $spec = array(
               0 => array("pipe", "r"),
               1 => array("pipe", "w"),
               2 => array("pipe", "w")
            );

            echo "Starting Dummy Server ...";
            self::$SVC = proc_open('php -S localhost:31415 '.__FILE__, $spec, $pipes);
            sleep(1);
            echo "\n";

        }

        static function tearDownAfterClass(){
            if(is_resource(self::$SVC)){
                // $info=proc_get_status(self::$SVC);
                // exec("kill -9 {$info['pid']}");
                // $ret=proc_close(self::$SVC);

                self::$SVC=null;
            }

            exec("kill \$(ps -af|grep \"php -S localhost:31415\" | awk '{print $2}')");
        }

        function test_status_503_retry(){
            $c=new DebugCrawler();
            $c->client->destroy_cache('http://localhost:31415/?status=503');
            $c->queue('503', 'http://localhost:31415/?status=503');
            $c->crawl();
        }
    }
?>
