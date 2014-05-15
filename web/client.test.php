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
				array("/", "http://host.com", "http://host.com/"),
				array("/", "http://host.com/", "http://host.com/"),
				array("/", "http://host.com/folder/", "http://host.com/"),
				array("#t", "http://host.com/folder/", "http://host.com/folder/#t"),
				array("", "http://no-slash.com", "http://no-slash.com"),
				array("", "http://keep-slash.com/", "http://keep-slash.com/"),
				array('images/2013-07-Mold-7.jpg', 'http://www.abc.com/dimensional/articles/2013-07-01.php3', 'http://www.abc.com/dimensional/articles/images/2013-07-Mold-7.jpg'),
			);

			foreach($tests as list($href, $current, $expected)){
				$this->assertEquals(
					$expected,
					$this->client->follow_url($href, $current),
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
					$expected,
					$this->client->follow_url($href, $current),
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
					$expected,
					$this->client->follow_url($href, $current),
					"$href => $current ==> $expected"
				);
			}
		}

		function test_follow_url_with_fragment(){
			$tests=array(
				//array("#1234", "http://www.google.com", "http://www.google.com/#1234"),
				array("test#1234", "http://www.google.com", "http://www.google.com/test#1234"),
			);

			foreach($tests as list($href, $current, $expected)){
				$this->assertEquals(
					$expected,
					$this->client->follow_url($href, $current),
					"$href => $current ==> $expected"
				);
			}
		}
	}
?>
