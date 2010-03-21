<?php  
    require_once 'simpletest/autorun.php';
    
    require_once 'clue/database.php';
	require_once 'clue/activerecord.php';
		
	class LateBindClass extends Clue_ActiveRecord{
	}
	
	class SampleEmployee extends Clue_ActiveRecord{	    
	    public $id;
	    public $fullname;
	    public $birthday;
	    
	    protected static $_model=array(
	        'table'=>'employee'
    	);
	}
	
	class TinyEmployee extends Clue_ActiveRecord{
	    public $name;
	    
	    protected static $_model=array(
	        'columns'=>array(
	            'name'=>array('name'=>'fullname')
	        )
        );
	}
	
	class Country extends Clue_ActiveRecord{
	    public $name;
	    public $language;
	    public $capital;
	    
	    protected static $_model=array(
	        'pkey'=>'name'
        );
	}
	
	class Test_Clue_ActiveRecord extends UnitTestCase{
	    private $db;
	    
		function setUp(){
		    $this->db=Clue_Database::create('mysql', array('host'=>'localhost', 'username'=>'root', 'password'=>'', 'db'=>'test'));
		    $this->db->exec("
		        drop table if exists employee;
		    ");
		    $this->db->exec("
		        create table employee(
		            id int primary key auto_increment,
		            fullname varchar(20) not null,
		            birthday datetime not null,
		            marriage int,
		            note varchar(256)
        		) engine=memory;
		    ");
		    $this->db->exec("insert into employee(fullname, birthday, marriage) values('Jack', '1970-1-1', 1)");
		    $this->db->exec("insert into employee(fullname, birthday) values('Rose Mary', '1972-3-8')");
		    $this->db->exec("insert into employee(fullname, birthday) values('Baby', '2012-6-1')");
		    
		    $this->db->exec("
		        drop table if exists country;
		    ");
		    $this->db->exec("
		        create table country(
		            name varchar(32) not null primary key,
		            language varchar(32) not null,
		            capital varchar(32) not null
        		) engine=memory;
		    ");
		    $this->db->exec("insert into country(name, language, capital) values('China', 'Chinese', 'Beijing')");
		    $this->db->exec("insert into country(name, language, capital) values('America', 'English', 'Washington')");
		    $this->db->exec("insert into country(name, language, capital) values('British', 'English', 'London')");
		    
		    Clue_ActiveRecord::use_database($this->db);
		}
		
		function tearDown(){
		    // Do nothing
		}
		
		function test_static_late_binding(){
			$lbc=new LateBindClass();
			$model=$lbc->model();
			
			$this->assertEqual("latebindclass", $model['table']);
		}
		
		function test_deduce_model_with_predefined_column(){
		    $model=TinyEmployee::model();
		    $this->assertEqual($model['columns']['name']['name'], 'fullname');
		}
		
		function test_bind_with_construction(){
		    $c=new Country(array(
		        'name' => "Name",
		        'language' => 'Language',
		        'capital' => 'Capital'
		    ));
		    
		    $this->assertEqual($c->name, "Name");
		    $this->assertEqual($c->language, "Language");
		    $this->assertEqual($c->capital, "Capital");
		}
		
		function test_bind_with_less_data(){
		    $c=new Country(array(
		        'name' => "Name",
		        'capital' => 'Capital'
		    ));
		    
		    $this->assertEqual($c->name, "Name");
		    $this->assertEqual($c->capital, "Capital");
		    $this->assertNull($c->language);
		}

		function test_bind_with_more_data(){
		    $c=new Country(array(
		        'name' => "Name",
		        'country' => 'Country',
		        'capital' => 'Capital'
		    ));
		    
		    $this->assertEqual($c->name, "Name");
		    $this->assertEqual($c->capital, "Capital");
		    $this->assertFalse(isset($c->country));
		}
		
		function test_table_column_as_property(){
		    $e=new SampleEmployee();
		    $model=$e->model();
		    
		    $this->assertTrue(isset($model['columns']['id']));
		}
		
		function test_deduce_table_name_by_class_name(){
		    $model=Country::model();
		    $this->assertEqual("country", $model['table']);
		}
		
		function test_primary_key_is_string(){
		    $c=Country::get('China');
		    $this->assertEqual('Chinese', $c->language);
		}
		
		function test_get_record(){
		    $e=SampleEmployee::get(1);
		    $this->assertEqual('Jack', $e->fullname);
		    
		    $e=SampleEmployee::get(2);
		    $this->assertEqual('1972-03-08 00:00:00', $e->birthday);
		}
		
		function test_find_all_records(){
		    $countries=Country::find(array('language'=>'english'));
		    $this->assertEqual(count($countries), 2);
		    
		    $this->assertEqual($countries[0]->name, "America");
    		$this->assertEqual($countries[1]->name, "British");
		}
		
		function test_find_one_record(){
		    $c=Country::find_one(array('capital'=>'London'));
		    $this->assertEqual($c->name, 'British');
		}
		
		function test_find_with_multiple_condition(){
		    $this->pass('TODO');
		}
		
		function test_find_with_null_condition(){
		    $e=SampleEmployee::find(array('marriage'=>null));
		    $this->assertEqual(count($e), 2);
		}
		
		function test_find_with_literal_condition(){
		    $this->pass("TODO");
		}
		
		function test_find_with_range(){
		    $this->pass("TODO");
		}
		
		function test_count_all(){
		    $this->assertEqual(SampleEmployee::count_all(), 3);
		    $this->assertEqual(Country::count_all(), 3);
		}
		
		function test_save_new(){
		    $this->assertEqual(Country::count_all(), 3);
		    $c=new Country();
		    $c->name="Japan";
		    $c->language="Japanese";
		    $c->capital="Tokyo";
		    $c->save();
		    
		    $this->assertEqual(Country::count_all(), 4);
		}
		
		function test_save_modified(){
		    $this->assertEqual(Country::count_all(), 3);
		    $c=Country::find_one(array('name'=>'British'));
		    $this->assertEqual(count(Country::find(array('language'=>'English'))), 2);
		    
		    $c->language="English(UK)";
		    $c->save();
		    
		    $this->assertEqual(Country::count_all(), 3);
		    $this->assertEqual(count(Country::find(array('language'=>'English'))), 1);
		}
		
		function test_destroy(){
		    $c=Country::find_one(array("name"=>"America"));
		    $c->destroy();
		    $this->assertEqual(Country::count_all(), 2);
		}
	}
?>