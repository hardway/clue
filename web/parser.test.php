<?php
	include "parser.php";
	use Clue\Web\Parser as Parser;

	class ParserTest extends PHPUnit_Framework_TestCase{
		function test_CSS2XPATH(){
			$tests=array(
				// 任意元素
				"*"=>"//*",
				// Tag P
				"p"=>"//p",
				// 包含元素
				'p *'=>"//p//*",
				// 连续元素
				'p > *'=>"//p/*",

				// 测试属性
				"p[attr]"=>'//p[@attr]',
				// 属性包含单词
				'p[class~="hide"]'=>"//p[contains(concat(' ',normalize-space(@class),' '),concat(' ',\"hide\",' '))]",
				// 属性包含字符
				"p[class*='hi']"=>"//p[contains(@class,'hi')]",
				// 单个类
				'p.hide'=>"//p[contains(concat(' ',normalize-space(@class),' '),' hide ')]",
				// 多个类
				'p.hide.again'=>"//p[contains(concat(' ',normalize-space(@class),' '),' hide ') and (contains(concat(' ',normalize-space(@class),' '),' again '))]",
			);

			foreach($tests as $css=>$xpath){
				$this->assertEquals(Parser::css2xpath($css), $xpath);
			}
		}
	}
?>
