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
				'p a b'=>'//p//a//b',

				// 连续元素
				'p > *'=>"//p/*",
				'p > a > b'=>"//p/a/b",

				// 测试属性
				"p[attr]"=>'//p[@attr]',
				"p[attr='abc']"=>'//p[@attr=\'abc\']',

				// 属性包含空格
				'a[@title="Next page"]'=>"//a[@title=\"Next page\"]",

				// 属性包含单词
				'p[class~="hide"]'=>"//p[contains(concat(' ',normalize-space(@class),' '),concat(' ',\"hide\",' '))]",
				// 属性包含字符
				"p[class*='hi']"=>"//p[contains(@class,'hi')]",
				// 属性以字符串开始
				"p[class^='hi']"=>"//p[starts-with(@class,'hi')]",

				// 单个类
				'p.hide'=>"//p[contains(concat(' ',normalize-space(@class),' '),' hide ')]",
				".hide"=>"//*[contains(concat(' ',normalize-space(@class),' '),' hide ')]",
				// 多个类
				'p.hide.again'=>"//p[contains(concat(' ',normalize-space(@class),' '),' hide ') and (contains(concat(' ',normalize-space(@class),' '),' again '))]",

				// 伪元素
				'span.recom li:last-child a'=>"//span[contains(concat(' ',normalize-space(@class),' '),' recom ')]//li[not(following-sibling::*)]//a",
				'ul li:nth-child(2)'=>'//ul//li[position()=2]',
			);

			foreach($tests as $css=>$xpath){
				$this->assertEquals(Parser::css2xpath($css), $xpath);
			}
		}

        function test_xml(){
            $xml='<?xml version="1.0" encoding="UTF-8"?>
<AmazonEnvelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="amzn-envelope.xsd">
    <Header>
        <DocumentVersion>1.02</DocumentVersion>
        <MerchantIdentifier>A1ZGY3RE5LH1AU</MerchantIdentifier>
    </Header>
    <MessageType>ProcessingReport</MessageType>
    <Message>
        <MessageID>1</MessageID>
        <ProcessingReport>
            <DocumentTransactionID>567090017927</DocumentTransactionID>
            <StatusCode>Complete</StatusCode>
            <ProcessingSummary>
                <MessagesProcessed>3</MessagesProcessed>
                <MessagesSuccessful>0</MessagesSuccessful>
                <MessagesWithError>2</MessagesWithError>
                <MessagesWithWarning>0</MessagesWithWarning>
            </ProcessingSummary>
            <Result>
                <MessageID>0</MessageID>
                <ResultCode>Error</ResultCode>
                <ResultMessageCode>5000</ResultMessageCode>
                <ResultDescription>XML Parsing Error at Line 22, Column 28</ResultDescription>
            </Result>
            <Result>
                <MessageID>1</MessageID>
                <ResultCode>Error</ResultCode>
                <ResultMessageCode>5002</ResultMessageCode>
                <ResultDescription>XML Parsing Error at Line 23, Column 9</ResultDescription>
            </Result>
        </ProcessingReport>
    </Message>
</AmazonEnvelope>
';
            $dom=new Parser($xml, 'xml');
            $this->assertEquals("3", $dom->getElement('MessagesProcessed')->text);

            $summary=$dom->getElement("ProcessingSummary")->array;
            $this->assertEquals(3, $summary['MessagesProcessed']);
            $this->assertEquals(0, $summary['MessagesSuccessful']);

            $results=$dom->getElement("ProcessingReport")->array;
            $this->assertEquals(1, $results['Result'][1]['MessageID']);
        }
	}
?>
