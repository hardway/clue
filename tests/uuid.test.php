<?php
include dirname(__DIR__).'/tool.php';

class Test_UUID extends PHPUnit_Framework_TestCase{
    function test_uuid36(){
        $ids=[];
        $cnt=1000;
        $custom="*1234*";

        for($i=0; $i<$cnt; $i++){
            $uuid=Clue\uuid($custom);
            $this->assertTrue(strpos($uuid, $custom)!==false, 'Custom string should be included');
            $this->assertEquals(20, strlen($uuid), "UUID should have correct length ($i): $uuid");
            $ids[]=$uuid;
        }

        $seq=implode(",", $ids);
        $this->assertEquals($cnt, count($ids), 'All UUIDs should be generated.');

        sort($ids);
        $this->assertEquals($seq, implode(",", $ids), 'Should based on time.');

        $unique=array_unique($ids);
        $this->assertEquals($cnt, count($ids), "Should not have duplicates");
    }
}
