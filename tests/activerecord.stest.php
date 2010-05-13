<?php  
    require_once 'simpletest/autorun.php';
    
    require_once dirname(__DIR__).'/database.php';
	require_once dirname(__DIR__).'/activerecord.php';
		
	class LateBindClass extends Clue_ActiveRecord{
	}
	
	class ARWithOverrideDB extends Clue_ActiveRecord{
	    protected static $_db;
	}
	class ARWithoutOverrideDB extends Clue_ActiveRecord{
	    
	}
	
	class Employee extends Clue_ActiveRecord{
	    public $id;
	    public $fullname;
	    public $birthday;
	    
	    protected static $_model=array(
	        'table'=>'employee'
    	);
	}
	
	class AlternateEmployee extends Clue_ActiveRecord{
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
	
	class Test_Clue_ActiveRecord_Isolation extends UnitTestCase{
		function test_static_late_binding(){
			$lbc=new LateBindClass();
			$model=$lbc->model();
			
			$this->assertEqual("latebindclass", $model['table']);
		}
		
		function test_using_different_database_for_each_subclass(){
		    $db0=0;
		    $db1=1;
		    $db2=2;
		    
		    Clue_ActiveRecord::use_database($db0);
		    // Every sub class follows base one
		    $this->assertEqual(Clue_ActiveRecord::db(), $db0);
		    $this->assertEqual(ARWithOverrideDB::db(), $db0);
		    $this->assertEqual(ARWithoutOverrideDB::db(), $db0);
		    
		    ARWithOverrideDB::use_database($db1);
		    // Sub class with overrided database can have it's own db copy
		    $this->assertEqual(Clue_ActiveRecord::db(), $db0);
		    $this->assertEqual(ARWithOverrideDB::db(), $db1);
		    $this->assertEqual(ARWithoutOverrideDB::db(), $db0);		    
		    
		    ARWithoutOverrideDB::use_database($db2);
		    // Sub classes without override database will also modify the base class
		    // while the overrided one is not affected.
		    $this->assertEqual(Clue_ActiveRecord::db(), $db2);
		    $this->assertEqual(ARWithOverrideDB::db(), $db1);
		    $this->assertEqual(ARWithoutOverrideDB::db(), $db2);
		    
		    ARWithOverrideDB::use_database(null);
		    // Clear the static property will fallback to inherit from base class.
		    $this->assertEqual(ARWithOverrideDB::db(), $db2);
		}
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
		
		function test_newly_created_record_will_have_dirty_snapshot(){
		    $e=new Employee(array('fullname'=>'Lich King', 'birthday'=>'1980-01-01'));
		    $this->assertTrue($e->is_new());
		    
		    $cnt=Employee::count();
		    $e->save();
		    $this->assertEqual($cnt+1, Employee::count());
		    
		    $e->destroy();
		    $this->assertEqual($cnt, Employee::count());
		}
		
		function test_record_get_from_database_has_been_snap_shotted(){
		    $e=Employee::get(2);
		    $this->assertFalse($e->is_new());
		    
		    $cnt=Employee::count();
		    $e->save();
		    
		    $this->assertEqual($cnt, Employee::count());
		}
				
		function test_deduce_model_with_predefined_column(){
		    $model=AlternateEmployee::model();
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
		    $e=new Employee();
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
		    $e=Employee::get(1);
		    $this->assertEqual('Jack', $e->fullname);
		    
		    $e=Employee::get(2);
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
		    $e=Employee::find(array('marriage'=>null));
		    $this->assertEqual(count($e), 2);
		}
		
		function test_find_with_literal_condition(){
		    $this->pass("TODO");
		}
		
		function test_find_with_range(){
		    $this->pass("TODO");
		}
		
		function test_count(){
		    $this->assertEqual(Employee::count(), 3);
		    $this->assertEqual(Country::count(), 3);
		}
		
		function test_save_new(){
		    $this->assertEqual(Country::count(), 3);
		    $c=new Country();
		    $c->name="Japan";
		    $c->language="Japanese";
		    $c->capital="Tokyo";
		    $c->save();
		    
		    $this->assertEqual(Country::count(), 4);
		}
		
		function test_save_modified(){
		    $this->assertEqual(Country::count(), 3);
		    $c=Country::find_one(array('name'=>'British'));
		    $this->assertEqual(count(Country::find(array('language'=>'English'))), 2);
		    
		    $c->language="English(UK)";
		    $c->save();
		    
		    $this->assertEqual(Country::count(), 3);
		    $this->assertEqual(count(Country::find(array('language'=>'English'))), 1);
		}
		
		function test_destroy(){
		    $c=Country::find_one(array("name"=>"America"));
		    $c->destroy();
		    $this->assertEqual(Country::count(), 2);
		}
		
		function test_static_magic_find_by(){
		    $america=Country::find_one_by_name("America");
		    $this->assertEqual($america->language, 'English');
		    
		    $englishSpeaking=Country::count_by_language("English");
		    $this->assertEqual($englishSpeaking, 2);
		}		
	}
?>