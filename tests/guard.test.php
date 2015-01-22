<?php
    require_once dirname(__DIR__).'/stub.php';

    @define("FATAL_ERROR_SCRIPT", '/tmp/clue-fatal-error.php');
    @define("FATAL_ERROR_LOG", '/tmp/clue-fatal-error.log');

    class Test_Guard extends PHPUnit_Framework_TestCase{
        protected $backupGlobals = FALSE;
        protected $backupGlobalsBlacklist = array('mysql');

        protected function setUp(){
            $this->app=new Clue\Application(['config'=>null]);
        }

        function test_catch_fatal_error(){
            @unlink(FATAL_ERROR_SCRIPT);
            @unlink(FATAL_ERROR_LOG);

            file_put_contents(FATAL_ERROR_SCRIPT, '<?php require "clue/stub.php"; $g=new Clue\Guard(["log_file"=>"'.FATAL_ERROR_LOG.'"]); $f=new Foo();');

            exec("php ".FATAL_ERROR_SCRIPT.' 2>&1 1>/dev/null', $output, $ret);

            $this->assertNotEquals(0, $ret);
            $this->assertTrue(!!preg_match('/PHP Fatal error/i', implode("\n", $output)), 'Fatal error did happen');
            $this->assertTrue(is_file(FATAL_ERROR_LOG), "Fatal error catched log file.");

            @unlink(FATAL_ERROR_SCRIPT);
            @unlink(FATAL_ERROR_LOG);
        }
    }
?>
