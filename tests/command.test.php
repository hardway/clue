<?php
	require_once dirname(__DIR__).'/cli/Command.php';

	function test_dummy(){}

	class Test_Command extends PHPUnit_Framework_TestCase{
		function test_env_as_global(){
			$cmd=new \Clue\CLI\Command();
			$_SERVER['TEST']='abc';
			$cmd->add_global('TEST', 'test global option');
			$cmd->handle(['test', 'dummy']);	// 没有参数

			$this->assertEquals('abc', $cmd->get_global('TEST'));
			$this->assertEquals('abc', TEST);
		}

		function test_option_as_global(){
			$cmd=new \Clue\CLI\Command();
			$cmd->add_global('TEST', 'test global option');
			$cmd->set_global('test', 'def');
			$cmd->handle(['test','dummy', '--test=abc']);	// 没有参数

			$this->assertEquals('abc', $cmd->get_global('TEST'));
			$this->assertEquals('abc', TEST);
		}
	}
?>
