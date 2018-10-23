<?php
    require_once dirname(__DIR__).'/cli/Command.php';

    function test_dummy(){}
    function test_basic($foo, $bar="default"){
        //print_r(compact('foo', 'bar'));
    }
    function test_defaultargs($foo=1, $bar="2"){
        //print_r(compact('foo', 'bar'));
    }

    class Test_Command extends PHPUnit_Framework_TestCase{
        function test_env_as_global(){
            $cmd=new \Clue\CLI\Command();
            $_SERVER['TEST']='abc';
            $cmd->add_global('TEST', 'test global option');
            $cmd->handle(['test', 'dummy']);    // 没有参数

            $this->assertEquals('abc', $cmd->get_global('TEST'));
            $this->assertEquals('abc', TEST);
        }

        function test_option_as_global(){
            $cmd=new \Clue\CLI\Command();
            $cmd->add_global('TEST', 'test global option');
            $cmd->set_global('test', 'def');
            $cmd->handle(['test','dummy', '--test=abc']);   // 没有参数

            $this->assertEquals('abc', $cmd->get_global('TEST'));
            $this->assertEquals('abc', TEST);
        }

        function test_arguments(){
            $cmd=new \Clue\CLI\Command();
            $cmd->handle(['test','basic', 'foo', 'bar']);
            $this->assertEquals('foo', $cmd->args[0]);
            $this->assertEquals('bar', $cmd->args[1]);

            $cmd->handle(['test','basic', 'f', '-bar=b']);
            $this->assertEquals('f', $cmd->args[0]);
            $this->assertEquals('b', $cmd->options['bar']);

            $cmd->handle(['test','basic', 'foo']);
            $this->assertEquals('foo', $cmd->args[0]);
            $this->assertFalse(isset($cmd->args[1]));

        }

        function test_arguments_default(){
            $cmd=new \Clue\CLI\Command();

            $cmd->handle(['test','defaultargs', '--bar=3']);
            $this->assertEquals(1, $cmd->options['foo']);
            $this->assertEquals(3, $cmd->options['bar']);

            $cmd->handle(['test','defaultargs']);
            $this->assertEquals(1, $cmd->options['foo']);
            $this->assertEquals(2, $cmd->options['bar']);
        }

        function test_argument_value_as_negative_number(){
            $cmd=new \Clue\CLI\Command();
            $cmd->handle(['test','basic', '-1']);
            $this->assertEquals('-1', $cmd->args[0]);

            $cmd->handle(['test','basic', '-1week']);
            $this->assertEquals('-1week', $cmd->args[0]);
        }
    }
?>
