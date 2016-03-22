<?php
    require_once dirname(__DIR__).'/stub.php';

    // 独立HTTPServer的行为
    if(!class_exists("PHPUnit_Framework_TestCase")){
        class PHPUnit_Framework_TestCase {}
        if($_SERVER["REQUEST_URI"]=='/redirect'){
            header("Location: /"); exit();
        }

        exit(json_encode($_SERVER));
    }

    class Test_Web_Client extends PHPUnit_Framework_TestCase{
    	// 运行测试时启动HTTP服务
        static $SVC;
        static $PIPES;

        static function setUpBeforeClass(){
            $spec = array(
               0 => array("pipe", "r"),
               1 => array("pipe", "w"),
               2 => array("pipe", "w")
            );

            echo "Starting Dummy Server ...";
            self::$SVC = proc_open('php -S localhost:31415 '.__FILE__, $spec, self::$PIPES);
            sleep(1);
            echo "\n";
        }

        // 测试完成时停止HTTP服务
        static function tearDownAfterClass(){
            if(is_resource(self::$SVC)){
                // $info=proc_get_status(self::$SVC);
                // exec("kill -9 {$info['pid']}");
                // $ret=proc_close(self::$SVC);

                self::$SVC=null;
            }

            exec("kill \$(ps -af|grep \"php -S localhost:31415\" | awk '{print $2}')");
        }

        function test_http_restful(){
            $c=new Clue\Web\Client();

            $response=json_decode($c->get("http://localhost:31415/test/get"), true);
            $this->assertEquals($response['REQUEST_METHOD'], 'GET');

            $response=json_decode($c->post("http://localhost:31415/test/post", null), true);
            $this->assertEquals($response['REQUEST_METHOD'], 'POST');

            $response=json_decode($c->delete("http://localhost:31415/test/delete"), true);
            $this->assertEquals($response['REQUEST_METHOD'], 'DELETE');

            $response=json_decode($c->put("http://localhost:31415/test/put", null), true);
            $this->assertEquals($response['REQUEST_METHOD'], 'PUT');
        }

        function test_follow_url(){
            $c=new Clue\Web\Client();

            $this->assertEquals("mailto:test@abc.com", $c->follow_url("mailto:test@abc.com", "http://some.host.com"));
            $this->assertEquals("ftp://test.ftp.com/abc", $c->follow_url("ftp://test.ftp.com/abc", "http://some.host.com"));
        }
    }
?>
