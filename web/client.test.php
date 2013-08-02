<?php
	include "client.php";
	use Clue\Web\Client as Client;

	class ClientTest extends PHPUnit_Framework_TestCase{
		protected function setUp(){
			$this->client=new Client();
		}

		function test_follow_url(){
			$tests=array(
				array("a", "http://www.google.com", "http://www.google.com/a"),
				array("../a", "http://www.google.com", "http://www.google.com/a"),
				array("www.baidu.com/a", "http://www.google.com", "http://www.google.com/www.baidu.com/a"),
				array("/a", "http://www.google.com/a/b/c/d", "http://www.google.com/a"),
			);

			foreach($tests as list($href, $current, $expected)){
				$this->assertEquals(
					$this->client->follow_url($href, $current),
					$expected,
					"$href => $current ==> $expected"
				);
			}
		}

		function test_follow_url_with_schema(){
			$tests=array(
				array("http://www.baidu.com/a", "http://www.google.com", "http://www.baidu.com/a"),
				array("https://www.baidu.com/a", "http://www.google.com", "https://www.baidu.com/a"),
				array("ftp://www.baidu.com", "http://www.google.com", "ftp://www.baidu.com"),
			);

			foreach($tests as list($href, $current, $expected)){
				$this->assertEquals(
					$this->client->follow_url($href, $current),
					$expected,
					"$href => $current ==> $expected"
				);
			}
		}

		function test_follow_url_with_query(){
			$tests=array(
				array("test?a=1&b=2", "http://www.google.com", "http://www.google.com/test?a=1&b=2"),
				array("test?a=1&b=2", "http://www.google.com?c=3", "http://www.google.com/test?a=1&b=2"),
			);

			foreach($tests as list($href, $current, $expected)){
				$this->assertEquals(
					$this->client->follow_url($href, $current),
					$expected,
					"$href => $current ==> $expected"
				);
			}
		}

		function test_follow_url_with_fragment(){
			$tests=array(
				array("#1234", "http://www.google.com", "http://www.google.com/#1234"),
				array("test#1234", "http://www.google.com", "http://www.google.com/test#1234"),
			);

			foreach($tests as list($href, $current, $expected)){
				$this->assertEquals(
					$this->client->follow_url($href, $current),
					$expected,
					"$href => $current ==> $expected"
				);
			}
		}
	}
?>
