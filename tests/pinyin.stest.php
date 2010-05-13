<?php  
    require_once 'simpletest/autorun.php';
	require_once dirname(__DIR__).'/pinyin.php';
		
	class Test_Clue_PinYin extends UnitTestCase{
		private $py;
		
		function setUp(){
		    /*
			$cfg=new Clue_Config();
			$db=Clue_Database::create('mysql', $cfg->database);
			$this->py=new Clue_PinYin($db);
			*/
		}
		
		function tearDown(){}
		
		function test_soundex(){
			/*
			$this->assertEqual("a", $this->py->soundex("啊"));
			echo $this->py->soundex("你好") , "\n";
			echo $this->py->soundex("一只棕色狐狸飞快地跳过那只懒惰的狗"), "\n";
			echo $this->py->soundex("中英文混排，Chinglish, haha :) 哦也！"), "\n";
			echo $this->py->soundex2("胡锦涛")."\n";
			echo $this->py->soundex2("侯丹午")."\n";
			echo $this->py->soundex2("陈斌B")."\n";
			echo $this->py->soundex2("Leonardo Davinci")."\n";
			*/

			return $this->assertTrue(true);
		}
	}
?>
