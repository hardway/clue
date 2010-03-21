<?php
    require_once('simpletest/autorun.php');
    
    // Just make sure simple test works
    class TestOfTest extends UnitTestCase {
        function test_if_simpletest_works(){
            $this->assertTrue(1==1);
        }
    }
?>
