<?php  
    require_once 'simpletest/autorun.php';
    
	require_once dirname(__DIR__).'/database.php';
	
	define('CREATE_TABLE1', "
		create table table1(
			id int primary key auto_increment,
			col1 varchar(1000),
			Max int,
			col3 timestamp not null default current_timestamp,
			col4 float	
		)
	");
	
	class Test_Database_Mysql extends UnitTestCase{
		private $db;
		
		function setUp(){
			$cfg=array(
				"host"=>"localhost",
				"db"=>"test",
				"username"=>"root",
				"password"=>"",
				"encoding"=>"utf8"
			);
			$this->db=Clue_Database::create('mysql', $cfg);
			if(!$this->db->has_table('sample')){
				$this->db->exec("
					create table sample(
						id tinyint unsigned not null auto_increment primary key,
						name varchar(40) not null,
						value int,
						timestamp timestamp not null default current_timestamp
					)
				");
			}
		}
		
		function tearDown(){
		}
		
		function test_get_schema_of_table1(){
			$this->db->exec(CREATE_TABLE1);
			
			$schema=$this->db->get_schema("table1");
			$this->assertEqual(count($schema['column']), 5);
			$this->assertEqual(count($schema['col']), 5);
			$this->assertEqual(join(",", $schema['pkey']), "id");
			
			$cols=array();
			foreach($schema['column'] as $c){
				$cols[]=$c['name'];
			}
			$this->assertEqual(implode(",", $cols), 'id,col1,Max,col3,col4');
			
			$this->assertEqual($schema['col']['col1']['type'], 'varchar');
			$this->assertEqual($schema['col']['Max']['type'], 'int');
			
			$this->db->exec("drop table table1");
			return $this->assertTrue(true);
		}
		
		function test_query_log(){
		    // TODO
		    /*
			$logfile=__DIR__."/data/database_mysql_test_query_log.log";
			$this->db->enable_query_log(new Clue_Log_File($logfile, 'w'));
			
			$this->db->exec("insert into sample(name, value) values('hello', 99)");
			$this->db->get_var("select value from sample");
			
			$this->assertTrue(strlen(file_get_contents($logfile))>0);
			*/
		}
		
		function test_get_affected_rows(){
		    $this->db->exec("insert into sample(name, value) values('China', 1949)");
		    $this->assertEqual($this->db->affected_rows(), 1);
		}
	}
?>