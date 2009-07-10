<?php
	require_once 'clue/core.php';
	require_once 'clue/controller.php';
	
	class Clue_RouteMap{
		protected $appbase;
		protected $cp;
		protected $ap;
		
		public $controller;
		public $action;
		public $param;
		
		function __construct($cpos, $apos){
			$this->cp=$cpos;
			$this->ap=$apos;
			$this->appbase=dirname($_SERVER['SCRIPT_NAME']);
		}
		
		function reform($controller, $action, $params){
			// TODO: reform using mapping rules
			return "{$this->appbase}/$controller/$action";
		}
		
		function resolve($uri){
			// strip app base
			
			if(strpos($uri, $this->appbase)==0){
				$uri=substr($uri, strlen($this->appbase)+1);
			}
			else{
				throw new Exception('Error in route map, wront app base.');
			}
			
			// strip query from uri
			if(($p=strpos($uri, '?'))!==FALSE){
				$uri=substr($uri, 0, $p);
			}
			
			// explode path and do mapping.
			$p=explode('/', $uri);

			for($i=0; $i<count($p); $i++){
				if($i==$this->cp)
					$this->controller=$p[$this->cp];
				else if($i==$this->ap)
					$this->action=$p[$this->ap];
				else
					$this->param[]=$p[$i];
			}
				
			// Default controller and action
			if(empty($this->controller)) $this->controller='Index';
			if(empty($this->action)) $this->action='index';
		}
	}
	
	class Clue_Router{
		protected $map;
		
		function __construct(){
			// Determine controller and action by default map
			$this->map=new Clue_RouteMap(0, 1);
		}
		
		function uri_for($controller, $action, $param=null){
			return $this->map->reform($controller, $action, $param);
		}
		
		function route($controller, $action, $param=null){
			// load controller
			$class="{$controller}Controller";
			require "controller/{$class}.php";
			
			$obj=new $class;
			
			// invoke action
			$obj->controller=$controller;
			$obj->view=$action;
			
			if($_SERVER['REQUEST_METHOD']=='POST')
				$obj->action="_$action";
			else
				$obj->action=$action;			
			
			call_user_func_array(array($obj, $obj->action), $param);
		}
		
		function dispatch(){
			$this->map->resolve($_SERVER['REQUEST_URI']);
			$this->route($this->map->controller, $this->map->action, $this->map->param);
		}
	}
?>
