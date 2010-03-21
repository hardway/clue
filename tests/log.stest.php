<?php  
    require_once 'simpletest/autorun.php';
	require_once 'clue/log.php';
		
	class Test_Clue_Log extends UnitTestCase{
		function setUp(){
			ob_start();
		}
		function tearDown(){
			ob_end_clean();			
		}
		
		function test_clue_log_dumps_log_message_to_stdout(){
			$log=new Clue_Log();

			ob_clean();
			$log->log("hello");
			$this->assertTrue(ob_get_length()>0);
			
			ob_clean();
			$log->log("hello", Clue_Log::ERROR);
			$this->assertTrue(ob_get_length()>0);
			
			return $this->assertTrue(true);
		}
		
		function test_log_level_contains_correct_levels(){
			$log=new Clue_Log();

			$log->log("error message", Clue_Log::ERROR);
			$log->log("warning message", Clue_Log::WARNING);
			$log->log("notice message", Clue_Log::NOTICE);
			$log->log("debug message", Clue_Log::DEBUG);
			
			return $this->assertTrue(true);
		}		
	}
	
	class Test_Clue_Log_File extends UnitTestCase{
		const templog="templog.log";
		const pathlog="temp/templog.log";
		
		function setUp(){
		}
		
		function tearDown(){
			$this->expectError();	// Due to filesystem latency, may unlink file twice and one of them will fail.
			if(file_exists(self::templog)) unlink(self::templog);
			if(file_exists(self::pathlog)){
				unlink(self::pathlog);
				rmdir(dirname(self::pathlog));
			}
		}
		
		function test_create_log_file_automatically(){
			$log=new Clue_Log_File(self::templog);
			$this->assertTrue(file_exists(self::templog));
			
			return $this->assertTrue(true);
		}
		
		function test_create_log_file_under_path_not_exists(){
			$log=new Clue_Log_File(self::pathlog);
			$this->assertTrue(file_exists(self::pathlog));
			
			return $this->assertTrue(true);
		}
		
		function test_appends_log_file_correctly(){
			$msg="Test123";
			$err="Message999";
			
			$log=new Clue_Log_File(self::templog);
			$log->log($msg);
			$log->log($err, IClue_Log::ERROR);
			
			$content=file_get_contents(self::templog);
			
			$this->assertTrue(strlen($content)>0);
			$this->assertTrue(strpos($content, $msg)>0);
			$this->assertTrue(strpos($content, $err)>0);
			
			return $this->assertTrue(true);
		}
	}
?>
