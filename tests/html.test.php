<?php
	require_once dirname(__DIR__).'/stub.php';

	class Test_HTML extends PHPUnit_Framework_TestCase{
		function test_select_option_single(){
			$options=['a'=>'apple', 'b'=>'banana'];
			$html=Clue\HTML::select_options($options);
			$this->assertRegexp('/option value=.a.+>apple<\/option>/', $html);
			$this->assertRegexp('/option value=.b.+>banana<\/option>/', $html);

			$options=['apple', 'banana'];
			$html=Clue\HTML::select_options($options);
			$this->assertRegexp('/option value=.apple.+>apple<\/option>/', $html);
			$this->assertRegexp('/option value=.banana.+>banana<\/option>/', $html);

			$options=['a'=>'apple', '---', 'b'=>'banana'];
			$html=Clue\HTML::select_options($options);
			$this->assertRegexp('/option disabled.+>(\_|─)+<\/option>/', $html);
		}
	}
?>
