<?php
    class Test_RPC extends PHPUnit_Framework_TestCase{
        function test_encdec(){
            include_once dirname(__DIR__).'/rpc/common.php';

            $payload="Quick brown fox jumps over the lazy dog!";
            $secret="secret";

            $enc=clue_rpc_encrypt($payload, $secret);
            $dec=clue_rpc_decrypt($enc, $secret);

            $this->assertEquals($payload, $dec);
        }
    }
