<?php
	require_once dirname(__DIR__).'/stub.php';

	class Test_URL extends PHPUnit_Framework_TestCase{
		function test_normalize(){
			$test_cases=[
				'http://abc//def'=>'http://abc/def',
				'file:/abc//def'=>'file:///abc/def',
				'http://abc.com///def/./.././abc?n=1#qqq'=>'http://abc.com/abc?n=1#qqq',
				'//abc//def'=>'//abc/def',
			];

			foreach($test_cases as $from=>$to){
				$this->assertEquals($to, url_normalize($from));
			}
		}
	}
