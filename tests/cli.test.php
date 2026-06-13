<?php
    /**
     * 测试 Clue\CLI 类和 Clue\CLI\Command pipe 功能
     */

    // ====== CLI 入口（子进程直接运行时路由 feed/pipe 命令） ======
    if(PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__){
        require_once __DIR__ . '/../stub.php';

        $cli = new \Clue\CLI\Command("Test Pipe", 'cli');
        $cli->handle($argv);
        exit();
    }

    // ====== Pipe 回调函数 ======

    /**
     * Pipe Receiver
     * @param $p1 Item1
     * @param $p2 Item2
     */
    function cli_pipe($p1, $p2){
        printf("Got Item: %s\t%s\n", $p1, $p2);
    }

    /**
     * Pipe Generator
     */
    function cli_feed(){
        for($i=0; $i<3; $i++){
            printf("%d, %s\n", $i, chr(ord('a')+$i));
        }
    }

    class Test_CLI extends PHPUnit_Framework_TestCase{

        // ==================== ansi() 纯函数 ====================

        function test_ansi_reset(){
            $this->assertEquals("\033[0;0m", Clue\CLI::ansi('RESET'));
        }

        function test_ansi_colors(){
            $this->assertEquals("\033[30m", Clue\CLI::ansi('BLACK'));
            $this->assertEquals("\033[31m", Clue\CLI::ansi('RED'));
            $this->assertEquals("\033[32m", Clue\CLI::ansi('GREEN'));
            $this->assertEquals("\033[33m", Clue\CLI::ansi('YELLOW'));
            $this->assertEquals("\033[34m", Clue\CLI::ansi('BLUE'));
            $this->assertEquals("\033[35m", Clue\CLI::ansi('MAGENTA'));
            $this->assertEquals("\033[36m", Clue\CLI::ansi('CYAN'));
            $this->assertEquals("\033[37m", Clue\CLI::ansi('WHITE'));
        }

        function test_ansi_banners(){
            $this->assertEquals("\033[37;41;1m", Clue\CLI::ansi('RED_BANNER'));
            $this->assertEquals("\033[37;42;1m", Clue\CLI::ansi('GREEN_BANNER'));
            $this->assertEquals("\033[30;47;1m", Clue\CLI::ansi('WHITE_BANNER'));
        }

        function test_ansi_unknown_returns_reset(){
            $this->assertEquals("\033[0;0m", Clue\CLI::ansi('NONEXISTENT'));
        }

        function test_ansi_case_insensitive(){
            $this->assertEquals("\033[31m", Clue\CLI::ansi('red'));
            $this->assertEquals("\033[31m", Clue\CLI::ansi('Red'));
        }

        function test_ansi_array(){
            $this->assertEquals("\033[31m\033[32m", Clue\CLI::ansi(['RED', 'GREEN']));
        }

        // ==================== 输出方法（不抛异常即通过） ====================

        function test_log(){
            Clue\CLI::log("test log %s %d", "hello", 42);
            $this->assertTrue(true);
        }

        function test_text(){
            Clue\CLI::text("plain");
            Clue\CLI::text("colored", "red");
            $this->assertTrue(true);
        }

        function test_banner(){
            Clue\CLI::banner("plain");
            Clue\CLI::banner("green", "green");
            $this->assertTrue(true);
        }

        function test_warning(){
            Clue\CLI::warning("warn: %s", "test");
            $this->assertTrue(true);
        }

        function test_info(){
            Clue\CLI::info("info: %s", "test");
            $this->assertTrue(true);
        }

        function test_success(){
            Clue\CLI::success("ok: %s", "test");
            $this->assertTrue(true);
        }

        function test_error(){
            Clue\CLI::error("err: %s", "test");
            $this->assertTrue(true);
        }

        function test_alert(){
            Clue\CLI::alert("alert message");
            $this->assertTrue(true);
        }

        // ==================== progress() ====================

        function test_progress_total_zero(){
            // total <= 0 时不输出、不报错
            Clue\CLI::progress(0, 0);
            Clue\CLI::progress(5, 0);
            Clue\CLI::progress(10, -1);
            $this->assertTrue(true);
        }

        function test_progress_full(){
            Clue\CLI::progress(100, 100, "done");
            $this->assertTrue(true);
        }

        function test_progress_partial(){
            Clue\CLI::progress(50, 100, "halfway");
            Clue\CLI::progress(1, 1000, "just started");
            $this->assertTrue(true);
        }

        // ==================== 光标/清屏 ====================

        function test_cursor(){
            Clue\CLI::save_cursor('foo');
            Clue\CLI::restore_cursor('foo');
            $this->assertTrue(true);
        }

        function test_erase_line(){
            Clue\CLI::erase_line();
            $this->assertTrue(true);
        }

        function test_erase_screen(){
            Clue\CLI::erase_screen();
            $this->assertTrue(true);
        }

        // ==================== Pipe 联动测试 ====================

        function test_pipe(){
            $php = PHP_BINARY ?: '/usr/local/bin/php';
            $cmd = sprintf(
                '%s %s feed 2>/dev/null | %s %s pipe - 2>/dev/null',
                escapeshellcmd($php),
                escapeshellarg(__FILE__),
                escapeshellcmd($php),
                escapeshellarg(__FILE__)
            );

            $output = shell_exec($cmd);
            $this->assertNotNull($output, 'Pipe command produced no output');
            $this->assertContains("Got Item: 0\ta", $output);
            $this->assertContains("Got Item: 1\tb", $output);
            $this->assertContains("Got Item: 2\tc", $output);
        }
    }
