<?php
	require_once 'clue/core.php';
	require_once 'clue/controller.php';
	require_once 'clue/tool.php';
	
	class Clue_RouteMap{
		protected $cp;
		protected $ap;
		
		public $appbase;
		public $controller;
		public $action;
		public $param;
		
		public $rules;
		
		function __construct(){
			$this->rules=array();
			
			$this->cp=0;
			$this->ap=1;
			
			$this->appbase=dirname($_SERVER['SCRIPT_NAME']);
			$this->param=array();
		}
		
		function reform($controller, $action, $params){
			// TODO: reform using mapping rules
			$url="";

			// convert namespace
			// TODO: according to route map
			$controller=str_replace('_','/',$controller);
			
			if($action=='index')
				$url="{$this->appbase}/$controller";
			else
				$url="{$this->appbase}/$controller/$action";
			
			// 防止controller为index，出现"//"
			if(strpos($url, "//")!==FALSE) $url=str_replace("//", "/", $url);
			
			if(is_array($params)){
				$np=array();
				$sp=array();
				foreach($params as $n=>$v){
					if(is_numeric($n)){
						$np[]=$v;
					}
					else{
						$sp[]="$n=$v";
					}
				}
				if(count($np)>0) $url .= "/".implode('/', $np);
				if(count($sp)>0) $url .= "?".implode('&', $sp);
			}
			
			return $url;
		}
		
		private function replace_with_position($str, $vals){
			$ret=$str;
			
			if(preg_match_all('|\{(\d+)\}|', $str, $match)){
				foreach($match[1] as $p){
					if(isset($vals[$p])){
						$ret=str_replace("{{$p}}", $vals[$p], $ret);
					}
				}
			}
			
			return $ret;
		}
		
		function resolve($uri){
			// strip app base
			
			if(strpos($uri, $this->appbase)==0){
				$uri=substr($uri, strlen($this->appbase)+1);
			}
			else{
				throw new Exception('Error in route map, wrong app base.');
			}
			
			// strip query from uri
			if(($p=strpos($uri, '?'))!==FALSE){
				$uri=substr($uri, 0, $p);
			}
			
			// try to match against rule
			foreach($this->rules as $m=>$r){
				$m=str_replace('?', '(.+)', $m);
				$m=str_replace("\\", "\\\\", $m);
				$m=str_replace('|', '(\|)', $m);
				
				if(preg_match("|$m|i", '/'.$uri, $match)){
					$this->controller=$this->replace_with_position($r[0], $match);
					$this->action=$this->replace_with_position($r[1], $match);
					$this->param=$r[2];
					foreach($this->param as &$p){
						$p=$this->replace_with_position($p, $match);
					}
					
					return;
				}
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
	
	class Clue_QueryRouteMap extends Clue_RouteMap{	
		function resolve($uri){
			if(isset($_GET["_c"])) {$this->controller=$_GET["_c"]; unset($_GET["_c"]);} else {$this->controller="index";}
			if(isset($_GET["_a"])) {$this->action=$_GET["_a"]; unset($_GET["_a"]);} else {$this->action="index";}
		}
		
		function reform($controller, $action, $params){
			// TODO: reform using mapping rules
			$ps=array();
			if(is_array($params)) foreach($params as $n=>$v){
				$ps[]="&$n=$v";
			}
			return "$this->appbase/index.php?_c=$controller&_a=$action" . implode("", $ps);
		}
	}
	
	class Clue_Router{
		protected $map;
		
		function __construct($option){
			// Determine controller and action by default map
			$this->map=@$option['url_rewrite'] ? 
				new Clue_RouteMap($option['map']) : 
				new Clue_QueryRouteMap();		
		}
		
		function add_rule($pattern, $route){
			$this->map->rules[$pattern]=$route;
		}
		
		function controller(){
			return $this->map->controller;
		}
		
		// URL Base for the application, which is defined in route-map
		function base(){
			return $this->map->appbase;
		}
		
		function uri_for($controller, $action='index', $param=null){
			return $this->map->reform($controller, $action, $param);
		}
		
		function redirect_route($controller, $action='index', $param=null){
			$uri=$this->uri_for($controller, $action, $param);
			$this->redirect($uri);
		}
		
		function redirect($url){
			header("Status: 302 Found");
			header("Location: $url");
			exit();
		}
		
		function route($controller, $action, $param=array()){
			// load controller
			$class="{$controller}Controller";		
			$path="controller/".strtolower(str_replace('_','/',$class)).".php";

			if($_SERVER['REQUEST_METHOD']=='POST')
				$action="_$action";
			
			if(file_exists($path)){
				require_once $path;
				// Action not detected
				if(!in_array($action, get_class_methods($class))){					
					$try_param=POST() ? substr($action, 1) : $action;
					$try_action=POST() ? "_index" : "index";
					
					// fallback to default action - "index"					
					if(in_array($try_action, get_class_methods($class))){
						array_unshift($param, urldecode($try_param));
						$action=$try_action;
					}
					else{
						$class='ErrorController';
						$action='noAction';
						require_once "controller/errorcontroller.php";
					}
				}
			}
			else{
				// Controller not detected.
				// TODO: better implementation
				$class='ErrorController';
				$action='noController';
				require_once "controller/errorcontroller.php";
			}
			
			$obj=new $class($controller, $action);
			
			// invoke action
			$obj->controller=$controller;
			$obj->view=$action;
			$obj->action=$action;
			
			call_user_func_array(array($obj, $obj->action), $param);
		}
		
		function dispatch(){
			$this->map->resolve($_SERVER['REQUEST_URI']);
			
			$this->route($this->map->controller, $this->map->action, $this->map->param);
		}
	}
?>
