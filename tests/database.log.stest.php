<?php  
    require_once 'simpletest/autorun.php';
	require_once 'clue/core.php';
		
	class Test_Log_Database extends UnitTestCase{
		private $db;
		private $cfg;
		private $log;
		private $logdb;
		
		function count_table($table){
			return $this->logdb->get_var("select count(*) from $table");
		}
		
		function setUp(){
			if(empty($this->cfg)){
				$this->cfg=array(
					"host"=>"localhost", "db"=>"test",
					"username"=>"root", "password"=>""
				);
			}
			if(empty($this->db)){
				$this->db=Clue_Database::create('mysql', $this->cfg);
			}
			if(empty($this->log)){
				$this->log=new Clue_Log_Database($this->cfg);
			}
			if(empty($this->logdb)){
				$this->cfg['db']='database_log';
				$this->logdb=Clue_Database::create('mysql', $this->cfg);
			}
		}
		
		function tearDown(){
		}
		
		function test_log_a_notice(){
			$this->log->log('test', "Hi, this is a notice");
			
			return $this->assertTrue(true);
		}
		
		function test_error_log(){
			$this->log->log_error('test', "Hi, this is a notice", 1, __FILE__, __LINE__);
			
			return $this->assertTrue(true);
		}
		
		function test_exception_log(){
			$e=new Exception("Sample Exception");
			$this->log->log_exception('test', $e);
			
			return $this->assertTrue(true);
		}
		
		function test_query_log(){
			
			return $this->assertTrue(true);
		}
	}
?>