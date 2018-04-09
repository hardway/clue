<?php
    require_once dirname(__DIR__).'/stub.php';

    $cli=new Clue\CLI\Command("Test Pipe", 'cli');

    /**
     * Pipe Receiver
     * @param $p1 Item1
     * @param $p2 Item2
     */
    function cli_pipe($p1, $p2){
        printf("Got Item: %s\t%s\n", $p1, $p2);
        sleep(1);
    }

    /**
     * Pipe Generator
     */
    function cli_feed(){
        for($i=0; $i<3; $i++){
            printf("%d, %s\n", $i, chr(ord('a')+$i));
            sleep(1);
        }
    }

    /**
     * Progress Bar
     */
    function cli_progress(){
        echo "This is progress test: ";

        for($i=0; $i<=100; $i++){
            Clue\CLI::save_cursor('progress');
            echo " $i% ";
            usleep(10000);
            CLUE\CLI::restore_cursor("progress");
        }

        echo "Done.\n";
    }

    if(!class_exists('PHPUnit_Framework_TestCase')){
        class PHPUnit_Framework_TestCase{};
    }

    if(count($argv)>1){
        $cli->handle($argv);
        exit();
    }

    class Test_CLI extends PHPUnit_Framework_TestCase{
        function test_console_text(){
            $colors=['black', 'red', 'green', 'yellow', 'blue', 'magenta', 'cyan', 'white'];
            foreach($colors as $fg){
                Clue\CLI::text(" $fg"."\n", $fg);
            }

            foreach($colors as $bg){
                Clue\CLI::banner(" $bg", $bg);
            }
        }

        function test_pipe(){
            $cmd=sprintf("php %s feed | php %s pipe -", __FILE__, __FILE__);
            printf("\nPipeTest: %s\n", $cmd);
            passthru($cmd);
        }
    }
