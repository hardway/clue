<?php
    // When loaded as a CLI-server router script (php -S)
    if (php_sapi_name() === 'cli-server' && isset($_GET['status'])) {
        header("HTTP/1.0 {$_GET['status']} TEST");
        exit();
    }

    class DebugCrawler extends Clue\Web\Crawler{
        public $last503Url = '';
        public $last503Html = '';

        function crawl_503($url, $html){
            $this->last503Url = $url;
            $this->last503Html = $html;
        }

        function crawl_index($url, $html){
            // no-op
        }

        function process_503($data){
            // no-op for test
        }
    }

    class Test_Crawler extends PHPUnit_Framework_TestCase{
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
            self::$SVC = null;
            exec("kill \$(ps -af|grep \"php -S localhost:31415\" | awk '{print $2}')");
        }

        function test_status_503_retry(){
            $c = new DebugCrawler();

            // 缩短重试间隔和次数，加速测试
            $c->retry_download = 2;
            $c->retry_delay = 1;

            $c->client->destroy_cache('http://localhost:31415/?status=503');
            $c->queue('503', 'http://localhost:31415/?status=503');
            $c->crawl();

            // 验证重试后回调被调用
            $this->assertNotEmpty($c->last503Url, 'crawl_503 should have been called');
            $this->assertContains('status=503', $c->last503Url);
        }

        function test_status_200(){
            $c = new DebugCrawler();
            $c->retry_download = 2;
            $c->retry_delay = 1;

            $c->client->destroy_cache('http://localhost:31415/?status=200');
            $c->queue('index', 'http://localhost:31415/?status=200');
            $c->crawl();
            $this->assertTrue(true);
        }
    }
