<?php
    require_once dirname(__DIR__).'/stub.php';

    class Test_Web_Client extends PHPUnit_Framework_TestCase{
        function test_follow_url(){
            $c=new Clue\Web\Client();

            $this->assertEquals("mailto:test@abc.com", $c->follow_url("mailto:test@abc.com", "http://some.host.com"));
            $this->assertEquals("ftp://test.ftp.com/abc", $c->follow_url("ftp://test.ftp.com/abc", "http://some.host.com"));
        }
    }
?>
