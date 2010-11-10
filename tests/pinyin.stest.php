<?php  
    require_once 'simpletest/autorun.php';
	require_once dirname(__DIR__).'/pinyin.php';
		
	class Test_Clue_PinYin extends UnitTestCase{
		private $py;
		
		function setUp(){
			$this->py=new Clue_PinYin();
		}
		
		function tearDown(){}
		
		function test_soundex(){
			$this->assertEqual("a", $this->py->soundex("啊"));
			$this->assertEqual("ni hao", $this->py->soundex("你好"));
			$this->assertEqual("zhong wen ABC", $this->py->soundex("中文ABC"));			
			$this->assertEqual("zhu zhao", $this->py->soundex("朱赵"));

			return $this->assertTrue(true);
		}
	}
?>
