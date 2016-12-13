<?php
    require_once dirname(__DIR__).'/stub.php';

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

        function test_progress(){
            echo "This is progress test: ";

            for($i=0; $i<=100; $i++){
                Clue\CLI::save_cursor('progress');
                echo " $i% ";
                usleep(10000);
                CLUE\CLI::restore_cursor("progress");
            }

            echo "Done.\n";
        }
    }

    $t=new Test_CLI;
    $t->test_progress();
?>
