<?php
	require_once __DIR__.'/core.php';
	require_once __DIR__.'/controller.php';
	require_once __DIR__.'/tool.php';
	
	// TODO: rule order (fallback ?)
	class Clue_RouteMap{
		protected $cp;
		protected $ap;
		
		public $controller;
		public $action;
		public $param;
		
		public $rules;
		
		function __construct(){
			$this->rules=array();
			
			$this->cp=0;
			$this->ap=1;
			
			$this->param=array();
		}
		
		function connect($urlPattern, $rule=array()){
			// TODO: check validity of url pattern and mapping rule
			
			// decode mapping string to array
			$mapping=array();
			if($rule) foreach($rule as $n=>$v){
				$mapping[$n]=$v;
			}

			// decode url pattern into name list
			$names=array();
			$pattern=preg_replace_callback('/:([a-zA-Z0-9_]+)/', function($m) use(&$names, $mapping){
			    $name=$m[1];
				$names[]=$name;

				if($name=='controller' || $name=='action')
					return '([^/]*)';
				else{
				    if(isset($mapping[$name])){
				        $p=$mapping[$name];
				        return "($p)";
			        }
			        else
					    return '([^/]+)';
				}
			}, $urlPattern);
			
			$pattern="^$pattern\$";
						
			$this->rules[]=array(
				'reformation'=>$urlPattern,
				'pattern'=>$pattern,
				'names'=>$names,
				'mapping'=>$mapping,
			);
			
			return true;
		}
		
		function reform($controller, $action, $params=array()){
			foreach($this->rules as $r){
				if(
					isset($r['mapping']['controller']) && 
					!preg_match('/'.$r['mapping']['controller'].'/i', $controller)
				) continue;
				
				if(
					isset($r['mapping']['action']) && 
					!preg_match('/'.$r['mapping']['action'].'/i',$action)
				) continue;

				$allParamsAreMet=true;
				foreach(array_keys($params) as $name){
				    if(isset($r['mapping'][$name]) && !preg_match('/'.$r['mapping'][$name].'/i', $params[$name])) continue;
				}
				
				$params['controller']=$controller;
				$params['action']=$action;
								
				$url=preg_replace_callback('/\:([a-zA-Z0-9_]+)/', function($m) use(&$params, $r){
				    $name=$m[1];
				    
					if(isset($params[$name])){
						$ret=urlencode($params[$name]);
						unset($params[$name]);
						return $ret;
					}
					else{
						// TODO: Clue_RouterException
						throw new Exception("Couldn't found parameter '$name' in mapping rule.");
					}
				}, $r['reformation']);

				$query=array();
				if($params) foreach($params as $n=>$v){
					if($n=='controller' || $n=='action') continue;
					$query[]="$n=$v";
				}
				if(count($query)>0)
					$url.='?'.implode('&', $query);
				
				return $url;
			}
			
			throw new Exception('COUND NOT REFORM');
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
			$params=array();
			
			// strip query from uri
			if(($p=strpos($uri, '?'))!==FALSE){
				$uri=substr($uri, 0, $p);
			}
			
			// try to match against rules
			foreach($this->rules as $r){
				if(preg_match("|{$r['pattern']}|i", $uri, $match)){
					array_shift($match);
					$mapping=$r['mapping'];
					for($i=0; $i<count($match); $i++){
						$mapping[$r['names'][$i]]=$match[$i];
					}
					
					// Default controller is Index
					if(empty($mapping['controller'])) $mapping['controller']='Index';
					// Default action is index
					if(empty($mapping['action'])) $mapping['action']='index';
					
					foreach($mapping as $n=>$v){
						if($n=='controller' || $n=='action') continue;
						$params[$n]=urldecode($v);
						unset($mapping[$n]);
					}
					$params=array_merge($params, $_GET, $_POST);
					
					$mapping['params']=$params;
					return $mapping;
				}
			}
			
			throw new Exception("No route found.");
			
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
		
		function reform($controller, $action, $params=array()){
			$ps=array();
			if(is_array($params)) foreach($params as $n=>$v){
				$ps[]="&$n=$v";
			}
			return "/index.php?_c=$controller&_a=$action" . implode("", $ps);
		}
	}
	
	class Clue_Router{
		protected $appbase;
		protected $map;
		
		function __construct($option){
			$this->appbase=dirname($_SERVER['SCRIPT_NAME']);
			
			// Determine controller and action by default map
			$this->map=@$option['url_rewrite'] ? 
				new Clue_RouteMap() : 
				new Clue_QueryRouteMap();		
		}
		
		function connect($pattern, $route=''){
			$this->map->connect($pattern, $route);
		}
		
		function controller(){
			return $this->controller;
		}
		
		// URL Base for the application, which is defined in route-map
		function base(){
			return $this->appbase;
		}
		
		function uri_for($controller, $action='index', $param=null){
			return $this->appbase . $this->map->reform($controller, $action, $param);
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
		
		function route($controller, $action, $params=array()){
			// load controller
			$class="{$controller}Controller";
			$path="controller/".strtolower(str_replace('_','/',$class)).".php";

			if($_SERVER['REQUEST_METHOD']=='POST')
				$action="_$action";

			if(!class_exists($class, false)){
			    if(file_exists($path)) require_once $path;
			    if(!class_exists($class, false))
			        return $this->route('error', 'noController', array('controller'=>$controller));
			}
			
			$rfxClass=new ReflectionClass($class);
			
			if($rfxClass->hasMethod($action)){
				$rfxMethod=new ReflectionMethod($class, $action);
				
				// detect parameters using reflection
				$callArgs=array();
				foreach($rfxMethod->getParameters() as $rfxParam){
					if(isset($params[$rfxParam->name])){
						$callArgs[]=$params[$rfxParam->name];
					}
					else{
						$callArgs[]=null;
					}
				}
				
				$obj=new $class($controller, $action);
				
				// invoke action
				$obj->params=$params;
				$obj->controller=$controller;
				$obj->view=$action;
				$obj->action=$action;
				
				call_user_func_array(array($obj, $obj->action), $callArgs);
			}
			else{
			    throw new Exception("Can't find action $action of $controller");
			}
		}
		
		function resolve($uri){
			if(strpos($uri, $this->appbase)===0){
				$uri=substr($uri, strlen($this->appbase));
			}
		    return $this->map->resolve($uri);
		}
	}
?>
