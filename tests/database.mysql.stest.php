<?php  
	require_once 'clue/config.php';
	require_once 'clue/database.php';
	
	define('CREATE_TABLE1', "
		create table table1(
			id int primary key,
			col1 varchar(1000),
			Max int,
			col3 timestamp not null default current_timestamp,
			col4 float	
		)
	");
	
	class Test_Database_Mysql extends Snap_UnitTestCase{
		private $db;
		
		function setUp(){
			$cfg=new Clue_Config("data/mysql.ini");
			$this->db=Clue_Database::create('mysql', (array)$cfg->database);
			
			$this->db->exec(CREATE_TABLE1);
		}
		
		function tearDown(){
			$this->db->exec("drop table table1");
		}
		
		function test_get_schema_of_table1(){
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
			
			return $this->assertTrue(true);
		}
	}
?>