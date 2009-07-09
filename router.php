<?php
	require_once 'clue/core.php';
	
	class Clue_RouteMap{
		protected $cp;
		protected $ap;
		
		public $controller;
		public $action;
		public $query;
		
		function __construct($cpos, $apos){
			$this->cp=$cpos;
			$this->ap=$apos;
		}
		
		function resolve($uri){
			// strip app base
			$appbase=dirname($_SERVER['SCRIPT_NAME']);
			if(strpos($uri, $appbase)==0){
				$uri=substr($uri, strlen($appbase)+1);
			}
			else{
				throw new Exception('Error in route map, wront app base.');
			}
			
			// strip query from uri
			if(($p=strpos($uri, '?'))!==FALSE){
				$uri=substr($uri, 0, $p);
				$this->query=$_GET;
			}
					
			$p=explode('/', $uri);

			if(isset($p[$this->cp]))
				$this->controller=$p[$this->cp];
			if(isset($p[$this->ap]))
				$this->action=$p[$this->ap];
				
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
		
		function route($controller, $action){
			// load controller
			$class="{$controller}Controller";
			require 'controller'.DS."{$class}.php";
			
			$obj=new $class;
			
			// invoke action
			$obj->controller=$controller;
			$obj->action=$action;
			$obj->$action();
		}
		
		function dispatch(){
			$this->map->resolve($_SERVER['REQUEST_URI']);
			$this->route($this->map->controller, $this->map->action);
		}
	}
?>
