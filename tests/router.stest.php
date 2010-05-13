<?php  
    require_once 'simpletest/autorun.php';
	require_once dirname(__DIR__).'/router.php';
		
	class Test_Route extends UnitTestCase{
		function setUp(){
			
		}
		
		function tearDown(){
		}
		
		function test_basic_conneting(){
			$R=new Clue_RouteMap();
			
			$R->connect(':controller/:action/:id');
			$r=$R->resolve('board/show/2');
			
			$this->assertEqual($r['controller'], 'board');
			$this->assertEqual($r['action'], 'show');
			$this->assertEqual($r['params'], array('id'=>2));
						
			return $this->assertTrue(true);
		}
		
		function test_conneting_with_query_string(){
			$R=new Clue_RouteMap();
			
			$R->connect(':controller/:action/:id');
			
			global $_GET;
			$_GET['color']='white';
			
			$r=$R->resolve('board/show/2?color=white');
			
			$this->assertEqual($r['params']['id'], 2);
			$this->assertEqual($r['params']['color'], 'white');
						
			return $this->assertTrue(true);
		}
		
		function test_conneting_with_post_form(){
			$R=new Clue_RouteMap();
			
			$R->connect(':controller/:action/:id');
			
			global $_POST;
			$_POST['confirm']='no';
			
			$r=$R->resolve('board/delete/2');
			
			$this->assertEqual($r['params']['id'], 2);
			$this->assertEqual($r['params']['confirm'], 'no');
						
			return $this->assertTrue(true);
		}
		
		function test_connecting_with_default_controller_action(){
			$R=new Clue_RouteMap();
			
			$R->connect(':id/post', array('controller'=>'board', 'action'=>'post'));
			$r=$R->resolve('45/post');
			
			$this->assertEqual($r['controller'], 'board');
			$this->assertEqual($r['action'], 'post');
			$this->assertEqual($r['params']['id'], 45);
			
			return $this->assertTrue(true);
		}
		
		function test_connecting_with_empty_route(){
			$R=new Clue_RouteMap();
			
			$R->connect('', array('controller'=>'board'));
			$r=$R->resolve('');
			
			$this->assertEqual($r['controller'], 'board');
			$this->assertEqual($r['action'], 'index');
			
			return $this->assertTrue(true);
		}
		
		function test_reform_basic_mapping(){
			$R=new Clue_RouteMap();
			
			$R->connect(':controller/:action/:id');
			$url=$R->reform('board', 'show', array('id'=>2));
			
			$this->assertEqual($url, 'board/show/2');
						
			return $this->assertTrue(true);
		}

		function test_reform_with_lack_of_params(){
			$R=new Clue_RouteMap();
			$R->connect(':controller/:action/:id');
			
			$this->expectException();
			$url=$R->reform('board', 'show');
						
			return $this->assertTrue(true);
		}
		
		function test_reform_with_extra_params(){
			$R=new Clue_RouteMap();			
			$R->connect(':controller/:action/:id');
			
			$url=$R->reform('board', 'show', array('id'=>2, 'type'=>'max'));			
			$this->assertEqual($url, 'board/show/2?type=max');
						
			return $this->assertTrue(true);
		}
		
		function test_reform_without_rule(){
			$R=new Clue_RouteMap();			
			
			$this->expectException();
			$url=$R->reform('board', 'show');

			return $this->assertTrue(true);
		}
		
		function test_params_with_regexp(){
			$R=new Clue_RouteMap();
			
			$R->connect(':id/post', array('controller'=>'board', 'action'=>'post', 'id'=>'\d+'));
			$R->connect(':mid/:action', array(
			    'controller'=>'board', 'action'=>'get|put', 'mid'=>'\d+'
			));
			$R->connect(':name/:action', array('controller'=>'board', 'name'=>'[str]+'));
			
			$r=$R->resolve('45/post');
			
			$this->assertEqual($r['controller'], 'board');
			$this->assertEqual($r['action'], 'post');
			$this->assertEqual($r['params']['id'], 45);
			
			$r=$R->resolve('45/get');
			$this->assertEqual($r['action'], 'get');
			$this->assertEqual($r['params']['mid'], 45);
			
			$r=$R->resolve('45/put');
			$this->assertEqual($r['action'], 'put');
			$this->assertEqual($r['params']['mid'], 45);

			$r=$R->resolve('STR/put');
			$this->assertEqual($r['action'], 'put');
			$this->assertEqual($r['params']['name'], 'STR');

            // Reforming
			$url=$R->reform('board', 'post', array('id'=>123));
			$this->assertEqual($url, '123/post');
			
			$url=$R->reform('board', 'put', array('mid'=>123));
			$this->assertEqual($url, '123/put');

            // Exception
			$this->expectException();
			$r=$R->resolve('string/post');
		}
	}
?>