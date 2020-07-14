<?php
    require_once dirname(__DIR__).'/vendor/json5.php';

    class Test_JSON5 extends PHPUnit_Framework_TestCase{
        function test_json5_baseline(){
            $json='
                {
                    n: 7,
                    t: true,
                    // b: false ? 1 : 2,
                    a: ["string"],
                    f: !true,
                }
            ';
            $r=Clue\json5_decode($json, true);
            $this->assertEquals(7, $r['n']);
            $this->assertEquals(true, $r['t']);
            $this->assertEquals("string", $r['a'][0]);
            $this->assertEquals(false, $r['f']);
            $this->assertEquals(false, $r['f']);
            // $this->assertEquals(2, $r['b']);
        }
    }
?>
