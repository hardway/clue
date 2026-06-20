<?php
    @define("FATAL_ERROR_SCRIPT", '/tmp/clue-fatal-error.php');
    @define("FATAL_ERROR_LOG", '/tmp/clue-fatal-error.log');

    class Test_Guard extends PHPUnit_Framework_TestCase{
    public $app;
        protected function setUp(): void{
            $this->app=new Clue\Application(['config'=>null]);
        }

        function test_catch_fatal_error(){
            @unlink(FATAL_ERROR_SCRIPT);
            @unlink(FATAL_ERROR_LOG);

            file_put_contents(FATAL_ERROR_SCRIPT, '<?php require "stub.php"; $g=new Clue\Guard(["log_file"=>"'.FATAL_ERROR_LOG.'"]); $f=new Foo();');

            exec("php ".FATAL_ERROR_SCRIPT.' 2>&1', $output, $ret);

            // Guard should catch the fatal error and log it; script exits normally
            $this->assertEquals(0, $ret, 'Guard should handle fatal error without crash');
            $this->assertTrue(!!preg_match('/error occurred recently/i', implode("\n", $output)), 'Guard should output error summary');
            $this->assertTrue(is_file(FATAL_ERROR_LOG), 'Guard should log fatal error to file');

            @unlink(FATAL_ERROR_SCRIPT);
            @unlink(FATAL_ERROR_LOG);
        }
    }
?>
