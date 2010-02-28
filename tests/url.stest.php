<?php  
	require_once 'clue/url.php';
	
	class Test_Clue_URL extends Snap_UnitTestCase{
		function setUp(){}
		function tearDown(){}
		
		function test_can_recognise_a_absolute_url(){
			$this->assertTrue(Clue_URL::is_absolute("http://www.google.com"));
			$this->assertFalse(Clue_URL::is_absolute("image/test.png"));
			$this->assertFalse(Clue_URL::is_absolute("/assets/main.css"));
			$this->assertFalse(Clue_URL::is_absolute("../image/test.png"));
			
			return $this->assertTrue(true);
		}
		
		function test_parse_normal_url(){
			$u=new Clue_URL('http://www.google.com/search?q=abc');
			$this->assertEqual($u->host, 'www.google.com');
			$this->assertEqual($u->port, '80');
			$this->assertEqual($u->scheme, 'http');
			$this->assertEqual($u->path, '/search');
			$this->assertEqual($u->query, 'q=abc');
			
			return $this->assertTrue(true);
		}
		
		function test_url_can_be_reformmed_correctly(){
			$u=new Clue_URL('http://www.google.com/search?q=abc');
			
			$this->assertEqual($u->get_url(), 'http://www.google.com/search?q=abc');
			return $this->assertTrue(true);
		}
		
		function test_url_reform_with_special_port(){
			$URL='http://www.shzfcg.gov.cn:8090/new_web/zfcg_new.jsp';
			$u=new Clue_URL($URL);
			
			$this->assertEqual($u->get_url(), $URL);
			return $this->assertTrue(true);
		}
		
		function test_parse_url_with_default_port_for_https(){
			$u=new Clue_URL('https://www.google.com/search?q=abc');
			$this->assertEqual($u->port, 443);
			
			return $this->assertTrue(true);
		}
		
		function test_resolve_relative_url(){
			$u=new Clue_URL('http://www.google.com/search?q=abc');
			$r=$u->resolve('/tools');
			
			$this->assertEqual($r->get_url(), 'http://www.google.com/tools');
			
			return $this->assertTrue(true);
		}
		
		function test_resolve_url_with_dotted_segments(){
			$u=new Clue_URL("http://www.sina.com/news/latest.htm");
			$r=$u->resolve("../img/latest.gif?t=123");
			
			$this->assertEqual($r->get_url(), "http://www.sina.com/img/latest.gif?t=123");
			
			return $this->assertTrue(true);
		}
		
		function test_resolve_url_with_filename(){
			$u=new Clue_URL("http://www.sina.com/news/latest.htm#1");
			$r=$u->resolve("top.html");
			
			$this->assertEqual($r->get_url(), "http://www.sina.com/news/top.html");
			
			return $this->assertTrue(true);
		}
	}
?>
