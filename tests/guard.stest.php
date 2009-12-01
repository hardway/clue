<?php  
	require_once 'clue/guard.php';
		
	class Test_Clue_Guard extends Snap_UnitTestCase{
		function setUp(){
			Clue_Guard::init();
		}
		
		function tearDown(){}
		
		function test_set_and_get_default_works(){
			$g=new Clue_Guard();
			
			$this->assertNotIdentical($g, Clue_Guard::getDefault());
			
			Clue_Guard::setDefault($g);
			$this->assertIdentical($g, Clue_Guard::getDefault());
			
			return $this->assertTrue(true);
		}
		
		function test_if_it_will_create_default_guard_when_not_set(){
			$g=Clue_Guard::getDefault();
			$this->assertNotNull($g);
			$this->assertIdentical('Clue_Guard', get_class($g));
			
			return $this->assertTrue(true);
		}
		
		function test_catch_error_by_default(){
			Clue_Guard::getDefault()->mute();
			trigger_error("Sample error that will be catched by guard.");
			
			return $this->assertTrue(true);
		}
		
		function test_will_not_catch_error_if_stopped(){
			$this->willError();
			Clue_Guard::getDefault()->stop();
			
			trigger_error("Sample error that will be throw out.", E_USER_ERROR);
			
			return $this->assertTrue(true);
		}
		
		function test_catch_exception(){
			//NOTE: every exception will be catched by snaptest first.
			// So, this test is not completed, and can't be done.
			$this->willThrow("Exception");
			
			throw new RuntimeException("Sample exception that will be catched.");
			
			return $this->assertTrue(true);
		}
		
		function test_will_not_catch_exception_if_stopped(){
			$this->willThrow("Exception");
			Clue_Guard::getDefault()->stop();
			
			throw new Exception("Sample exception will be throwed out");
			
			return $this->assertTrue(true);
		}
	}
?>
