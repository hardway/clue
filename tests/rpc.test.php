<?php
    require_once dirname(__DIR__).'/stub.php';

    define("TEST_ENABLE_BOOKKEEPING", 0);

    class TestService{
        function ping($payload=null){
            return $payload ?: "pong";
        }

        function trigger_error(){
            throw new Exception("This is a test error", 100);
        }

        function reverse($str){
            return implode("", array_reverse(str_split($str)));
        }
    }

    function init_bookkeeper(){
        $db=Clue\Database::create(['type'=>'mysql', 'db'=>'test', 'username'=>'root']);
        if(!$db->has_table('rpc_log')){
            $db->exec("
                create table rpc_log(
                    id serial,
                    call_time datetime not null,
                    type enum('in', 'out') not null,
                    endpoint varchar(128) not null,
                    ip int unsigned,
                    client varchar(64),
                    method varchar(64),
                    status int,
                    request text,
                    response text
                )
            ");
        }

        // $db->exec("truncate table rpc_log");
        return $db;
    }
    if(defined('TEST_ENABLE_BOOKKEEPING') && TEST_ENABLE_BOOKKEEPING){
        Clue\RPC\Server::enable_bookkeeping(init_bookkeeper(), 'rpc_log');
        Clue\RPC\Client::enable_bookkeeping(init_bookkeeper(), 'rpc_log');
    }

    if(!class_exists("PHPUnit_Framework_TestCase")){
        class PHPUnit_Framework_TestCase {}
        if(@$_SERVER["REQUEST_URI"]=='/redirect'){
            header("Location: /"); exit();
        }

        Clue\RPC\Server::bind(new TestService);
    }

    class Test_RPC extends PHPUnit_Framework_TestCase{
        static $SVC;
        static $PIPES;
        static $ENDPOINT;

        static function setUpBeforeClass(){
            $spec = array(
               0 => array("pipe", "r"),
               1 => array("pipe", "w"),
               2 => array("pipe", "w")
            );

            $port=rand(1024, 65535);
            self::$ENDPOINT="http://127.0.0.1:$port";

            echo "Starting Dummy Server ...";
            self::$SVC = proc_open("php -S 127.0.0.1:$port ".__FILE__, $spec, self::$PIPES);
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

            exec("kill \$(ps -af|grep \"php -S .*rpc.test.php\" | awk '{print $2}')");
        }

        function test_basic(){
            $c=new Clue\RPC\Client(self::$ENDPOINT);
            $this->assertEquals("pong", $c->ping());
        }

        function test_reverse(){
            $c=new Clue\RPC\Client(self::$ENDPOINT);
            $this->assertEquals("olleh", $c->reverse("hello"));
        }

        function test_redirect(){
            $c=new Clue\RPC\Client(self::$ENDPOINT."/redirect");
            $this->assertEquals("pong", $c->ping());
        }

        function test_compression(){
            $c=new Clue\RPC\Client(self::$ENDPOINT, ['compression'=>1]);
            $block=str_repeat(rand(), 1000);
            $this->assertEquals($block, $c->ping($block));
        }

        /**
         * @expectedException Exception
         * @expectedExceptionMessage This is a test error
         * @expectedExceptionCode 100
         */
        function test_exception(){
            $c=new Clue\RPC\Client(self::$ENDPOINT);
            echo $c->trigger_error();
        }
    }
?>
