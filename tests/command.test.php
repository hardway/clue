<?php
	require_once dirname(__DIR__).'/cli/Command.php';

	function test_dummy(){}
	function test_basic($foo, $bar="default"){}

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

		function test_arguments(){
			$cmd=new \Clue\CLI\Command();
			$cmd->handle(['test','basic', 'foo', 'bar']);
			$this->assertEquals('foo', $cmd->args[0]);
			$this->assertEquals('bar', $cmd->args[1]);

			$cmd->handle(['test','basic', 'foo']);
			$this->assertEquals('foo', $cmd->args[0]);
			$this->assertFalse(isset($cmd->args[1]));
		}

		function test_argument_value_as_negative_number(){
			$cmd=new \Clue\CLI\Command();
			$cmd->handle(['test','basic', '-1']);
			$this->assertEquals('-1', $cmd->args[0]);
		}
	}
?>
